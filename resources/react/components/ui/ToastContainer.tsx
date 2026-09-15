import { useToast } from '../../contexts/ToastContext';
import Button from './Button';

export default function ToastContainer() {
    const { toasts, removeToast } = useToast();
    if (toasts.length === 0) return null;

    return (
        <div
            className="fixed bottom-4 right-4 z-50 flex max-w-[calc(100vw-2rem)] flex-col gap-2"
            aria-live="polite"
            aria-label="Notifications"
        >
            {toasts.map(toast => (
                <div
                    key={toast.id}
                    role="status"
                    className={`flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-xl transition-all animate__animated animate__fadeInUp animate__faster ${
                        toast.type === 'success'
                            ? 'bg-emerald-600 text-white'
                            : toast.type === 'error'
                            ? 'bg-red-600 text-white'
                            : toast.type === 'warning'
                            ? 'bg-amber-500 text-white'
                            : 'bg-slate-800 text-white'
                    }`}
                >
                    <span className="flex items-center gap-2">
                        {toast.type === 'success' && <i className="fas fa-check-circle" />}
                        {toast.type === 'error' && <i className="fas fa-exclamation-circle" />}
                        {toast.type === 'warning' && <i className="fas fa-exclamation-triangle" />}
                        {toast.type === 'info' && <i className="fas fa-info-circle" />}
                        <span>{toast.message}</span>
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => removeToast(toast.id)}
                        aria-label="Dismiss notification"
                        className="ml-2 min-h-0 p-0 text-white/70 hover:bg-transparent hover:text-white"
                    >
                        &times;
                    </Button>
                </div>
            ))}
        </div>
    );
}
