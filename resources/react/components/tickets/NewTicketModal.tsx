import { useState, useRef } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { createTicket, type TicketDetail } from '../../api/tickets';
import { useToast } from '../../contexts/ToastContext';
import Modal from '../ui/Modal';
import ModalHeader from '../ui/ModalHeader';
import Button from '../ui/Button';

interface NewTicketModalProps {
    open: boolean;
    onClose: () => void;
    onSuccess?: (ticket: TicketDetail) => void;
}

const CATEGORIES = [
    { value: 'membership', label: 'Membership & Profile' },
    { value: 'savings_deposits', label: 'Savings & Deposits' },
    { value: 'withdrawals', label: 'Withdrawals & OTP' },
    { value: 'loans', label: 'Loans & Borrowing' },
    { value: 'repayments', label: 'Loan Repayments' },
    { value: 'account_security', label: 'Account & Security' },
    { value: 'technical_issue', label: 'Bug / Technical Issue' },
    { value: 'other', label: 'General Inquiry / Other' },
];

const PRIORITIES = [
    { value: 'low', label: 'Low - General question or minor request', color: 'text-slate-600' },
    { value: 'medium', label: 'Medium - Standard inquiry or processing question', color: 'text-blue-600' },
    { value: 'high', label: 'High - Service blocked or urgent transaction', color: 'text-amber-600' },
    { value: 'urgent', label: 'Urgent - Critical issue or security concern', color: 'text-rose-600' },
];

export default function NewTicketModal({ open, onClose, onSuccess }: NewTicketModalProps) {
    const { addToast } = useToast();
    const queryClient = useQueryClient();
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [subject, setSubject] = useState('');
    const [category, setCategory] = useState('loans');
    const [priority, setPriority] = useState<'low' | 'medium' | 'high' | 'urgent'>('medium');
    const [message, setMessage] = useState('');
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [previews, setPreviews] = useState<string[]>([]);
    const [validationError, setValidationError] = useState('');

    const mutation = useMutation({
        mutationFn: async () => {
            const fd = new FormData();
            fd.append('subject', subject.trim());
            fd.append('category', category);
            fd.append('priority', priority);
            fd.append('message', message.trim());
            selectedFiles.forEach(file => {
                fd.append('attachments[]', file);
            });
            return createTicket(fd);
        },
        onSuccess: (res) => {
            addToast('Support ticket created successfully.', 'success');
            queryClient.invalidateQueries({ queryKey: ['tickets'] });
            resetForm();
            onClose();
            if (onSuccess && res.data) {
                onSuccess(res.data);
            }
        },
        onError: (err: any) => {
            const msg = err.response?.data?.message || 'Failed to create support ticket.';
            addToast(msg, 'error');
            setValidationError(msg);
        },
    });

    const resetForm = () => {
        setSubject('');
        setCategory('loans');
        setPriority('medium');
        setMessage('');
        setSelectedFiles([]);
        previews.forEach(url => URL.revokeObjectURL(url));
        setPreviews([]);
        setValidationError('');
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files) return;
        const newFiles = Array.from(e.target.files);
        if (selectedFiles.length + newFiles.length > 5) {
            addToast('You can attach a maximum of 5 images.', 'error');
            return;
        }

        const validImages: File[] = [];
        const newUrls: string[] = [];

        for (const file of newFiles) {
            if (!file.type.startsWith('image/')) {
                addToast(`${file.name} is not an image. Only images are supported.`, 'error');
                continue;
            }
            if (file.size > 5 * 1024 * 1024) {
                addToast(`${file.name} exceeds the 5MB size limit.`, 'error');
                continue;
            }
            validImages.push(file);
            newUrls.push(URL.createObjectURL(file));
        }

        setSelectedFiles(prev => [...prev, ...validImages]);
        setPreviews(prev => [...prev, ...newUrls]);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const removeFile = (index: number) => {
        URL.revokeObjectURL(previews[index]);
        setSelectedFiles(prev => prev.filter((_, i) => i !== index));
        setPreviews(prev => prev.filter((_, i) => i !== index));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationError('');

        if (!subject.trim()) {
            setValidationError('Please enter a brief subject.');
            return;
        }
        if (!message.trim()) {
            setValidationError('Please provide details for your support request.');
            return;
        }

        mutation.mutate();
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Submit Support Ticket"
            size="lg"
            header={
                <ModalHeader
                    title="Submit Support Ticket"
                    subtitle="Ask a question or report an issue to cooperative support staff"
                    icon="fas fa-ticket-alt"
                    onClose={onClose}
                />
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                {validationError && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                        {validationError}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1">
                            Topic / Category <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={category}
                            onChange={e => setCategory(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            {CATEGORIES.map(c => (
                                <option key={c.value} value={c.value}>{c.label}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1">
                            Priority <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={priority}
                            onChange={e => setPriority(e.target.value as any)}
                            className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            {PRIORITIES.map(p => (
                                <option key={p.value} value={p.value}>{p.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <div>
                    <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1">
                        Subject <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={subject}
                        onChange={e => setSubject(e.target.value)}
                        placeholder="E.g., OTP code not received during withdrawal"
                        maxLength={255}
                        required
                        className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    />
                </div>

                <div>
                    <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1">
                        Message Description <span className="text-red-500">*</span>
                    </label>
                    <textarea
                        value={message}
                        onChange={e => setMessage(e.target.value)}
                        rows={5}
                        placeholder="Please describe what happened, any error messages encountered, or details about your inquiry..."
                        required
                        className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    />
                </div>

                {/* Attachments Section */}
                <div>
                    <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1">
                        Image Attachments (Optional, max 5 images, up to 5MB each)
                    </label>
                    <div className="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={selectedFiles.length >= 5}
                            className="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 hover:border-emerald-500 disabled:opacity-50 transition-colors"
                        >
                            <i className="fas fa-image text-emerald-600 text-sm"></i>
                            <span>{selectedFiles.length >= 5 ? 'Max images reached' : 'Attach Screenshots / Images'}</span>
                        </button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            multiple
                            onChange={handleFileChange}
                            className="hidden"
                        />
                        <span className="text-xs text-gray-500">
                            {selectedFiles.length} / 5 attached
                        </span>
                    </div>

                    {previews.length > 0 && (
                        <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
                            {previews.map((src, index) => (
                                <div key={index} className="relative group rounded-lg overflow-hidden border border-gray-200 bg-gray-100 aspect-square shadow-xs">
                                    <img src={src} alt={`Preview ${index + 1}`} className="w-full h-full object-cover" />
                                    <button
                                        type="button"
                                        onClick={() => removeFile(index)}
                                        className="absolute top-1 right-1 h-6 w-6 rounded-full bg-red-600 text-white flex items-center justify-center opacity-80 group-hover:opacity-100 hover:bg-red-700 text-xs shadow-md transition-all"
                                        title="Remove attachment"
                                    >
                                        <i className="fas fa-times"></i>
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                    <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={mutation.isPending}>
                        Cancel
                    </Button>
                    <Button type="submit" variant="primary" size="sm" loading={mutation.isPending}>
                        <i className="fas fa-paper-plane mr-1.5"></i>
                        Submit Ticket
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
