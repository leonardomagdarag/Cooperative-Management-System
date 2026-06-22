import { api } from './client';

export interface Transaction {
    id: number;
    transaction_number: string;
    transaction_type: string;
    amount: number;
    status: string;
    note: string;
    transaction_date: string;
    account: { id: number; account_number: string; account_type: string } | null;
    loan: { id: number; loan_number: string } | null;
    recorded_by: { id: number; name: string } | null;
    created_at: string;
}

export async function getHistory(params?: {
    category?: string;
    status?: string;
    date?: string;
    search?: string;
    page?: number;
    per_page?: number;
    direction?: string;
}) {
    return api.get('/api/history', { params });
}
