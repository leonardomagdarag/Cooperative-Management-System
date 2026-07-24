export default function LoadingScreen() {
    return (
        <div className="flex h-screen items-center justify-center bg-slate-50">
            <div className="flex flex-col items-center gap-3">
                <div className="h-10 w-10 animate-spin rounded-full border-4 border-emerald-200 border-t-emerald-600"></div>
                <p className="text-sm text-slate-500">Loading...</p>
            </div>
        </div>
    );
}
