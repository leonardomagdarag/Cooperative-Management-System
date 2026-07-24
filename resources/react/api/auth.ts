import { api } from './client';

export async function fetchCsrf() {
    await api.get('/sanctum/csrf-cookie');
}

export async function login(email: string, password: string) {
    await fetchCsrf();
    return api.post('/api/login', { email, password });
}

export async function logout() {
    return api.post('/api/logout');
}

export async function fetchUser() {
    return api.get('/api/user');
}

export async function forgotPassword(email: string) {
    return api.post('/forgot-password', { email });
}

export async function resetPassword(payload: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}) {
    return api.post('/reset-password', payload);
}
