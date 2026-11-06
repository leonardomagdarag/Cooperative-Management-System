import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router';
import { useAuth } from '../contexts/AuthContext';
import { useSidebar } from '../contexts/SidebarContext';
import NotificationDropdown from './NotificationDropdown';
import LanguageSwitcher from './LanguageSwitcher';
import { useLanguage } from '../contexts/LanguageContext';
import { globalSearch, SearchGroup, SearchItem } from '../api/search';

import Button from './ui/Button';
interface FlatItem {
    group: string;
    item: SearchItem;
}

export default function Header() {
    const { t } = useLanguage();
    const { user } = useAuth();
    const { isOpen, toggle, toggleMobile } = useSidebar();
    const navigate = useNavigate();

    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<SearchGroup[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);

    const inputRef = useRef<HTMLInputElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const searchControllerRef = useRef<AbortController | null>(null);

    const displayName = user?.name || 'User';
    const displayRole = user?.primary_role || 'Member';
    const isMac = typeof navigator !== 'undefined' && /Mac|iPod|iPhone|iPad/.test(navigator.userAgent);

    const flatItems: FlatItem[] = groups.flatMap(g => g.items.map(item => ({ group: g.label, item })));

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        const q = query.trim();

        if (q.length < 2) {
            searchControllerRef.current?.abort();
            setGroups([]);
            setLoading(false);
            setOpen(false);
            return;
        }

        setLoading(true);
        setOpen(true);

        debounceRef.current = setTimeout(async () => {
            searchControllerRef.current?.abort();
            const controller = new AbortController();
            searchControllerRef.current = controller;
            try {
                const result = await globalSearch(q, controller.signal);
                if (controller.signal.aborted) return;
                setGroups(result);
                setActiveIndex(result.flatMap(g => g.items).length > 0 ? 0 : -1);
            } catch (error: any) {
                if (controller.signal.aborted) return;
                setGroups([]);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);

        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            searchControllerRef.current?.abort();
        };
    }, [query]);

    useEffect(() => {
        function onKeyDown(e: KeyboardEvent) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
                inputRef.current?.select();
            }
        }
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    useEffect(() => {
        function onClickOutside(e: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    function goTo(item: SearchItem) {
        navigate(item.url);
        setQuery('');
        setOpen(false);
        setGroups([]);
        inputRef.current?.blur();
    }

    function onKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setOpen(true);
            setActiveIndex(i => (flatItems.length === 0 ? -1 : (i + 1) % flatItems.length));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIndex(i => (flatItems.length === 0 ? -1 : (i - 1 + flatItems.length) % flatItems.length));
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && flatItems[activeIndex]) {
                goTo(flatItems[activeIndex].item);
            }
        } else if (e.key === 'Escape') {
            setOpen(false);
            inputRef.current?.blur();
        }
    }

    function onMouseEnter(index: number) {
        setActiveIndex(index);
    }

    const [mobileSearchOpen, setMobileSearchOpen] = useState(false);

    return (
        <>
            <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white shadow-sm px-4 lg:px-6">
                <div className="flex items-center gap-2 sm:gap-3">
                    <button
                        onClick={toggleMobile}
                        className="flex lg:hidden items-center justify-center w-10 h-10 text-gray-500 rounded-lg hover:bg-gray-100"
                        aria-label="Toggle Mobile Menu"
                    >
                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fillRule="evenodd" clipRule="evenodd" d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z" fill="currentColor"/>
                        </svg>
                    </button>
                    <button
                        onClick={toggle}
                        className="hidden lg:flex items-center justify-center w-10 h-10 text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-100"
                        aria-label="Toggle Sidebar"
                    >
                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fillRule="evenodd" clipRule="evenodd" d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z" fill="currentColor"/>
                        </svg>
                    </button>

                    <div className="hidden md:block">
                        <div className="relative" ref={containerRef}>
                            <span className="absolute -translate-y-1/2 pointer-events-none left-4 top-1/2">
                                <svg className="fill-gray-400" width="18" height="18" viewBox="0 0 20 20" fill="none">
                                    <path fillRule="evenodd" clipRule="evenodd" d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z" fill=""/>
                                </svg>
                            </span>
                            <input
                                ref={inputRef}
                                type="text"
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                onKeyDown={onKeyDown}
                                onFocus={() => query.trim().length >= 2 && setOpen(true)}
                                placeholder={t('Search or type command...')}
                                className="h-10 w-full rounded-lg border border-gray-200 bg-gray-50/50 py-2 pl-11 pr-16 text-sm text-gray-800 placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 md:w-64 lg:w-80 xl:w-[420px] transition-all"
                            />
                            <span className="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[11px] font-medium text-gray-500 shadow-xs">
                                {isMac ? '⌘K' : 'Ctrl+K'}
                            </span>

                            {open && (
                                <div className="absolute left-0 right-0 top-[calc(100%+8px)] max-h-[480px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl z-50">
                                    <div className="max-h-[480px] overflow-y-auto p-2">
                                        {loading && (
                                            <div className="flex items-center gap-2 px-3 py-3 text-sm text-gray-500">
                                                <span className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-emerald-600" />
                                                {t('Searching...')}
                                            </div>
                                        )}

                                        {!loading && flatItems.length === 0 && (
                                            <p className="px-3 py-4 text-center text-sm text-gray-500">{t('No results found.')}</p>
                                        )}

                                        {!loading && groups.map(group => (
                                            <div key={group.label}>
                                                <p className="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{group.label}</p>
                                                {group.items.map(item => {
                                                    const flatIndex = flatItems.findIndex(fi => fi.group === group.label && fi.item === item);
                                                    const isActive = flatIndex === activeIndex;
                                                    return (
                                                        <button
                                                            key={`${group.label}-${(item as any).id ?? item.label}-${flatIndex}`}
                                                            type="button"
                                                            onMouseDown={e => e.preventDefault()}
                                                            onClick={() => goTo(item)}
                                                            onMouseEnter={() => onMouseEnter(flatIndex)}
                                                            className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors ${isActive ? 'bg-emerald-50 text-emerald-950' : 'hover:bg-gray-50'}`}
                                                        >
                                                            {item.icon && (
                                                                <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm ${isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                                                                    <i className={`fas fa-${item.icon}`} />
                                                                </span>
                                                            )}
                                                            <span className="min-w-0 flex-1">
                                                                <span className="block truncate text-sm font-medium text-gray-900">{item.label}</span>
                                                                {item.subtitle && <span className="block truncate text-xs text-gray-500">{item.subtitle}</span>}
                                                            </span>
                                                            {item.meta && (
                                                                <span className="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium capitalize text-gray-600">{item.meta}</span>
                                                            )}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-1 sm:gap-2">
                    <button
                        type="button"
                        onClick={() => setMobileSearchOpen(true)}
                        className="flex md:hidden items-center justify-center w-10 h-10 text-gray-500 rounded-lg hover:bg-gray-100"
                        aria-label="Open search"
                    >
                        <i className="fas fa-search text-sm"></i>
                    </button>

                    <NotificationDropdown />

                    <button
                        type="button"
                        onClick={() => navigate('/dashboard/settings?tab=help-support', { state: { tab: 'help-support' } })}
                        className="flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors cursor-pointer"
                        title={t('Help & Support')}
                        aria-label={t('Help and Support')}
                    >
                        <i className="fas fa-question-circle text-base"></i>
                    </button>

                    <LanguageSwitcher compact />

                    <button
                        onClick={() => navigate('/dashboard/profile')}
                        className="flex items-center gap-2 sm:gap-3 pl-2 sm:pl-4 border-l border-gray-200 hover:opacity-80 transition-opacity cursor-pointer"
                        title={t('View my profile')}
                    >
                        <div className="hidden sm:block text-right">
                            <p className="text-sm font-medium text-gray-900 truncate max-w-[140px]">{displayName}</p>
                            <p className="text-xs text-gray-500 capitalize">{displayRole}</p>
                        </div>
                        <div className="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-linear-to-r from-emerald-500 to-cyan-500 flex items-center justify-center text-white font-semibold ring-2 ring-transparent hover:ring-emerald-400 transition-all text-sm sm:text-base shrink-0">
                            {displayName.charAt(0).toUpperCase()}
                        </div>
                    </button>
                </div>
            </header>

            {/* Mobile Search Modal */}
            {mobileSearchOpen && (
                <div
                    className="fixed inset-0 z-50 flex flex-col bg-slate-900/50 backdrop-blur-sm p-4 md:hidden"
                    onClick={() => { setMobileSearchOpen(false); setQuery(''); }}
                >
                    <div
                        className="w-full rounded-2xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[80vh]"
                        onClick={e => e.stopPropagation()}
                    >
                        <div className="flex items-center gap-3 p-3 border-b border-gray-200 bg-gray-50">
                            <i className="fas fa-search text-gray-400 text-sm pl-2"></i>
                            <input
                                autoFocus
                                type="text"
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                onKeyDown={e => {
                                    if (e.key === 'Escape') setMobileSearchOpen(false);
                                    else onKeyDown(e);
                                }}
                                placeholder={t('Search pages, members...')}
                                className="flex-1 bg-transparent text-sm text-gray-800 placeholder:text-gray-400 outline-none"
                            />
                            <button
                                type="button"
                                onClick={() => { setMobileSearchOpen(false); setQuery(''); }}
                                className="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg"
                                aria-label="Close search"
                            >
                                <i className="fas fa-times"></i>
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-2">
                            {loading && (
                                <div className="flex items-center gap-2 px-3 py-4 text-sm text-gray-500">
                                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-emerald-600" />
                                    Searching...
                                </div>
                            )}

                            {!loading && query.trim().length >= 2 && flatItems.length === 0 && (
                                <p className="px-3 py-6 text-center text-sm text-gray-500">No results found.</p>
                            )}

                            {!loading && query.trim().length < 2 && (
                                <p className="px-3 py-6 text-center text-xs text-gray-400">Type at least 2 characters to search...</p>
                            )}

                            {!loading && groups.map(group => (
                                <div key={group.label}>
                                    <p className="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{group.label}</p>
                                    {group.items.map(item => (
                                        <button
                                            key={`mob-${group.label}-${(item as any).id ?? item.label}`}
                                            type="button"
                                            onClick={() => {
                                                goTo(item);
                                                setMobileSearchOpen(false);
                                            }}
                                            className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-emerald-50"
                                        >
                                            {item.icon && (
                                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm bg-gray-100 text-gray-500">
                                                    <i className={`fas fa-${item.icon}`} />
                                                </span>
                                            )}
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium text-gray-900">{item.label}</span>
                                                {item.subtitle && <span className="block truncate text-xs text-gray-500">{item.subtitle}</span>}
                                            </span>
                                            {item.meta && (
                                                <span className="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium capitalize text-gray-600">{item.meta}</span>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
