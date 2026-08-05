import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function OfficersPage() {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [viewing, setViewing] = useState<any>(null);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading, refetch } = useQuery({
        queryKey: ['officers', search, page],
        queryFn: () => api.get('/api/members', { params: { is_officer: true, search: search || undefined, page, per_page: 15 } }),
    });

    const officers = data?.data?.data ?? [];
    const pagination = data?.data?.pagination ?? {};

    return (
        <div>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div className="w-full mb-1">
                    <div className="mb-4">
                        <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Officers</h1>
                        <p className="text-sm text-gray-500">Manage cooperative officers and their permissions</p>
                    </div>

                    <div className="sm:flex">
                        <div className="items-center hidden mb-3 sm:flex sm:mb-0">
                            <div className="relative mt-1 lg:w-64 xl:w-96">
                                <input type="text" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Search officers..." />
                            </div>
                        </div>
                        <div className="flex items-center ml-auto space-x-2 sm:space-x-3">
                            <button type="button" onClick={() => refetch()} className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                            <button className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                <svg className="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>Add Officer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex flex-col">
                <div className="overflow-x-auto">
                    <div className="inline-block min-w-full align-middle">
                        <div className="overflow-hidden shadow">
                            {isLoading ? (
                                <div className="bg-white p-8 text-center text-sm text-gray-500">Loading...</div>
                            ) : officers.length === 0 ? (
                                <div className="bg-white p-16 text-center">
                                    <svg className="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M9 20H2v-2a3 3 0 015.356-1.857M13 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <p className="text-sm text-gray-500 mt-4">No officers found</p>
                                </div>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th scope="col" className="p-4 w-10"><input type="checkbox" className="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300" /></th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Name</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Email</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {officers.map((o: any) => (
                                            <tr key={o.id} className="hover:bg-gray-50">
                                                <td className="p-4 w-10"><input type="checkbox" className="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300" /></td>
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">{o.name || 'Unknown'}</td>
                                                <td className="p-4 text-sm text-gray-500 whitespace-nowrap capitalize">{o.primary_role || 'Officer'}</td>
                                                <td className="p-4 whitespace-nowrap"><span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${o.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{o.account_status}</span></td>
                                                <td className="p-4 text-sm text-gray-500">{o.email || '—'}</td>
                                                <td className="p-4 whitespace-nowrap">
                                                    <div className="flex items-center gap-1">
                                                        <button onClick={() => setViewing(o)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><i className="fas fa-eye text-sm"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {pagination.last_page > 1 && (
                <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-50">Previous</button>
                    <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-50">Next</button>
                </div>
            )}

            {viewing && <OfficerDetailModal officer={viewing} onClose={() => setViewing(null)} />}
        </div>
    );
}

function OfficerDetailModal({ officer, onClose }: { officer: any; onClose: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Officer Details</h2>
                        <button onClick={onClose} className="text-white/70 hover:text-white"><i className="fas fa-times"></i></button>
                    </div>
                </div>
                <div className="p-6 space-y-4">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-100 text-xl font-bold text-indigo-600">{officer.name?.charAt(0) || '?'}</div>
                        <div>
                            <p className="font-semibold text-lg text-gray-900">{officer.name || 'Unknown'}</p>
                            <p className="text-sm text-gray-500">{officer.email} · {officer.user_number}</p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <InfoRow label="Primary Role" value={officer.primary_role} />
                        <InfoRow label="Account Status" value={officer.account_status} />
                        <InfoRow label="Is Officer" value={officer.is_officer ? 'Yes' : 'No'} />
                    </div>
                </div>
            </div>
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: any }) {
    return (
        <div><p className="text-xs text-gray-500">{label}</p><p className="text-sm font-medium text-gray-900 capitalize">{value || '—'}</p></div>
    );
}
