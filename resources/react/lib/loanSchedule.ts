/**
 * Shared helpers for the loan modals.
 *
 * The backend stores no per-installment status, so the paid / next due /
 * overdue / upcoming state of each amortization row is derived here from the
 * schedule plus the loan's completed payments (the same cumulative walk used
 * by the loan payment wizard).
 */

export interface ScheduleRow {
    period: number;
    due_date: string | null;
    payment: number;
    principal: number;
    interest: number;
    balance: number;
    total_interest?: number;
    total_payment?: number;
}

export type InstallmentStatus = 'paid' | 'overdue' | 'due' | 'upcoming';

export interface DerivedInstallment extends ScheduleRow {
    amount_due: number;
    covered: number;
    status: InstallmentStatus;
}

export interface InstallmentStyle {
    label: string;
    badgeVariant: 'success' | 'warning' | 'danger' | 'info' | 'neutral';
    rowClass: string;
}

export const INSTALLMENT_STATUS: Record<InstallmentStatus, InstallmentStyle> = {
    paid: {
        label: 'Paid',
        badgeVariant: 'success',
        rowClass: 'bg-emerald-50',
    },
    overdue: {
        label: 'Overdue',
        badgeVariant: 'danger',
        rowClass: 'bg-red-50',
    },
    due: {
        label: 'Next due',
        badgeVariant: 'info',
        rowClass: 'bg-emerald-100/60',
    },
    upcoming: {
        label: 'Upcoming',
        badgeVariant: 'neutral',
        rowClass: '',
    },
};

/**
 * Parse a schedule due date to local midnight. The backend renders `M d, Y`
 * (e.g. "Oct 15, 2026"), which `new Date()` accepts directly; an ISO
 * `YYYY-MM-DD` value needs an explicit time so it is read as local, not UTC.
 */
function parseDueDate(value: string | null | undefined): Date | null {
    if (!value) return null;
    const normalized = /^\d{4}-\d{2}-\d{2}/.test(value) ? `${value}T00:00:00` : value;
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Walk the amortization schedule and apply completed payments cumulatively,
 * marking each installment as paid, overdue, the next one due, or upcoming.
 */
export function deriveInstallments(
    schedule: ScheduleRow[] | null | undefined,
    totalPaid: number,
): DerivedInstallment[] {
    if (!schedule || schedule.length === 0) return [];

    const paid = Math.max(0, Number(totalPaid) || 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    let cumulative = 0;
    let nextDueAssigned = false;

    return schedule.map((row) => {
        const entry = Number(row.payment) || 0;
        const covered = Math.min(Math.max(paid - cumulative, 0), entry);
        const amountDue = Math.round((entry - covered) * 100) / 100;
        cumulative += entry;

        let status: InstallmentStatus;
        if (amountDue <= 0.005) {
            status = 'paid';
        } else {
            const dueDate = parseDueDate(row.due_date);
            if (dueDate && dueDate < today) {
                status = 'overdue';
            } else if (!nextDueAssigned) {
                status = 'due';
                nextDueAssigned = true;
            } else {
                status = 'upcoming';
            }
        }

        return { ...row, amount_due: amountDue, covered, status };
    });
}
