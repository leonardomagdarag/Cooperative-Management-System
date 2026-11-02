import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './App';
import ErrorBoundary from './components/ErrorBoundary';
import { AuthProvider } from './contexts/AuthContext';
import { SidebarProvider } from './contexts/SidebarContext';
import { ThemeProvider } from './contexts/ThemeContext';
import { ToastProvider } from './contexts/ToastContext';
import { LanguageProvider } from './contexts/LanguageContext';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 30_000,
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

const root = document.getElementById('app');
if (root) {
    ReactDOM.createRoot(root).render(
        <React.StrictMode>
            <ErrorBoundary>
                <BrowserRouter>
                    <QueryClientProvider client={queryClient}>
                        <LanguageProvider>
                        <AuthProvider>
                            <ThemeProvider>
                                <SidebarProvider>
                                    <ToastProvider>
                                        <App />
                                    </ToastProvider>
                                </SidebarProvider>
                            </ThemeProvider>
                        </AuthProvider>
                        </LanguageProvider>
                    </QueryClientProvider>
                </BrowserRouter>
            </ErrorBoundary>
        </React.StrictMode>
    );
}
