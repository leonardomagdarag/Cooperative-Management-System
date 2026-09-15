import { api } from './client';

export interface PayMongoSummary {
    enabled: boolean;
    mode: 'sandbox' | 'live';
    is_live: boolean;
    configured: boolean;
    live_configured: boolean;
    pass_on_fees: boolean;
    send_email_receipt: boolean;
    stored: {
        test_public_key: boolean;
        test_secret_key: boolean;
        test_webhook_secret: boolean;
        live_public_key: boolean;
        live_secret_key: boolean;
        live_webhook_secret: boolean;
    };
    configured_keys: {
        test_public_key: boolean;
        test_secret_key: boolean;
        test_webhook_secret: boolean;
        live_public_key: boolean;
        live_secret_key: boolean;
        live_webhook_secret: boolean;
    };
    enabled_methods: string[];
    supported_methods: Record<string, {
        name: string;
        type: string;
        description: string;
        icon: string;
    }>;
}

export interface PayMongoPublicMethod {
    key: string;
    name: string;
    type: string;
    description: string;
    icon: string;
}

export interface PayMongoPublicMethodsResponse {
    enabled: boolean;
    mode: 'sandbox' | 'live';
    methods: PayMongoPublicMethod[];
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

export async function getPayMongoSettings(): Promise<{ data: PayMongoSummary }> {
    const res = await api.get('/api/settings/paymongo');
    return res.data;
}

export async function updatePayMongoSettings(data: {
    enabled: boolean;
    mode: 'sandbox' | 'live';
    enabled_methods: string[];
    pass_on_fees?: boolean;
    send_email_receipt?: boolean;
}): Promise<{ message: string; data: PayMongoSummary }> {
    const res = await api.put('/api/settings/paymongo', data);
    return res.data;
}

export async function updatePayMongoCredentials(data: {
    test_public_key?: string;
    test_secret_key?: string;
    test_webhook_secret?: string;
    live_public_key?: string;
    live_secret_key?: string;
    live_webhook_secret?: string;
}): Promise<{ message: string; data: PayMongoSummary }> {
    const res = await api.put('/api/settings/paymongo/credentials', data);
    return res.data;
}

export async function clearPayMongoCredentials(): Promise<{ message: string; data: PayMongoSummary }> {
    const res = await api.delete('/api/settings/paymongo/credentials');
    return res.data;
}

export async function testPayMongoConnection(): Promise<{ message: string; data: PayMongoSummary }> {
    const res = await api.post('/api/settings/paymongo/test');
    return res.data;
}

export async function getPayMongoMethods(): Promise<PayMongoPublicMethodsResponse> {
    const res = await api.get('/api/services/paymongo/methods');
    return res.data;
}

export async function createPayMongoCheckout(data: {
    service_type: 'deposit' | 'loan_payment';
    amount: number | string;
    account_type?: 'savings' | 'share-capital';
    loan_id?: number | string;
    member_id?: number | string;
    user_id?: number | string;
    note?: string;
    payment_methods?: string[];
    client_request_id?: string;
    return_url?: string;
}): Promise<CheckoutResponse> {
    const res = await api.post('/api/services/paymongo/checkout', data);
    return res.data;
}

export async function verifyPayMongoPayment(transactionNumber: string): Promise<PaymentVerifyResponse> {
    const res = await api.get(`/api/services/paymongo/verify/${transactionNumber}`);
    return res.data;
}
