export default function LoadingIndicator({ message = 'Loading...', className = 'min-h-64' }: { message?: string; className?: string }) {
    return (
        <div role="status" aria-live="polite" className={`flex items-center justify-center gap-3 text-sm text-slate-600 ${className}`}>
            <i className="fas fa-spinner fa-spin text-lg text-emerald-600" aria-hidden="true" />
            <span>{message}</span>
        </div>
    );
}
