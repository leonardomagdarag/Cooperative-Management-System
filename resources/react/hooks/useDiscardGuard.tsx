import { useCallback, useEffect, useState } from 'react';

export function useDiscardGuard(controlledDirty?: boolean) {
    const [internalDirty, setInternalDirty] = useState(false);
    const dirty = controlledDirty ?? internalDirty;
    const [pendingClose, setPendingClose] = useState<(() => void) | null>(null);

    useEffect(() => {
        if (!dirty) return;
        const handler = (e: BeforeUnloadEvent) => { e.preventDefault(); e.returnValue = ''; };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [dirty]);

    const markDirty = useCallback(() => setInternalDirty(true), []);
    const reset = useCallback(() => { setInternalDirty(false); setPendingClose(null); }, []);

    const requestClose = useCallback((close: () => void) => {
        if (dirty) setPendingClose(() => close);
        else close();
    }, [dirty]);

    const discard = useCallback(() => {
        const close = pendingClose;
        setPendingClose(null);
        setInternalDirty(false);
        close?.();
    }, [pendingClose]);

    const keepEditing = useCallback(() => setPendingClose(null), []);

    const renderDialog = useCallback(() => {
        if (!pendingClose) return null;
        return (
            <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={keepEditing}>
                <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl" onClick={e => e.stopPropagation()}>
                    <div className="flex items-center gap-3 mb-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600"><i className="fas fa-exclamation-triangle"></i></div>
                        <h3 className="text-lg font-bold text-gray-900">Discard changes?</h3>
                    </div>
                    <p className="text-sm text-gray-600 mb-6">You have unsaved changes in this form. If you close now, they will be lost.</p>
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={keepEditing} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Keep editing</button>
                        <button type="button" onClick={discard} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Discard</button>
                    </div>
                </div>
            </div>
        );
    }, [pendingClose, keepEditing, discard]);

    return { dirty, markDirty, reset, requestClose, renderDialog };
}