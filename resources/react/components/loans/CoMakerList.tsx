import { useLanguage } from '../../contexts/LanguageContext';

export interface CoMaker {
    id: number;
    name: string;
    user_number: string | null;
    has_approved?: boolean;
}

export default function CoMakerList({ members = [] }: { members?: CoMaker[] }) {
    const { t } = useLanguage();

    if (members.length === 0) {
        return <p className="text-sm text-gray-500">{t('No co-makers recorded')}</p>;
    }

    return (
        <ul className="space-y-2 text-sm">
            {members.map(member => (
                <li key={member.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                    <span className="font-medium text-gray-900">
                        {member.name}
                        {member.user_number && <span className="ml-2 font-normal text-gray-600">{member.user_number}</span>}
                    </span>
                    {member.has_approved && <span className="text-emerald-700">{t('Approved')}</span>}
                </li>
            ))}
        </ul>
    );
}
