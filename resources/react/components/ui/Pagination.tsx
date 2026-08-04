import Button from './Button';

export default function Pagination({ page, lastPage, onChange }: { page: number; lastPage: number; onChange: (page: number) => void }) {
    if (lastPage <= 1) return null;
    return <nav className="ui-pagination" aria-label="Pagination"><Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => onChange(Math.max(1, page - 1))}>Previous</Button><span>Page {page} of {lastPage}</span><Button variant="secondary" size="sm" disabled={page >= lastPage} onClick={() => onChange(Math.min(lastPage, page + 1))}>Next</Button></nav>;
}
