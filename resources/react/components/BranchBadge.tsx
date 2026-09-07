export default function BranchBadge({ name }: { name?: string | null }) {
    if (!name) return <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">No branch</span>;
    return <span className="inline-flex items-center gap-1 rounded-full bg-cyan-50 px-2.5 py-0.5 text-xs font-medium text-cyan-700 border border-cyan-100">{name}</span>;
}