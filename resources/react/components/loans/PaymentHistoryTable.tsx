import Badge from '../ui/Badge';
import { formatPeso } from '../../lib/format';

export interface PaymentRecord {
    id: number;
    payment_number?: number;
    transaction_number?: string | null;
    amount: number | string;
    payment_date?: string | null;
    status?: string | null;
    /** Reversal entries net against the original payment. */
    is_reversal?: boolean;
}

const STATUS_META: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'neutral' }> = {
    completed: { label: 'Completed', variant: 'success' },
    pending: { label: 'Pending', variant: 'warning' },
    rejected: { label: 'Rejected', variant: 'danger' },
};

interface PaymentHistoryTableProps {
    payments: PaymentRecord[] | null | undefined;
    /** Smaller type/padding, used by the officer loan modal. */
    compact?: boolean;
}

/** Recorded payments with a status-aware badge and a net completed total. */
export default function PaymentHistoryTable({ payments, compact = false }: PaymentHistoryTableProps) {
    const rows = payments ?? [];
    const pad = compact ? 'px-3 py-1.5 text-xs' : 'px-4 py-2';
    const head = compact ? 'px-3 py-2' : 'px-4 py-2';

    if (rows.length === 0) {
        return <div className="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">No payments recorded yet</div>;
    }

    const totalPaid = rows.reduce((sum, row) => {
        if ((row.status ?? 'completed') !== 'completed') return sum;
        const amount = Number(row.amount) || 0;
        return row.is_reversal ? sum - amount : sum + amount;
    }, 0);

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto">
                <table className="min-w-[560px] w-full text-sm">
                    <thead className="bg-gray-50">
                        <tr className="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                            <th className={head}>#</th>
                            <th className={head}>Transaction #</th>
                            <th className={`${head} text-right`}>Amount</th>
                            <th className={head}>Date</th>
                            <th className={head}>Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.map((payment, index) => {
                            const reversal = !!payment.is_reversal;
                            const meta = reversal
                                ? { label: 'Reversal', variant: 'neutral' as const }
                                : STATUS_META[payment.status ?? 'completed'] ?? { label: String(payment.status ?? 'Recorded'), variant: 'neutral' as const };
                            return (
                                <tr key={payment.id} className={`hover:bg-gray-50 ${reversal ? 'text-gray-400' : ''}`}>
                                    <td className={pad}>{payment.payment_number ?? index + 1}</td>
                                    <td className={`${pad} max-w-[220px] truncate font-mono text-xs`} title={payment.transaction_number ?? undefined}>{payment.transaction_number || '—'}</td>
                                    <td className={`${pad} text-right font-medium ${reversal ? 'text-red-500' : 'text-gray-900'}`}>{reversal ? `−${formatPeso(payment.amount)}` : formatPeso(payment.amount)}</td>
                                    <td className={`${pad} text-gray-500`}>{payment.payment_date || '—'}</td>
                                    <td className={pad}><Badge variant={meta.variant}>{meta.label}</Badge></td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <p className="text-xs text-gray-500">
                Total completed payments: <span className="font-semibold text-gray-700">{formatPeso(totalPaid)}</span>
            </p>
        </div>
    );
}
