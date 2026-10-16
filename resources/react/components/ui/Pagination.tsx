import Button from './Button';

export interface PaginationProps {
    page: number;
    lastPage: number;
    onChange: (page: number) => void;
    total?: number;
    perPage?: number;
    className?: string;
    scrollToTop?: boolean;
}

export default function Pagination({
    page,
    lastPage,
    onChange,
    total,
    perPage,
    className = '',
    scrollToTop = true,
}: PaginationProps) {
    if (lastPage <= 1 && (!total || total <= (perPage ?? 15))) return null;

    const handlePageChange = (newPage: number) => {
        onChange(newPage);
        if (scrollToTop) {
            const main = document.querySelector('main');
            if (main) {
                main.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }
    };

    const from = perPage && total ? Math.min((page - 1) * perPage + 1, total) : null;
    const to = perPage && total ? Math.min(page * perPage, total) : null;

    return (
        <nav
            className={`flex flex-wrap items-center justify-between gap-3 py-4 text-sm text-slate-500 ${className}`}
            aria-label="Pagination"
        >
            <div className="text-xs sm:text-sm text-slate-500">
                {from !== null && to !== null && total !== undefined ? (
                    <span>
                        Showing <span className="font-semibold text-slate-900">{from}</span> to{' '}
                        <span className="font-semibold text-slate-900">{to}</span> of{' '}
                        <span className="font-semibold text-slate-900">{total}</span> results
                    </span>
                ) : (
                    <span>
                        Page <span className="font-semibold text-slate-900">{page}</span> of{' '}
                        <span className="font-semibold text-slate-900">{lastPage}</span>
                    </span>
                )}
            </div>
            <div className="flex items-center gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    disabled={page <= 1}
                    onClick={() => handlePageChange(Math.max(1, page - 1))}
                    aria-label="Previous page"
                >
                    <i className="fas fa-chevron-left text-xs" />
                    <span className="hidden sm:inline">Previous</span>
                </Button>
                <span className="px-2 text-xs font-medium text-slate-600 sm:hidden">
                    {page} / {lastPage}
                </span>
                <Button
                    variant="secondary"
                    size="sm"
                    disabled={page >= lastPage}
                    onClick={() => handlePageChange(Math.min(lastPage, page + 1))}
                    aria-label="Next page"
                >
                    <span className="hidden sm:inline">Next</span>
                    <i className="fas fa-chevron-right text-xs" />
                </Button>
            </div>
        </nav>
    );
}

