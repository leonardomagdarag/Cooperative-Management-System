import Badge from '../ui/Badge';
import { formatPeso } from '../../lib/format';
import { deriveInstallments, INSTALLMENT_STATUS, type InstallmentStatus, type ScheduleRow } from '../../lib/loanSchedule';

interface AmortizationTableProps {
    schedule: ScheduleRow[] | null | undefined;
    totalPaid: number;
    /** Smaller type/padding, used by the officer loan modal. */
    compact?: boolean;
    showLegend?: boolean;
}

/**
 * Renders a loan amortization schedule with a derived per-row status:
 * paid (green + check), overdue (red), the next installment due (highlighted),
 * and later upcoming installments (plain).
 */
export default function AmortizationTable({ schedule, totalPaid, compact = false, showLegend = true }: AmortizationTableProps) {
    const rows = deriveInstallments(schedule, totalPaid);
    const pad = compact ? 'px-3 py-1.5 text-xs' : 'px-4 py-2';
    const head = compact ? 'px-3 py-2' : 'px-4 py-2';

    if (!schedule || schedule.length === 0) {
        return <div className="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">No payment schedule available</div>;
    }

    return (
        <div className="space-y-3">
            {showLegend && <Legend />}
            <div className="overflow-x-auto">
                <table className="min-w-[820px] w-full text-sm">
                    <thead className="bg-gray-50">
                        <tr className="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                            <th className={head}>#</th>
                            <th className={head}>Due Date</th>
                            <th className={`${head} text-right`}>Payment</th>
                            <th className={`${head} text-right`}>Principal</th>
                            <th className={`${head} text-right`}>Interest</th>
                            <th className={`${head} text-right`}>Penalty</th>
                            <th className={`${head} text-right`}>Balance</th>
                            <th className={head}>Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.map((row) => {
                            const style = INSTALLMENT_STATUS[row.status];
                            const isNext = row.status === 'due';
                            const isInterestExtension = Number(row.interest_extension) > 0;
                            return (
                                <tr key={row.period} className={`${isInterestExtension ? 'bg-amber-50 ring-1 ring-inset ring-amber-200' : style.rowClass} hover:bg-gray-50`}>
                                    <td className={`${pad} ${isNext ? 'border-l-4 border-emerald-500 font-semibold text-emerald-800' : ''}`}>
                                        <span className="inline-flex items-center gap-1.5">
                                            {row.status === 'paid' && <i className="fas fa-check text-emerald-600" aria-label="Paid" />}
                                            {row.status === 'overdue' && <i className="fas fa-exclamation-triangle text-red-500" aria-label="Overdue" />}
                                            {isNext && <i className="fas fa-arrow-right text-emerald-600" aria-label="Next due" />}
                                            {row.period}
                                        </span>
                                        {isInterestExtension && <span className="mt-1 block whitespace-nowrap rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950">Interest only</span>}
                                    </td>
                                    <td className={`${pad} text-gray-600`}>{row.due_date || '—'}</td>
                                    <td className={`${pad} text-right font-medium text-gray-900`}>{formatPeso(row.payment)}</td>
                                    <td className={`${pad} text-right text-gray-600`}>{formatPeso(row.principal)}</td>
                                    <td className={`${pad} text-right ${isInterestExtension ? 'font-semibold text-amber-900' : 'text-gray-500'}`}>{formatPeso(row.interest)}</td>
                                    <td className={`${pad} text-right ${Number(row.penalty) > 0 ? 'font-medium text-amber-700' : 'text-gray-400'}`}>{Number(row.penalty) > 0 ? formatPeso(row.penalty) : '—'}</td>
                                    <td className={`${pad} text-right text-gray-500`}>{Number(row.balance) > 0 ? formatPeso(row.balance) : '—'}</td>
                                    <td className={pad}><Badge variant={style.badgeVariant}>{style.label}</Badge></td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

const LEGEND_DOT: Record<InstallmentStatus, string> = {
    paid: 'bg-emerald-500',
    due: 'bg-emerald-400',
    overdue: 'bg-red-500',
    upcoming: 'bg-gray-300',
};

function Legend() {
    const order: InstallmentStatus[] = ['paid', 'due', 'overdue', 'upcoming'];
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500">
            <span className="font-medium text-gray-600">Legend:</span>
            {order.map((status) => (
                <span key={status} className="inline-flex items-center gap-1.5">
                    <span className={`h-2.5 w-2.5 rounded-full ${LEGEND_DOT[status]}`} />
                    {INSTALLMENT_STATUS[status].label}
                </span>
            ))}
        </div>
    );
}
