import { useEffect, useState } from 'react';

/** Pagination for complete datasets already returned by detail endpoints. */
export default function usePaginatedRows<T>(rows: T[], perPage = 10) {
    const [requestedPage, setPage] = useState(1);
    const lastPage = Math.max(1, Math.ceil(rows.length / perPage));
    const page = Math.min(requestedPage, lastPage);
    useEffect(() => { setPage(current => Math.min(current, lastPage)); }, [lastPage]);
    return { rows: rows.slice((page - 1) * perPage, page * perPage), page, lastPage, total: rows.length, perPage, setPage };
}
