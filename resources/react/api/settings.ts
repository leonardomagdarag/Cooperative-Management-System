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

export async function revokeDevices() {
    return api.post('/api/settings/revoke-devices');
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
}

export interface ServiceAvailabilityData {
    deposit: ServiceAvailabilityFlags;
    withdrawal: ServiceAvailabilityFlags;
    loan_application: ServiceAvailabilityFlags;
    loan_payment: ServiceAvailabilityFlags;
    registration: Pick<ServiceAvailabilityFlags, 'global' | 'member'>;
    login: Pick<ServiceAvailabilityFlags, 'global' | 'member' | 'officer'>;
}

export async function getServiceAvailability() {
    return api.get('/api/settings/service-availability');
}

export async function updateServiceAvailability(data: {
    services: ServiceAvailabilityData;
    notify_affected: boolean;
}) {
    return api.put('/api/settings/service-availability', data);
}
