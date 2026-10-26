import axios from 'axios';

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}

export const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    },
    withCredentials: true,
});

api.interceptors.request.use((config) => {
    const token = getCookie('XSRF-TOKEN');
    if (token) {
        config.headers['X-XSRF-TOKEN'] = token;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && error.response?.data?.reason === 'session_ended') {
            window.dispatchEvent(new CustomEvent('auth:session-ended'));
        }
        // Don't hard-redirect on 401 — let React handle routing via AuthContext.
        // Only redirect on 419 (CSRF expired) which needs a full page reload.
        if (error.response?.status === 419) {
            window.location.reload();
        }
        // 423 = security/OTP verification required (EnsureOtpVerified middleware).
        // Redirect to the server-rendered OTP challenge page.
        if (error.response?.status === 423) {
            const redirect = error.response.data?.redirect;
            window.location.href = redirect || '/security/verify-otp';
        }
        // Laravel blocks the whole app before controllers while it is in
        // maintenance mode. Leave the stale SPA for the standalone page, which
        // does not depend on the API, session, database, or Vite bundle.
        if (error.response?.status === 503 && window.location.pathname !== '/maintenance') {
            window.location.href = '/maintenance';
        }
        // The backend flags its own maintenance responses with `maintenance: true`
        // rather than a status code we can switch on: sign-in being switched off
        // answers 403 from the login endpoint and 503 from the public guard.
        if (error.response?.data?.maintenance === true) {
            const redirect = error.response.data?.redirect;
            if (redirect && !window.location.pathname.startsWith(redirect)) {
                window.location.href = redirect;
            }
        }
        return Promise.reject(error);
    }
);
