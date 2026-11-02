import { useLanguage } from '../contexts/LanguageContext';

export default function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
    const { language, setLanguage } = useLanguage();

    return (
        <label className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600">
            {!compact && <i className="fas fa-language text-base" aria-hidden="true" />}
            <span className="sr-only">Language / Wika</span>
            <select
                aria-label="Language / Wika"
                value={language}
                onChange={event => setLanguage(event.target.value === 'tl' ? 'tl' : 'en')}
                className="max-w-24 rounded-lg border border-slate-200 bg-white px-1.5 py-2 text-sm text-slate-700 focus-visible:outline-2 focus-visible:outline-emerald-600"
            >
                <option value="en">English</option>
                <option value="tl">Tagalog</option>
            </select>
        </label>
    );
}
