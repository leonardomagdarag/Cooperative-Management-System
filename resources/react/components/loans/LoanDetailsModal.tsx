import { useState, type ReactNode } from 'react';
import { useLanguage } from '../../contexts/LanguageContext';
import { formatPeso } from '../../lib/format';
import type { ScheduleRow } from '../../lib/loanSchedule';
import HelpTip from '../ui/HelpTip';
import LoadingIndicator from '../ui/LoadingIndicator';
import Modal from '../ui/Modal';
import ModalTabs from '../ui/ModalTabs';
import AmortizationTable from './AmortizationTable';
import CoMakerList, { type CoMaker } from './CoMakerList';
import PaymentHistoryTable, { type PaymentRecord } from './PaymentHistoryTable';

type Amount = number | string;

export interface LoanDetails {
    id: number;
    loan_number: string;
    loan_type: string;
    loan_type_label?: string | null;
    loan_product_name?: string | null;
    loan_amount: Amount;
    principal_amount?: Amount | null;
    interest_rate: Amount;
    term_months: number;
    payment_frequency: string;
    loan_status: string;
    total_paid: Amount;
    schedule_paid?: Amount | null;
    remaining_balance: Amount;
    progress: number;
    estimated_interest: Amount;
    estimated_total_repayment: Amount;
    accrued_penalty?: Amount | null;
    amount_due?: Amount | null;
    penalty_amount?: Amount | null;
    penalty_type?: string | null;
    penalty_frequency?: string | null;
    interest_extension_penalty?: boolean;
    applied_at?: string | null;
    approved_at?: string | null;
    due_date?: string | null;
    approved_by?: string | null;
    group_members?: CoMaker[];
    payment_schedule?: ScheduleRow[] | null;
    payments?: PaymentRecord[] | null;
    documents?: { id: number; original_name: string; file_size?: number | null; url: string }[];
}

interface LoanDetailsModalProps {
    loan?: LoanDetails;
    onClose: () => void;
    loadFailed?: boolean;
    borrowerName?: string;
    borrowerNumber?: string | null;
    reviewerName?: string;
    nextApprover?: string;
    onUpload?: (file: File) => void;
    uploading?: boolean;
}

const LOAN_STATUS_STYLES: Record<string, { label: string; className: string }> = {
    active: { label: 'Active', className: 'bg-emerald-100 text-emerald-700' },
    approved: { label: 'Approved', className: 'bg-emerald-100 text-emerald-700' },
    pending: { label: 'Pending', className: 'bg-amber-100 text-amber-700' },
    paid: { label: 'Paid', className: 'bg-blue-100 text-blue-700' },
    paid_off: { label: 'Paid Off', className: 'bg-blue-100 text-blue-700' },
    rejected: { label: 'Rejected', className: 'bg-red-100 text-red-700' },
};

export function loanStatusMeta(status: string) {
    return LOAN_STATUS_STYLES[status] ?? { label: status, className: 'bg-gray-100 text-gray-600' };
}

function DetailRow({ label, children, hint }: { label: string; children: ReactNode; hint?: string }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2">
            <dt className="flex items-center gap-1 text-gray-600">{label}{hint && <HelpTip text={hint} />}</dt>
            <dd className="min-w-0 text-right font-medium wrap-break-word text-gray-900">{children}</dd>
        </div>
    );
}

