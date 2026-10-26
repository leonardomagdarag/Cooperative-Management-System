import { api } from './client';

export interface XenditSummary {
    enabled: boolean;
    mode: 'test' | 'live';
    configured: boolean;
    live_configured: boolean;
    stored: Record<string, boolean>;
    configured_keys: Record<string, boolean>;
}

export interface CheckoutResponse {
    message: string;
    checkout_url: string;
    checkout_session_id: string;
    transaction_number: string;
    transaction_id: number;
    amount: number;
}

export interface PaymentVerifyResponse {
    status: 'pending' | 'completed' | 'rejected';
    is_paid: boolean;
    message: string;
    transaction: {
        id: number;
        transaction_number: string;
        transaction_type: string;
        amount: number;
        status: string;
        payment_gateway: string;
        payment_method_used?: string;
        transaction_date?: string;
        approved_at?: string;
        note?: string;
    };
}

export async function getXenditSettings(): Promise<{ data: XenditSummary }> {
    const response = await api.get('/api/settings/xendit');
    return response.data;
}

export async function updateXenditSettings(data: { enabled: boolean; mode: 'test' | 'live' }) {
    const response = await api.put('/api/settings/xendit', data);
    return response.data;
}

export async function updateXenditCredentials(data: Record<string, string>) {
    const response = await api.put('/api/settings/xendit/credentials', data);
    return response.data;
}

export async function clearXenditCredentials() {
    const response = await api.delete('/api/settings/xendit/credentials');
    return response.data;
}

export async function testXenditConnection() {
    const response = await api.post('/api/settings/xendit/test');
    return response.data;
}

export async function getXenditStatus(): Promise<{ enabled: boolean; mode: 'test' | 'live' }> {
    const response = await api.get('/api/services/xendit/status');
    return response.data;
}

export async function createXenditCheckout(data: {
    service_type: 'deposit' | 'loan_payment';
    amount: number | string;
    account_type?: 'savings' | 'share-capital';
    loan_id?: number | string;
    member_id?: number | string;
    user_id?: number | string;
    installment_allocations?: Array<{ period: number; amount: number }>;
    penalty_amount?: number | string;
    note?: string;
    client_request_id?: string;
}): Promise<CheckoutResponse> {
    const endpoint = data.service_type === 'deposit'
        ? '/api/services/xendit/deposit/checkout'
        : '/api/services/xendit/loan-payment/checkout';
    const response = await api.post(endpoint, data);
    return response.data;
}

export async function verifyXenditPayment(transactionNumber: string): Promise<PaymentVerifyResponse> {
    const response = await api.get(`/api/services/xendit/verify/${transactionNumber}`);
    return response.data;
}
