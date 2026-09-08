import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import DatePicker from '../../components/DatePicker';

export default function MemberExportModal({ onClose }: { onClose: () => void }) {
    const { addToast } = useToast();
    const [exportType, setExportType] = useState<'pdf' | 'excel'>('pdf');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const today = new Date();
    const todayISO = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    const exportMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post('/api/members/export', { export_type: exportType, start_date: startDate, end_date: endDate }, { responseType: 'blob' });
            const disposition = res.headers['content-disposition'] || '';
            const match = disposition.match(/filename="?([^";]+)"?/i);
            const filename = match ? match[1] : `members-${startDate}-to-${endDate}.${exportType === 'pdf' ? 'pdf' : 'xls'}`;
            const url = window.URL.createObjectURL(new Blob([res.data]));
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        },
        onSuccess: () => addToast('Export downloaded', 'success'),
        onError: (err: any) => {
            if (err.response?.data instanceof Blob) {
                err.response.data.text().then((text: string) => {
                    try {
                        const parsed = JSON.parse(text);
                        addToast(parsed.message || 'Export failed', 'error');
                    } catch {
                        addToast('Export failed', 'error');
                    }
                });
            } else {
                addToast(err.response?.data?.message || 'Export failed', 'error');
            }
        },
    });

    const valid = startDate !== '' && endDate !== '' && startDate <= endDate;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={onClose}>
            <div className="w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Export Members</h2>
                        <button onClick={onClose} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white"><i className="fas fa-times"></i></button>
                    </div>
                    <p className="mt-1 text-sm text-white/80">Download member records within a date range</p>
                </div>
                <div className="p-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Export format</label>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                onClick={() => setExportType('pdf')}
                                className={`inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition-colors ${exportType === 'pdf' ? 'border-red-300 bg-red-50 text-red-700' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'}`}
                            >
                                <i className="fas fa-file-pdf text-red-500"></i> PDF
                            </button>
                            <button
                                type="button"
                                onClick={() => setExportType('excel')}
                                className={`inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition-colors ${exportType === 'excel' ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'}`}
                            >
                                <i className="fas fa-file-excel text-green-600"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">Start date</label>
                            <DatePicker value={startDate} onChange={setStartDate} maxDate={endDate || todayISO} clearable={false} placeholder="Pick start" className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">End date</label>
                            <DatePicker value={endDate} onChange={setEndDate} minDate={startDate} maxDate={todayISO} clearable={false} placeholder="Pick end" className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200" />
                        </div>
                    </div>
                    {startDate !== '' && endDate !== '' && startDate > endDate && (
                        <p className="text-xs text-red-600">End date must be on or after the start date.</p>
                    )}
                </div>
                <div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button onClick={onClose} className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button
                        onClick={() => exportMutation.mutate()}
                        disabled={!valid || exportMutation.isPending}
                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {exportMutation.isPending ? <><i className="fas fa-spinner fa-spin mr-2"></i>Exporting...</> : <><i className="fas fa-download mr-2"></i>Download Export</>}
                    </button>
                </div>
            </div>
        </div>
    );
}