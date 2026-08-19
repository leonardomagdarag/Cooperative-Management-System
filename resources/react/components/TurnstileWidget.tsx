import { useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';

declare global {
    interface Window {
        turnstile?: {
            render: (container: string | HTMLElement, options: Record<string, string>) => string;
            reset: (widgetId: string) => void;
            remove: (widgetId: string) => void;
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

export default function TurnstileWidget() {
    const containerRef = useRef<HTMLDivElement>(null);

    const { data: pc } = useQuery({
        queryKey: ['public-content'],
        queryFn: () => api.get('/api/public-content'),
        staleTime: 300000,
    });

    const siteKey = pc?.data?.turnstile_site_key;

    useEffect(() => {
        if (!siteKey || !containerRef.current) return;
        let widgetId = '';

        loadTurnstileScript().then(() => {
            if (containerRef.current && window.turnstile) {
                widgetId = window.turnstile.render(containerRef.current, {
                    sitekey: siteKey,
                });
            }
        });

        return () => {
            if (widgetId && window.turnstile) {
                window.turnstile.remove(widgetId);
            }
        };
    }, [siteKey]);

    if (!siteKey) return null;

    return <div ref={containerRef}></div>;
}
