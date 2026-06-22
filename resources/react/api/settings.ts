import { api } from './client';

export async function changePassword(data: {
    current_password: string;
    password: string;
    password_confirmation: string;
}) {
    return api.put('/api/settings/password', data);
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
