import { useState } from 'react';

interface DeclineApplicationModalProps {
    member: any;
    onConfirm: (reason: string) => void;
    onClose: () => void;
    submitting?: boolean;
}

export default function DeclineApplicationModal({ member, onConfirm, onClose, submitting }: DeclineApplicationModalProps) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');

    const submit = () => {
        if (!reason.trim()) {
            setError('Please provide a reason for declining the application');
            return;
        }
        onConfirm(reason.trim());
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-red-600 to-rose-600 px-6 py-5 text-white">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Decline Membership Application</h2>
                        <button onClick={onClose} className="text-white/70 hover:text-white"><i className="fas fa-times"></i></button>
                    </div>
                    <p className="mt-1 text-sm text-white/80">This will notify <strong>{member.name || member.email}</strong> that their application is declined.</p>
                </div>
                <div className="p-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Reason <span className="text-red-500">*</span></label>
                        <textarea
                            value={reason}
                            onChange={e => { setReason(e.target.value); setError(''); }}
                            rows={4}
                            maxLength={500}
                            placeholder="Explain why the application is declined (e.g., incomplete documents, disqualified income, etc.)"
                            className={`w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 ${error ? 'border-red-400 focus:border-red-500 focus:ring-red-200' : 'border-gray-300 focus:border-cyan-500 focus:ring-cyan-200'}`}
                        />
                        {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
                        <p className={`mt-1 text-xs ${reason.length >= 500 ? 'text-red-600' : 'text-gray-400'}`}>{reason.length}/500</p>
                    </div>
                    <div className="flex items-start gap-3 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700">
                        <i className="fas fa-info-circle mt-0.5"></i>
                        <span>The applicant's membership record will be archived. An officer can create a new record with the same email after a future office application.</span>
                    </div>
                </div>
                <div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button onClick={onClose} className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button
                        onClick={submit}
                        disabled={submitting || !reason.trim()}
                        className="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {submitting ? <><i className="fas fa-spinner fa-spin mr-2"></i>Declining...</> : <><i className="fas fa-times mr-2"></i>Decline Application</>}
                    </button>
                </div>
            </div>
        </div>
    );
}
