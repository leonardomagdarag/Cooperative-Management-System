import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import Button from '../../components/ui/Button';
import Badge from '../../components/ui/Badge';
import { useToast } from '../../contexts/ToastContext';
import Modal from '../../components/ui/Modal';
import ModalHeader from '../../components/ui/ModalHeader';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { useAuth } from '../../contexts/AuthContext';

interface RouteLoan {
    id: number;
    branch_id: number;
    branch_name: string;
    loan_number: string;
    member_id: number;
    member_name: string;
    contact_number: string | null;
    address: string | null;
    amount_due: number;
    days_overdue: number;
    savings_account_id: number | null;
}

export default function MyRouteTab() {
    const { user } = useAuth();
    const { addToast } = useToast();
    const [loans, setLoans] = useState<RouteLoan[]>([]);
    const [loading, setLoading] = useState(true);
    const [draftRuns, setDraftRuns] = useState<any[]>([]);
    const [selectedBranchId, setSelectedBranchId] = useState<number | null>(user?.branch_id ?? null);
    const [selectedLoan, setSelectedLoan] = useState<RouteLoan | null>(null);
    const [editingItem, setEditingItem] = useState<any>(null);
    
    // Add item form state
    const [amount, setAmount] = useState('');
    const [note, setNote] = useState('');
    const [visited, setVisited] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    
    const { markDirty, reset, requestClose, renderDialog } = useDiscardGuard(amount !== '' || note !== '');

    const handleCloseModal = () => {
        requestClose(() => {
            setSelectedLoan(null);
            setEditingItem(null);
            setAmount('');
            setNote('');
            reset();
        });
    };

    useEffect(() => {
        fetchData();
    }, [user?.id]);

    const fetchData = async () => {
        setLoading(true);
        try {
            // Fetch route list
            const resLoans = await api.get('/api/collections/route-list');
            const routeLoans: RouteLoan[] = resLoans.data.data;
            setLoans(routeLoans);

            // Keep this officer's drafts so switching branches selects the matching envelope.
            const resRuns = await api.get('/api/collections?status=draft');
            const runs = (resRuns.data.data || []).filter((run: any) => Number(run.loan_officer_id) === Number(user?.id));
            const details = await Promise.all(runs.map(async (run: any) => {
                const response = await api.get(`/api/collections/${run.id}`);
                return { ...response.data.data, branch_name: run.branch?.name };
            }));
            setDraftRuns(details);
            setSelectedBranchId(current => current ?? user?.branch_id ?? details[0]?.branch_id ?? routeLoans[0]?.branch_id ?? null);
        } catch (err: any) {
            addToast('error', 'Failed to load route data');
        } finally {
            setLoading(false);
        }
    };

    const startRun = async () => {
        try {
            if (!selectedBranchId) {
                addToast('error', 'Choose a branch before starting a collection run');
                return;
            }
            const res = await api.post('/api/collections', {
                collection_date: new Intl.DateTimeFormat('en-CA').format(new Date()),
                branch_id: selectedBranchId,
            });
            const runDetails = await api.get(`/api/collections/${res.data.data.id}`);
            setDraftRuns(current => [...current.filter(run => run.id !== runDetails.data.data.id), runDetails.data.data]);
            addToast('success', 'Collection run started');
        } catch (err: any) {
            addToast('error', err.response?.data?.message || 'Failed to start run');
        }
    };

    const handleAddItem = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeRun || (!selectedLoan && !editingItem)) return;

        setIsSubmitting(true);
        try {
            if (editingItem) {
                await api.put(`/api/collections/${activeRun.id}/items/${editingItem.id}`, { amount: parseFloat(amount), note, visited });
            } else {
                await api.post(`/api/collections/${activeRun.id}/items`, {
                    member_id: selectedLoan!.member_id,
                    loan_id: selectedLoan!.id,
                    account_id: selectedLoan!.savings_account_id,
                    amount: parseFloat(amount), note, visited
                });
            }
            addToast('success', editingItem ? 'Collection updated' : 'Collection recorded');
            setSelectedLoan(null);
            setEditingItem(null);
            setAmount('');
            setNote('');
            setVisited(true);
            reset();
            fetchData(); // Refresh active run
        } catch (err: any) {
            addToast('error', err.response?.data?.message || 'Failed to record collection');
        } finally {
            setIsSubmitting(false);
        }
    };

    const branches = Array.from(new Map([
        ...loans.map(loan => [loan.branch_id, { id: loan.branch_id, name: loan.branch_name }] as const),
        ...draftRuns.map(run => [Number(run.branch_id), { id: Number(run.branch_id), name: run.branch_name || `Branch ${run.branch_id}` }] as const),
    ]).values());
    const activeRun = draftRuns.find(run => Number(run.branch_id) === Number(selectedBranchId)) ?? null;
    const routeLoans = loans.filter(loan => Number(loan.branch_id) === Number(selectedBranchId));

    const editItem = (item: any) => {
        setEditingItem(item);
        setAmount(String(item.amount));
        setNote(item.note || '');
        setVisited(Boolean(item.visited));
    };

    const removeItem = async (item: any) => {
        if (!activeRun || !confirm('Remove this collection record from the draft run?')) return;
        try {
            await api.delete(`/api/collections/${activeRun.id}/items/${item.id}`);
            addToast('success', 'Collection removed');
            await fetchData();
        } catch (err: any) {
            addToast('error', err.response?.data?.message || 'Failed to remove collection');
        }
    };

    const submitRun = async () => {
        if (!activeRun || activeRun.items?.length === 0) {
            addToast('error', 'Cannot submit an empty run');
            return;
        }

        if (confirm('Are you sure you want to submit this run for approval? You will not be able to edit it afterwards.')) {
            try {
                await api.post(`/api/collections/${activeRun.id}/submit`);
                addToast('success', 'Run submitted successfully');
                fetchData();
            } catch (err: any) {
                const message = String(err.response?.data?.message || '');
                if (err.response?.status === 422 && /late|deadline/i.test(message)) {
                    addToast('error', 'The submission deadline has passed. Contact a manager to submit this run on your behalf.');
                } else {
                    addToast('error', err.response?.data?.message || 'Failed to submit run');
                }
            }
        }
    };

    if (loading) return <div className="p-8 text-center text-slate-500"><i className="fa-solid fa-spinner fa-spin mr-2"></i>Loading route...</div>;

    return (
        <div className="flex flex-col md:flex-row h-full">
            {/* Route List (Left Panel) */}
            <div className="w-full md:w-2/3 border-r border-slate-200 p-6">
                <div className="flex justify-between items-center mb-4">
                    <div>
                        <h2 className="text-lg font-bold text-slate-800">Today's Route</h2>
                        {(branches.length > 1 || (branches.length === 1 && selectedBranchId !== branches[0].id)) && (
                            <label className="mt-2 flex items-center gap-2 text-sm text-slate-600">
                                Branch
                                <select
                                    className="rounded-md border border-slate-300 bg-white px-2 py-1"
                                    value={selectedBranchId ?? ''}
                                    onChange={event => setSelectedBranchId(event.target.value ? Number(event.target.value) : null)}
                                >
                                    {branches.map(branch => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                                </select>
                            </label>
                        )}
                    </div>
                    <Badge variant="blue">{routeLoans.length} Borrowers</Badge>
                </div>

                <div className="space-y-4">
                    {routeLoans.length === 0 ? (
                        <div className="text-center py-8 text-slate-500 bg-slate-50 rounded-lg">
                            <i className="fa-solid fa-check-circle text-4xl mb-3 text-slate-300"></i>
                            <p>No collections scheduled for today.</p>
                        </div>
                    ) : (
                        routeLoans.map(loan => {
                            const isCollected = activeRun?.items?.some((i: any) => i.loan_id === loan.id);
                            return (
                                <div key={loan.id} className={`p-4 border rounded-lg flex justify-between items-center ${isCollected ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-slate-200'}`}>
                                    <div>
                                        <div className="font-semibold text-slate-800">{loan.member_name}</div>
                                        <div className="text-sm text-slate-500 mt-1">
                                            <i className="fa-solid fa-hashtag mr-1"></i>{loan.loan_number} &bull; 
                                            <i className="fa-solid fa-phone mx-1"></i>{loan.contact_number || 'No contact'}
                                        </div>
                                        <div className="text-sm text-slate-500 mt-1">
                                            <i className="fa-solid fa-location-dot mr-1"></i>{loan.address || 'No address on file'}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-lg font-bold text-slate-800">₱{loan.amount_due.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                        {loan.days_overdue > 0 && <Badge variant="red" className="mt-1">{loan.days_overdue} days overdue</Badge>}
                                        
                                        <div className="mt-2">
                                            {isCollected ? (
                                                <Badge variant="emerald"><i className="fa-solid fa-check mr-1"></i>Collected</Badge>
                                            ) : (
                                                <Button 
                                                    variant="primary" 
                                                    size="sm" 
                                                    disabled={!activeRun}
                                                    onClick={() => {
                                                        setSelectedLoan(loan);
                                                        setAmount(loan.amount_due.toString());
                                                    }}
                                                >
                                                    Record
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>

            {/* Active Run (Right Panel) */}
            <div className="w-full md:w-1/3 bg-slate-50 p-6 flex flex-col h-full border-l border-slate-200">
                <h2 className="text-lg font-bold text-slate-800 mb-4">Active Run Envelope</h2>
                
                {!activeRun ? (
                    <div className="flex-1 flex flex-col items-center justify-center text-center p-6 bg-white rounded-lg border border-slate-200">
                        <div className="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl mb-4">
                            <i className="fa-solid fa-envelope-open-text"></i>
                        </div>
                        <h3 className="font-semibold text-slate-800 mb-2">No Active Run</h3>
                        <p className="text-sm text-slate-500 mb-4">Start a new collection run envelope to record payments today.</p>
                        <Button variant="primary" onClick={startRun}>Start Run</Button>
                    </div>
                ) : (
                    <div className="flex-1 flex flex-col h-full">
                        <div className="bg-white p-4 rounded-lg border border-slate-200 mb-4">
                            <div className="text-sm text-slate-500">Run Number</div>
                            <div className="font-mono font-bold text-slate-800">{activeRun.run_number}</div>
                            
                            <div className="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-slate-100">
                                <div>
                                    <div className="text-xs text-slate-500">Items</div>
                                    <div className="font-semibold text-lg">{activeRun.items?.length || 0}</div>
                                </div>
                                <div>
                                    <div className="text-xs text-slate-500">Total Cash</div>
                                    <div className="font-semibold text-lg text-emerald-600">₱{parseFloat(activeRun.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                </div>
                            </div>
                        </div>

                        <div className="flex-1 overflow-y-auto mb-4 bg-white rounded-lg border border-slate-200">
                            {activeRun.items?.length === 0 ? (
                                <div className="p-4 text-center text-sm text-slate-500 italic mt-8">
                                    Envelope is empty.<br/>Record collections from the route list.
                                </div>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {activeRun.items?.map((item: any) => (
                                        <li key={item.id} className="p-3 hover:bg-slate-50 flex justify-between items-center">
                                            <div>
                                                <div className="text-sm font-semibold">{item.member?.personal_data?.first_name} {item.member?.personal_data?.last_name}</div>
                                                <div className="text-xs text-slate-500">{item.loan?.loan_number}</div>
                                            </div>
                                            <div className="font-semibold text-emerald-600 text-right">
                                                ₱{parseFloat(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                            </div>
                                            <div className="flex gap-1">
                                                <Button variant="secondary" size="sm" onClick={() => editItem(item)}>Edit</Button>
                                                <Button variant="secondary" size="sm" onClick={() => removeItem(item)}>Remove</Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <Button 
                            variant="primary" 
                            className="w-full" 
                            onClick={submitRun}
                            disabled={activeRun.items?.length === 0}
                        >
                            <i className="fa-solid fa-paper-plane mr-2"></i> Submit Envelope
                        </Button>
                    </div>
                )}
            </div>

            {/* Record Modal */}
            <Modal
                open={!!selectedLoan || !!editingItem}
                onClose={handleCloseModal}
                title={editingItem ? 'Edit Collection' : 'Record Collection'}
                header={<ModalHeader title={editingItem ? 'Edit Collection' : 'Record Collection'} onClose={handleCloseModal} />}
            >
                <div className="p-6">
                    <div className="bg-slate-50 p-4 rounded-lg mb-6">
                        <div className="font-semibold text-slate-800">{selectedLoan?.member_name || `${editingItem?.member?.personal_data?.first_name || ''} ${editingItem?.member?.personal_data?.last_name || ''}`}</div>
                        <div className="text-sm text-slate-500">Loan: {selectedLoan?.loan_number || editingItem?.loan?.loan_number}</div>
                        {selectedLoan && <div className="text-sm text-slate-500 font-medium mt-1">Expected: ₱{selectedLoan.amount_due.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>}
                    </div>

                    <form onSubmit={handleAddItem}>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Amount Collected (₱)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    required
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    value={amount}
                                    onChange={e => { setAmount(e.target.value); markDirty(); }}
                                />
                            </div>
                            
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Note (Optional)</label>
                                <textarea
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    rows={2}
                                    value={note}
                                    onChange={e => { setNote(e.target.value); markDirty(); }}
                                    placeholder="e.g. Paid in full, short by 100"
                                />
                            </div>

                            <label className="flex items-center space-x-2">
                                <input
                                    type="checkbox"
                                    className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    checked={visited}
                                    onChange={e => setVisited(e.target.checked)}
                                />
                                <span className="text-sm text-slate-700">Member was physically visited</span>
                            </label>
                        </div>

                        <div className="mt-6 flex justify-end space-x-3">
                            <Button variant="secondary" onClick={handleCloseModal}>Cancel</Button>
                            <Button variant="primary" type="submit" disabled={isSubmitting}>
                                {isSubmitting ? 'Saving...' : editingItem ? 'Save Changes' : 'Save Record'}
                            </Button>
                        </div>
                    </form>
                </div>
            </Modal>
            
            {renderDialog()}
        </div>
    );
}
