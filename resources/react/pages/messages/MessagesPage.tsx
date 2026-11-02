import PageHeader from '../../components/ui/PageHeader';

export default function MessagesPage() {
    return (
        <div className="space-y-6">
            <PageHeader title="Messages" description="Member and cooperative conversations will appear here." icon="fas fa-comments" />
            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                <i className="fas fa-comments text-4xl text-slate-300 dark:text-slate-600"></i>
                <p className="mt-4 text-sm text-slate-500">Messages feature coming soon.</p>
            </div>
        </div>
    );
}
