import { api } from './client';

export async function changePassword(data: {
    current_password: string;
    password: string;
    password_confirmation: string;
    otp_code: string;
}) {
    return api.put('/api/settings/password', data);
}

export async function sendPasswordOtp() {
    return api.post('/api/settings/password/send-otp');
}

export async function sendTrustedDeviceRevocationOtp(deviceId: number) {
    return api.post(`/api/settings/trusted-devices/${deviceId}/revoke/send-otp`);
}

export async function revokeTrustedDevice(deviceId: number, otp_code: string) {
    return api.post(`/api/settings/trusted-devices/${deviceId}/revoke`, { otp_code });
}

export async function sendRevokeAllDevicesOtp() {
    return api.post('/api/settings/revoke-devices/send-otp');
}

export async function revokeDevices(otp_code: string) {
    return api.post('/api/settings/revoke-devices', { otp_code });
}

export async function deactivateAccount(data: { current_password: string }) {
    return api.post('/api/settings/deactivate-account', data);
}

export async function deleteAccount(data: { current_password: string }) {
    return api.delete('/api/settings/account', { data });
}

export interface ServiceAvailabilityFlags {
    global: boolean;
    member: boolean;
    officer: boolean;
    counter?: boolean;
    online?: boolean;
    bank_transfer?: boolean;
    // Withdrawal limits, exposed on /api/services/availability only.
    min_amount?: number;
    max_amount?: number;
    savings_enabled?: boolean;
    share_capital_enabled?: boolean;
    fee?: { mode: 'fixed' | 'percent'; value: number };
}

export interface ServiceAvailabilityData {
    deposit: ServiceAvailabilityFlags;
    withdrawal: ServiceAvailabilityFlags;
    loan_application: ServiceAvailabilityFlags;
    loan_payment: ServiceAvailabilityFlags;
    login: Pick<ServiceAvailabilityFlags, 'global' | 'member' | 'officer'>;
}

// Withdrawal was relocated to the Financial Accounts tab, so the settings
// endpoint no longer returns it.
export type SettingsServiceAvailabilityData = Omit<ServiceAvailabilityData, 'withdrawal'>;

export async function getServiceAvailability() {
    return api.get('/api/settings/service-availability');
}

export async function getServiceStatus() {
    return api.get('/api/services/availability');
}

export async function updateServiceAvailability(data: {
    services: SettingsServiceAvailabilityData;
    notify_affected: boolean;
}) {
    return api.put('/api/settings/service-availability', data);
}

// ── Financial Accounts ──────────────────────────────────────────────────────

export type InterestInterval = 'daily' | 'monthly' | 'yearly';
export type FinancialAccountType = 'savings' | 'share-capital';

export interface FinancialAccountConfig {
    rate: number;
    interval: InterestInterval;
    min_balance: number;
}

export interface FinancialWithdrawalConfig {
    min_amount: number;
    max_amount: number;
    savings_enabled: boolean;
    share_capital_enabled: boolean;
    enabled: boolean;
    member: boolean;
    officer: boolean;
    counter: boolean;
    online: boolean;
    bank_transfer: boolean;
}

export interface FinancialSettings {
    savings: FinancialAccountConfig;
    share_capital: FinancialAccountConfig;
    interest: {
        min_payout: number;
        notify_members: boolean;
    };
    fees: Record<'deposit' | 'withdrawal' | 'loan_payment' | 'loan_disbursement', { mode: 'fixed' | 'percent'; value: number }>;
    withdrawal: FinancialWithdrawalConfig;
    last_credited: {
        savings: string | null;
        share_capital: string | null;
    };
}

export interface FinancialSettingsPayload {
    savings: FinancialAccountConfig;
    share_capital: FinancialAccountConfig;
    interest: {
        min_payout: number;
        notify_members: boolean;
    };
    fees: FinancialSettings['fees'];
    withdrawal: FinancialWithdrawalConfig;
    notify_affected: boolean;
}

export async function getFinancialSettings() {
    return api.get('/api/settings/financial');
}

export async function updateFinancialSettings(data: FinancialSettingsPayload) {
    return api.put('/api/settings/financial', data);
}

export async function runInterestNow(data: { account_type: FinancialAccountType }) {
    return api.post('/api/settings/financial/interest/run', data);
}
