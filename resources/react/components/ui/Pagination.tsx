import Button from './Button';

export interface PaginationProps {
    page?: number;
    currentPage?: number;
    lastPage?: number;
    totalPages?: number;
    onChange?: (page: number) => void;
    onPageChange?: (page: number) => void;
    total?: number;
    perPage?: number;
    className?: string;
    scrollToTop?: boolean;
}

export default function Pagination({
    page,
    currentPage,
    lastPage,
    totalPages,
    onChange,
    onPageChange,
    total,
    perPage,
    className = '',
    scrollToTop = true,
}: PaginationProps) {
    const activePage = page ?? currentPage ?? 1;
    const finalLastPage = Math.max(1, lastPage ?? totalPages ?? 1);
    const handleChange = onChange ?? onPageChange;

    if (finalLastPage <= 1 && (!total || total <= (perPage ?? 15))) return null;

    const handlePageChange = (newPage: number) => {
        if (handleChange) {
            handleChange(newPage);
        }
        if (scrollToTop) {
            const main = document.querySelector('main');
            if (main) {
                if (typeof main.scrollTo === 'function') {
                    main.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    main.scrollTop = 0;
                }
            } else if (typeof window !== 'undefined') {
                if (typeof window.scrollTo === 'function') {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    window.scrollY = 0;
                }
            }
        }
    };

    const from = perPage && total ? Math.min((activePage - 1) * perPage + 1, total) : null;
    const to = perPage && total ? Math.min(activePage * perPage, total) : null;

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
                        Page <span className="font-semibold text-slate-900">{activePage}</span> of{' '}
                        <span className="font-semibold text-slate-900">{finalLastPage}</span>
                    </span>
                )}
            </div>
            <div className="flex items-center gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    disabled={activePage <= 1}
                    onClick={() => handlePageChange(Math.max(1, activePage - 1))}
                    aria-label="Previous page"
                >
                    <i className="fas fa-chevron-left text-xs" />
                    <span className="hidden sm:inline">Previous</span>
                </Button>
                <span className="px-2 text-xs font-medium text-slate-600 sm:hidden">
                    {activePage} / {finalLastPage}
                </span>
                <Button
                    variant="secondary"
                    size="sm"
                    disabled={activePage >= finalLastPage}
                    onClick={() => handlePageChange(Math.min(finalLastPage, activePage + 1))}
                    aria-label="Next page"
                >
                    <span className="hidden sm:inline">Next</span>
                    <i className="fas fa-chevron-right text-xs" />
                </Button>
            </div>
        </nav>
    );
}
