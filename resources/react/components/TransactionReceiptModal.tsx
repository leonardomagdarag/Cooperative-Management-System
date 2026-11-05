import Modal from './ui/Modal';
import Button from './ui/Button';

export interface TransactionReceiptData {
    id: number;
    reason: 'reprint' | 'reissue';
    certified: boolean;
    note: string | null;
    issued_at: string;
    issued_by: string | null;
    cooperative: string;
    member: {
        name: string | null;
        member_number: string | null;
        account_number: string | null;
    };
    transaction: {
        id: number;
        transaction_number: string;
        type: string;
        amount: string;
        fee_amount: string;
        total: string;
        date: string | null;
        status: string;
        branch: string | null;
        loan_number: string | null;
        note: string | null;
    };
}

interface TransactionReceiptModalProps {
    receipt: TransactionReceiptData | null;
    onClose: () => void;
}

const typeLabels: Record<string, string> = {
    deposit: 'Deposit',
    withdrawal: 'Withdrawal',
    loan_payment: 'Loan Payment',
    loan_disbursement: 'Loan Disbursement',
    fee: 'Fee',
    interest: 'Interest Credit',
};

const peso = (amount: string | number) =>
    `₱${Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatDate = (value: string | null) => {
    if (!value) return '—';
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatTimestamp = (value: string) => {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleString(undefined, { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' });
};

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-b border-dashed border-slate-200 py-1.5">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className="text-sm font-medium text-slate-800">{value}</p>
        </div>
    );
}

export default function TransactionReceiptModal({ receipt, onClose }: TransactionReceiptModalProps) {
    if (!receipt) return null;

    const watermark = receipt.certified ? 'CERTIFIED REPLACEMENT' : 'DUPLICATE';
    const amountInWordsHint = peso(receipt.transaction.amount);

    return (
        <Modal
            open={!!receipt}
            onClose={onClose}
            title="Transaction Receipt"
            size="lg"
            header={
                <header className="ui-modal-header no-print shrink-0">
                    <h2 className="ui-modal-title">Transaction Receipt</h2>
                    <Button variant="ghost" size="sm" onClick={onClose} aria-label="Close dialog" className="ui-modal-close">
                        <i className="fas fa-times" />
                    </Button>
                </header>
            }
            footer={
                <div className="no-print flex w-full items-center justify-between gap-3">
                    <p className="text-xs text-slate-500">
                        Receipt #{receipt.id} recorded in the ledger audit trail.
                    </p>
                    <div className="flex gap-2">
                        <Button variant="secondary" onClick={onClose}>Close</Button>
                        <Button onClick={() => window.print()}>
                            <i className="fas fa-print" /> Print receipt
                        </Button>
                    </div>
                </div>
            }
        >
            <div className="receipt-print-area relative overflow-hidden rounded-lg border border-slate-200 bg-white px-6 py-5">
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <span className="-rotate-[22deg] select-none whitespace-nowrap text-4xl font-extrabold tracking-[0.2em] text-slate-900 opacity-[0.06] sm:text-5xl">
                        {watermark}
                    </span>
                </div>

                <div className="relative flex items-start justify-between gap-4 border-b-2 border-slate-800 pb-3">
                    <div className="flex items-center gap-3">
                        <img src="/images/Logo.png" alt="" className="h-11 w-11 object-contain" />
                        <div>
                            <p className="text-base font-extrabold uppercase tracking-wide text-slate-900">{receipt.cooperative}</p>
                            <p className="text-xs text-slate-500">Official transaction receipt</p>
                        </div>
                    </div>
                    <div className="text-right text-xs text-slate-500">
                        <p>Receipt #{receipt.id}</p>
                        <p>{formatTimestamp(receipt.issued_at)}</p>
                    </div>
                </div>

                <div className="relative mt-3 flex items-center gap-2">
                    <span className={`ui-badge ${receipt.certified ? 'ui-badge-warning' : 'ui-badge-info'}`}>{watermark}</span>
                    <span className="text-xs text-slate-500">
                        {receipt.certified
                            ? 'Certified true copy replacing the original receipt.'
                            : 'Additional copy of a receipt already issued.'}
                    </span>
                </div>

                <div className="relative mt-3 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                    <DetailRow label="Transaction number" value={receipt.transaction.transaction_number} />
                    <DetailRow label="Date" value={formatDate(receipt.transaction.date)} />
                    <DetailRow label="Transaction type" value={typeLabels[receipt.transaction.type] || receipt.transaction.type.replace(/_/g, ' ')} />
                    <DetailRow label="Status" value={receipt.transaction.status} />
                    <DetailRow label="Member" value={receipt.member.name || '—'} />
                    <DetailRow label="Member number" value={receipt.member.member_number || '—'} />
                    <DetailRow label="Account number" value={receipt.member.account_number || (receipt.transaction.loan_number ? `Loan ${receipt.transaction.loan_number}` : '—')} />
                    <DetailRow label="Branch" value={receipt.transaction.branch || '—'} />
                </div>

                <div className="relative mt-3 flex items-center justify-between rounded-lg bg-slate-900 px-4 py-3 text-white">
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-300">Amount</p>
                        <p className="text-xl font-bold">{amountInWordsHint}</p>
                        {Number(receipt.transaction.fee_amount) > 0 && <>
                            <p className="mt-2 text-[10px] font-semibold uppercase tracking-wide text-amber-200">Fee</p>
                            <p className="text-sm font-semibold">{peso(receipt.transaction.fee_amount)}</p>
                            <p className="mt-2 text-[10px] font-semibold uppercase tracking-wide text-slate-300">{receipt.transaction.type === 'loan_disbursement' ? 'Net disbursed' : 'Total'}</p>
                            <p className="text-lg font-bold">{peso(receipt.transaction.total)}</p>
                        </>}
                    </div>
                    {receipt.transaction.loan_number && (
                        <div className="text-right">
                            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-300">Loan number</p>
                            <p className="text-sm font-semibold">{receipt.transaction.loan_number}</p>
                        </div>
                    )}
                </div>

                {(receipt.note || receipt.transaction.note) && (
                    <div className="relative mt-3 space-y-2">
                        {receipt.note && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                <p className="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Reason for replacement</p>
                                <p className="text-sm text-amber-900">{receipt.note}</p>
                            </div>
                        )}
                        {receipt.transaction.note && (
                            <div>
                                <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Transaction note</p>
                                <p className="text-sm text-slate-600">{receipt.transaction.note}</p>
                            </div>
                        )}
                    </div>
                )}

                <div className="relative mt-4 border-t border-slate-200 pt-3">
                    <div className="flex items-end justify-between gap-6">
                        <div className="text-xs text-slate-500">
                            <p>
                                Issued to <span className="font-semibold text-slate-700">{receipt.member.name || 'the member of record'}</span>
                                {receipt.member.member_number ? ` (${receipt.member.member_number})` : ''}.
                            </p>
                            <p className="mt-1">
                                System-generated copy issued from the cooperative ledger. Valid without signature when the receipt number matches the audit trail.
                            </p>
                        </div>
                        <div className="min-w-[10rem] text-center">
                            <div className="h-10 border-b border-slate-400" />
                            <p className="mt-1 text-xs font-semibold text-slate-700">{receipt.issued_by || 'Cooperative officer'}</p>
                            <p className="text-[10px] uppercase tracking-wide text-slate-400">Issued by</p>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
