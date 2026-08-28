import { useEffect, useRef, useImperativeHandle, forwardRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';

declare global {
    interface Window {
        turnstile?: {
            render: (container: string | HTMLElement, options: Record<string, string>) => string;
            reset: (widgetId: string) => void;
            remove: (widgetId: string) => void;
            getResponse: (widgetId: string) => string;
        };
    }
}

const TURNSTILE_SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
let scriptLoadPromise: Promise<void> | null = null;

function loadTurnstileScript(): Promise<void> {
    if (window.turnstile) return Promise.resolve();
    if (scriptLoadPromise) return scriptLoadPromise;

    scriptLoadPromise = new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = TURNSTILE_SCRIPT;
        script.async = true;
        script.defer = true;
        script.onload = () => {
            scriptLoadPromise = null;
            resolve();
        };
        document.head.appendChild(script);
    });

    return scriptLoadPromise;
}

interface TurnstileWidgetRef {
    getToken: () => string | null;
    reset: () => void;
}

export default forwardRef<TurnstileWidgetRef, {}>(function TurnstileWidget(props, ref) {
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string>('');

    const { data: pc } = useQuery({
        queryKey: ['public-content'],
        queryFn: () => api.get('/api/public-content'),
        staleTime: 300000,
    });

    const siteKey = pc?.data?.turnstile_site_key;

    useImperativeHandle(ref, () => ({
        getToken: () => {
            if (widgetIdRef.current && window.turnstile) {
                return window.turnstile.getResponse(widgetIdRef.current);
            }
            return null;
        },
        reset: () => {
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.reset(widgetIdRef.current);
            }
        },
    }));

    useEffect(() => {
        if (!siteKey || !containerRef.current) return;
        let cancelled = false;

        loadTurnstileScript().then(() => {
            if (!cancelled && containerRef.current && window.turnstile) {
                widgetIdRef.current = window.turnstile.render(containerRef.current, {
                    sitekey: siteKey,
                });
            }
        });

        return () => {
            cancelled = true;
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current);
            }
            widgetIdRef.current = '';
        };
    }, [siteKey]);

    if (!siteKey) return null;

    return <div ref={containerRef}></div>;
});
