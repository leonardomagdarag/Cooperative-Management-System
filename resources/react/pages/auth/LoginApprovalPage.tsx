import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router';
import { api } from '../../api/client';

export default function LoginApprovalPage() {
    const [status, setStatus] = useState('pending');
    const [message, setMessage] = useState('Waiting for approval on your currently signed-in device.');
    const [requestingOtp, setRequestingOtp] = useState(false);
    const [emailOtpBypassed, setEmailOtpBypassed] = useState(false);
    const statusRef = useRef('pending');
    const pollNowRef = useRef<(() => void) | null>(null);

    const verifyByEmail = async () => {
        statusRef.current = 'otp_pending';
        setStatus('otp_pending');
        setRequestingOtp(true);
        setMessage('Sending a verification code to your registered email address...');

        try {
            const { data } = await api.post('/api/login-handoffs/otp');
            window.location.assign(data.redirect || (data.success ? '/dashboard' : '/security/verify-otp'));
        } catch (error: any) {
            statusRef.current = 'pending';
            setStatus('pending');
            setMessage(error?.response?.data?.message || 'We could not send a verification code. Please try again or approve the sign-in from your active device.');
            pollNowRef.current?.();
        } finally {
            setRequestingOtp(false);
        }
    };

    useEffect(() => {
        let active = true;
        let timer: number;
        const poll = async () => {
            try {
                const { data } = await api.get('/api/login-handoffs/status');
                if (!active) return;
                statusRef.current = data.status;
                setStatus(data.status);
                setEmailOtpBypassed(!!data.email_otp_bypassed);
                if (data.status === 'otp_pending') {
                    window.location.assign('/security/verify-otp');
                    return;
                }
                if (data.status === 'approved') {
                    const result = await api.post('/api/login-handoffs/complete');
                    if (result.data.requires_otp) {
                        window.location.assign(result.data.redirect || '/security/verify-otp');
                        return;
                    }
                    window.location.assign(result.data.redirect || '/dashboard');
                    return;
                }
                if (data.status === 'completed') {
                    window.location.assign('/dashboard');
                    return;
                }
                if (data.status === 'rejected') setMessage('The sign-in was rejected. Your current device remains signed in. If this was not you, reset your password now.');
                else if (data.status === 'expired') setMessage('This sign-in request expired. Start again when you are ready.');
            } catch {
                if (active) setMessage('Unable to check the approval right now. Please keep this page open and try again.');
            }
            if (active && (statusRef.current === 'pending' || statusRef.current === 'approved')) timer = window.setTimeout(poll, 2500);
        };
        pollNowRef.current = () => { void poll(); };
        void poll();
        return () => { active = false; pollNowRef.current = null; window.clearTimeout(timer); };
    }, []);

    return <main className="flex min-h-screen items-center justify-center bg-slate-50 p-6">
        <section className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm" aria-live="polite">
            <h1 className="text-xl font-medium text-slate-900">Approve sign-in</h1>
            <p className="mt-3 text-sm leading-6 text-slate-600">{message}</p>
            {status === 'approved' && <p className="mt-3 text-sm text-emerald-700">Approved. Completing secure sign-in…</p>}
            {status === 'pending' && <button type="button" onClick={verifyByEmail} disabled={requestingOtp} className="mt-5 w-full rounded-lg bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60">{requestingOtp ? (emailOtpBypassed ? 'Continuing...' : 'Sending code...') : emailOtpBypassed ? 'Can’t access that device? Continue without email OTP' : 'Can’t access that device? Verify by email instead'}</button>}
            {status === 'otp_pending' && <p className="mt-5 rounded-lg bg-emerald-50 p-3 text-sm font-medium text-emerald-800">Use the emailed code to finish verifying this sign-in.</p>}
            <div className="mt-6 flex justify-center gap-4">
                <Link to="/forgot-password" className="text-sm font-semibold text-emerald-700 hover:underline">Reset password</Link>
                <Link to="/login" className="text-sm font-semibold text-slate-600 hover:underline">Return to login</Link>
            </div>
        </section>
    </main>;
}
