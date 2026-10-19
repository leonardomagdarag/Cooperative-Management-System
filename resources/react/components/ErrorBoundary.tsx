import { Component, ReactNode } from 'react';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
    resetKey?: string;
}

interface State {
    hasError: boolean;
    error?: Error;
}

export default class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: any) {
        console.error('ErrorBoundary caught:', error, errorInfo);
    }

    componentDidUpdate(previousProps: Props) {
        if (this.state.hasError && previousProps.resetKey !== this.props.resetKey) {
            this.setState({ hasError: false, error: undefined });
        }
    }

    render() {
        if (this.state.hasError) {
            if (this.props.fallback) return this.props.fallback;

            return (
                <div className="flex min-h-[400px] items-center justify-center p-8">
                    <div className="text-center">
                        <i className="fas fa-exclamation-triangle text-4xl text-amber-500"></i>
                        <h2 className="mt-4 text-lg font-bold text-slate-900 dark:text-white">Something went wrong</h2>
                        <p className="mt-2 text-sm text-slate-500">An unexpected error occurred. Please try refreshing the page.</p>
                        <button onClick={() => window.location.reload()} className="mt-4 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Refresh Page
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