export default function LoanDetailsModal({
    loan, onClose, loadFailed, borrowerName, borrowerNumber, reviewerName, nextApprover, onUpload, uploading,
}: LoanDetailsModalProps) {
    const { t } = useLanguage();
    const [tab, setTab] = useState('overview');
    const title = t('Loan Details');
    const loanType = loan?.loan_product_name || loan?.loan_type_label || loan?.loan_type;
    const subtitle = [borrowerName || loanType, borrowerNumber].filter(Boolean).join(' ? ');
    const schedulePaid = Number(loan?.schedule_paid ?? loan?.total_paid) || 0;
    const penaltiesPaid = Number(loan?.total_paid) - schedulePaid;
    const progress = Number(loan?.progress) || 0;
    const tabs = [
        { key: 'overview', label: t('Overview'), icon: 'fas fa-info-circle' },
        { key: 'schedule', label: t('Payment Schedule'), icon: 'fas fa-table' },
        { key: 'history', label: t('Payment History'), icon: 'fas fa-history' },
        { key: 'documents', label: t('Documents'), icon: 'fas fa-file-alt' },
        { key: 'co-makers', label: t('Co-makers'), icon: 'fas fa-users' },
    ];

    return (
        <Modal
            open fixed size="xl" title={title} onClose={onClose}
            header={
                <>
                    <header className="flex shrink-0 items-center justify-between gap-4 bg-linear-to-r from-emerald-600 to-cyan-600 px-6 py-4 text-white">
                        <div>
                            <h2 id="ui-modal-title" className="text-lg font-bold">{title}{loan && ` — ${loan.loan_number}`}</h2>
                            <p className="text-sm text-white/90">{[borrowerName || loanType, borrowerNumber].filter(Boolean).join(' · ')}</p>
                        </div>
                        <button type="button" onClick={onClose} aria-label={t('Close loan details')} className="shrink-0 cursor-pointer rounded-full p-2 text-white hover:bg-white/20">
                            <i className="fas fa-times" aria-hidden="true" />
                        </button>
                    </header>
                    {loan && <ModalTabs tabs={tabs} activeTab={tab} onChange={setTab} />}
                </>
            }
        >
            {!loan && (loadFailed
                ? <p role="alert" className="text-sm text-red-700">{t('Unable to load loan details.')}</p>
                : <LoadingIndicator message={t('Loading loan details...')} />)}

            {loan && tab === 'overview' && (
                <div className="space-y-4">
                    <section className="rounded-xl bg-gray-50 p-4 sm:p-5">
                        <h3 className="mb-3 flex items-center gap-2 font-semibold text-gray-900">
                            {t('How this loan breaks down')}
                        </h3>
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                            {[
                                { label: t('Principal'), amount: loan.principal_amount ?? loan.loan_amount },
                                { label: t('Estimated Interest'), amount: loan.estimated_interest },
                                { label: t('Estimated Total Repayment'), amount: loan.estimated_total_repayment },
                            ].map(({ label, amount }) => (
                                <div key={label} className="rounded-lg border border-gray-200 bg-white p-3">
                                    <p className="text-xs font-medium text-gray-600">{label}</p>
                                    <p className="mt-1 text-base font-semibold wrap-break-word tabular-nums text-gray-900">{formatPeso(amount)}</p>
                                </div>
                            ))}
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <p className="flex items-center gap-1 text-xs font-medium text-amber-900">
                                    {t('Accrued Penalties')} <HelpTip text="Current unpaid overdue charges based on the loan’s penalty policy." />
                                </p>
                                <p className="mt-1 text-base font-semibold wrap-break-word tabular-nums text-amber-900">{formatPeso(loan.accrued_penalty)}</p>
                            </div>
                        </div>
                        <div className="mt-4">
                            <div className="mb-1.5 flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs text-gray-600">
                                <span>
                                    {formatPeso(schedulePaid)} {t('paid')}
                                    {penaltiesPaid > 0.005 && <span className="ml-1 text-amber-800">(+{formatPeso(penaltiesPaid)} {t('in penalties paid')})</span>}
                                </span>
                                <span>{formatPeso(loan.remaining_balance)} {t('remaining')}</span>
                            </div>
                            <div className="h-2.5 overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label={t('Repayment progress')} aria-valuenow={Math.min(100, Math.max(0, progress))}>
                                <div className="h-full rounded-full bg-emerald-600" style={{ width: `${Math.min(100, Math.max(0, progress))}%` }} />
                            </div>
                            <p className="mt-1 text-right text-xs font-medium text-emerald-700">{progress.toFixed(1)}% {t('paid')}</p>
                        </div>
                    </section>

                    <dl className="space-y-2 text-sm">
                        {borrowerName && <DetailRow label={t('Borrower')}>{borrowerName}</DetailRow>}
                        <DetailRow label={t('Type')}>{loanType}</DetailRow>
                        <DetailRow label={t('Loan Amount')}>{formatPeso(loan.loan_amount)}</DetailRow>
                        <DetailRow label={t('Total Paid')}>{formatPeso(loan.total_paid)}</DetailRow>
                        <DetailRow label={t('Interest Rate')} hint="Monthly flat rate applied over the loan term.">{Number(loan.interest_rate)}% / mo (Flat)</DetailRow>
                        <DetailRow label={t('Term')}>{loan.term_months} {t('months')}</DetailRow>
                        <DetailRow label={t('Payment Frequency')}><span className="capitalize">{loan.payment_frequency}</span></DetailRow>
                        <DetailRow label={t('Overdue Penalty')}>
                            {Number(loan.penalty_amount) > 0
                                ? `${loan.penalty_type === 'percent' ? `${loan.penalty_amount}%` : formatPeso(loan.penalty_amount)} · ${loan.penalty_frequency === 'per_due' ? t('per overdue installment') : loan.penalty_frequency}`
                                : t('None')}
                        </DetailRow>
                        {loan.interest_extension_penalty !== undefined && (
                            <DetailRow label={t('Post-term interest amortization')} hint="When enabled, interest-only installments are added after the final due date while scheduled amounts remain unpaid.">
                                {t(loan.interest_extension_penalty ? 'Enabled' : 'Disabled')}
                            </DetailRow>
                        )}
                        <DetailRow label={t('Amount Due Now')} hint="Unpaid scheduled installments plus accrued overdue penalties.">{formatPeso(loan.amount_due)}</DetailRow>
                        <DetailRow label={t('Status')}>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${loanStatusMeta(loan.loan_status).className}`}>{t(loanStatusMeta(loan.loan_status).label)}</span>
                        </DetailRow>
                        <DetailRow label={t('Applied')}>{loan.applied_at || '—'}</DetailRow>
                        <DetailRow label={t('Approved')}>{loan.approved_at || '—'}</DetailRow>
                        <DetailRow label={t('Due Date')} hint="The final scheduled date for repaying the loan.">{loan.due_date || '—'}</DetailRow>
                        {loan.approved_by && <DetailRow label={t('Approved By')}>{loan.approved_by}</DetailRow>}
                        {reviewerName && <DetailRow label={t('Reviewed / Endorsed By')}>{reviewerName}</DetailRow>}
                        {nextApprover && <DetailRow label={t('Next Approver')}>{nextApprover}</DetailRow>}
                    </dl>
                </div>
            )}

            {loan && tab === 'schedule' && (
                <div className="space-y-4">
                    {loan.interest_extension_penalty && (
                        <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                            {t('Rows marked “Interest only” show interest charged after the original loan term.')}
                        </p>
                    )}
                    <p className="flex flex-wrap items-center gap-1 text-sm text-gray-600">
                        {t('Each row is one installment.')} <span className="inline-flex items-center gap-1">{t('Payment')} <HelpTip text="The amount due for this period, made up of principal and interest." /></span>
                        = <span className="inline-flex items-center gap-1">{t('Principal')} <HelpTip text="The part of the payment that reduces the amount borrowed." /></span>
                        + <span className="inline-flex items-center gap-1">{t('Interest')} <HelpTip text="The part of the payment that covers the cost of borrowing." /></span>.
                    </p>
                    <AmortizationTable schedule={loan.payment_schedule} totalPaid={schedulePaid} />
                </div>
            )}

            {loan && tab === 'history' && (
                <div className="space-y-3">
                    <p className="flex items-center gap-1 text-sm text-gray-600">
                        {t('Payments recorded against this loan.')} <HelpTip text="Only completed payments reduce the balance. Pending payments still need approval." />
                    </p>
                    <PaymentHistoryTable payments={loan.payments} />
                </div>
            )}

            {loan && tab === 'documents' && (
                <div className="space-y-3">
                    {onUpload && (
                        <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                            <label className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded focus-within:ring-2 focus-within:ring-emerald-600">
                                <i className="fas fa-cloud-upload-alt text-2xl text-gray-500" aria-hidden="true" />
                                <span className="text-sm font-medium text-gray-700">{t('Upload a supporting document')}</span>
                                <span className="text-xs text-gray-600">JPG, PNG, GIF, WebP or PDF &middot; max 10 MB</span>
                                <input
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                                    className="sr-only"
                                    disabled={uploading}
                                    onChange={event => {
                                        const file = event.currentTarget.files?.[0];
                                        if (file) onUpload(file);
                                        event.currentTarget.value = '';
                                    }}
                                />
                            </label>
                            {uploading && <p role="status" className="mt-2 text-center text-xs text-gray-600">{t('Uploading...')}</p>}
                        </div>
                    )}
                    {loan.documents?.length ? loan.documents.map(document => (
                        <div key={document.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">
                            <div className="flex min-w-0 items-center gap-3">
                                <i className="fas fa-file-alt text-gray-400" aria-hidden="true" />
                                <div className="min-w-0">
                                    <p className="wrap-break-word text-sm font-medium text-gray-800">{document.original_name}</p>
                                    {document.file_size != null && <p className="text-xs text-gray-500">{(document.file_size / 1024).toFixed(1)} KB</p>}
                                </div>
                            </div>
                            <div className="flex gap-3 text-sm font-medium">
                                <a href={document.url} target="_blank" rel="noopener noreferrer" className="text-emerald-700 hover:underline" aria-label={`${t('View')} ${document.original_name}`}>{t('View')}</a>
                                <a href={`${document.url}?disposition=attachment`} className="text-emerald-700 hover:underline" aria-label={`${t('Download')} ${document.original_name}`}>{t('Download')}</a>
                            </div>
                        </div>
                    )) : <p className="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">{t('No documents have been added to this loan yet.')}</p>}
                </div>
            )}

            {loan && tab === 'co-makers' && <CoMakerList members={loan.group_members} />}
        </Modal>
    );
}
