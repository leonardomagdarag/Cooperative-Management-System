import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import SettingsTabHeader from '../settings/SettingsTabHeader';

function getByPath(obj: any, path: string): any {
    return path.split('.').reduce((acc, key) => (acc == null ? undefined : acc[key]), obj);
}

function setByPath(obj: any, path: string, value: any): any {
    const keys = path.split('.');
    const clone: any = JSON.parse(JSON.stringify(obj));
    let cur = clone;
    for (let i = 0; i < keys.length - 1; i++) {
        const k = keys[i];
        if (cur[k] == null || typeof cur[k] !== 'object') cur[k] = {};
        cur = cur[k];
    }
    cur[keys[keys.length - 1]] = value;
    return clone;
}

function Switch({ checked, onChange, disabled }: { checked: boolean; onChange: (v: boolean) => void; disabled?: boolean }) {
    return (
        <label className={`inline-flex cursor-pointer items-center gap-3 ${disabled ? 'opacity-60' : ''}`}>
            <span className={`text-sm font-medium ${checked ? 'text-emerald-700' : 'text-gray-500'}`}>
                {checked ? 'Enabled' : 'Disabled'}
            </span>
            <div className="relative">
                <input type="checkbox" checked={checked} disabled={disabled} onChange={e => onChange(e.target.checked)} className="peer sr-only" />
                <div className="h-6 w-11 rounded-full border-2 border-transparent bg-gray-200 transition-colors peer-checked:bg-emerald-500 peer-focus:ring-4 peer-focus:ring-emerald-200 peer-disabled:opacity-50"></div>
                <div className="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></div>
            </div>
        </label>
    );
}

function cardImageSrc(image: string): string | null {
    if (!image) return null;
    if (/^https?:\/\//i.test(image)) return image;
    return '/' + image.replace(/^\/+/, '');
}

export default function PublicContentPage() {
    const [activeSection, setActiveSection] = useState('');
    const [form, setForm] = useState<Record<string, any>>({});
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['public-content', 'editor'],
        queryFn: () => api.get('/api/public-content'),
    });

    const saveMutation = useMutation({
        mutationFn: () => api.put('/api/public-content', { form }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['public-content'] }); addToast('Public content updated successfully.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const sections = data?.data?.sections || {};
    const canManage = data?.data?.can_manage === true;

    useEffect(() => {
        const loaded = data?.data?.form;
        if (!loaded) return;

        if (Object.keys(form).length === 0) {
            setForm(loaded);
        }

        // The editor schema (sections) is admin-only and may arrive a moment after
        // a cached payload (e.g. a guest response still cached from the login
        // page). Attach the first section as soon as the schema is available.
        const sectionKeys = Object.keys(data.data.sections || {});
        if (!activeSection && sectionKeys.length > 0) {
            setActiveSection(sectionKeys[0]);
        }
    }, [data, form, activeSection]);

    const sectionKeys = Object.keys(sections);
    const meta = sections[activeSection] || {};
    const sectionValue = (path: string) => getByPath(form[activeSection], path) ?? '';
    const setSectionValue = (path: string, value: any) => setForm(prev => ({ ...prev, [activeSection]: setByPath(prev[activeSection], path, value) }));

    const setCardField = (index: number, field: string, value: string) => {
        const cards = [...(getByPath(form[activeSection], 'cards') || [])];
        cards[index] = { ...cards[index], [field]: value };
        setSectionValue('cards', cards);
    };

    const addCard = () => {
        const cards = getByPath(form[activeSection], 'cards') || [];
        setSectionValue('cards', [...cards, { name: '', position: '', image_url: '' }]);
    };

    const removeCard = (index: number) => {
        const cards = [...(getByPath(form[activeSection], 'cards') || [])];
        cards.splice(index, 1);
        setSectionValue('cards', cards);
    };

    if (isLoading) {
        return <div className="space-y-4">{[...Array(3)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-xl bg-gray-100" />)}</div>;
    }

    const fields = (meta.groups || []).flatMap((g: any) => g.fields || []).filter((f: any) => !f.hidden);

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                eyebrow="Content management"
                title="Public Content"
                description="Update the public website content and publish changes immediately."
                icon="fas fa-globe"
                action={canManage ? <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">{saveMutation.isPending ? 'Saving...' : 'Save Changes'}</button> : undefined}
            />

            {!canManage && (
                <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <i className="fas fa-exclamation-triangle mt-0.5 text-amber-500"></i>
                    <p className="text-sm text-amber-800">Only admins can update public website content. You currently have view-only access.</p>
                </div>
            )}

            <div className="flex gap-6">
                {/* Section Tabs */}
                <div className="w-48 shrink-0">
                    <nav className="space-y-1">
                        {sectionKeys.map(key => (
                            <button key={key} onClick={() => setActiveSection(key)} className={`w-full rounded-xl border px-3 py-2.5 text-left text-sm font-medium transition ${activeSection === key ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-transparent text-gray-500 hover:bg-gray-50'}`}>
                                {sections[key]?.label || key}
                            </button>
                        ))}
                    </nav>
                </div>

                {/* Section Content */}
                <div className="flex-1 space-y-4">
                    {activeSection && meta && (
                        <>
                            <div className="flex items-center gap-4 rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                                    <i className="fas fa-list-alt text-emerald-700"></i>
                                </div>
                                <div>
                                    <h2 className="text-lg font-bold text-gray-900">{meta.label}</h2>
                                    <p className="text-sm text-gray-500">{meta.description || 'Edit the fields below and save.'}</p>
                                </div>
                            </div>

                            {meta.editor === 'cards' ? (
                                <CardsEditor
                                    meta={meta}
                                    cards={getByPath(form[activeSection], 'cards') || []}
                                    isVisible={getByPath(form[activeSection], 'is_visible') !== false}
                                    canManage={canManage}
                                    onToggle={() => setSectionValue('is_visible', !(getByPath(form[activeSection], 'is_visible') !== false))}
                                    onField={setCardField}
                                    onAdd={addCard}
                                    onRemove={removeCard}
                                />
                            ) : (
                                (meta.groups || []).map((group: any, gi: number) => (
                                    <section key={gi} className="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                                        {group.title && (
                                            <div className="border-b border-gray-100 bg-gray-50 px-6 py-4">
                                                <h3 className="text-sm font-semibold text-gray-800">{group.title}</h3>
                                                {group.description && <p className="mt-0.5 text-xs text-gray-500">{group.description}</p>}
                                            </div>
                                        )}
                                        <div className="grid grid-cols-1 gap-5 p-6 lg:grid-cols-2">
                                            {(group.fields || []).map((field: any) => (
                                                <div key={field.path} className={`${(field.columnSpan ?? 1) === 2 ? 'lg:col-span-2' : ''} ${field.hidden ? 'hidden' : ''}`}>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">{field.label}</label>
                                                    {(field.control || 'text') === 'toggle' || field.storage === 'boolean' ? (
                                                        <Switch
                                                            checked={sectionValue(field.path) === true || sectionValue(field.path) === '1'}
                                                            disabled={!canManage}
                                                            onChange={v => setSectionValue(field.path, v)}
                                                        />
                                                    ) : (field.control || 'text') === 'textarea' ? (
                                                        <textarea
                                                            rows={field.rows ?? 4}
                                                            value={sectionValue(field.path) ?? ''}
                                                            disabled={!canManage}
                                                            onChange={e => setSectionValue(field.path, e.target.value)}
                                                            className="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400"
                                                        />
                                                    ) : (
                                                        <input
                                                            type={field.control || 'text'}
                                                            value={sectionValue(field.path) ?? ''}
                                                            disabled={!canManage}
                                                            onChange={e => setSectionValue(field.path, e.target.value)}
                                                            className="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400"
                                                        />
                                                    )}
                                                    {field.description && <p className="mt-1.5 text-xs text-gray-400">{field.description}</p>}
                                                </div>
                                            ))}
                                        </div>
                                    </section>
                                ))
                            )}
                        </>
                    )}
                </div>
            </div>

            {canManage && (
                <div className="flex justify-end rounded-2xl border border-gray-200 bg-white px-6 py-4 shadow-sm">
                    <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:opacity-60">
                        <i className={`fas ${saveMutation.isPending ? 'fa-spinner fa-spin' : 'fa-check'} text-sm`}></i>
                        {saveMutation.isPending ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>
            )}
        </div>
    );
}

function CardsEditor({ meta, cards, isVisible, canManage, onToggle, onField, onAdd, onRemove }: {
    meta: any;
    cards: any[];
    isVisible: boolean;
    canManage: boolean;
    onToggle: () => void;
    onField: (index: number, field: string, value: string) => void;
    onAdd: () => void;
    onRemove: (index: number) => void;
}) {
    return (
        <>
            {/* Visibility toggle */}
            <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition-colors ${isVisible ? 'bg-emerald-100' : 'bg-gray-100'}`}>
                            <i className={`fas fa-eye text-xl ${isVisible ? 'text-emerald-600' : 'text-gray-400'}`}></i>
                        </div>
                        <div>
                            <h4 className="text-base font-semibold text-gray-900">Section visibility</h4>
                            <p className="mt-0.5 text-sm text-gray-500">{meta.toggle_description || 'Control whether this section is shown on the public page.'}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className={`text-sm font-medium ${isVisible ? 'text-emerald-700' : 'text-gray-500'}`}>{isVisible ? 'Visible' : 'Hidden'}</span>
                        <div className="relative">
                            <input type="checkbox" checked={isVisible} disabled={!canManage} onChange={onToggle} className="peer sr-only" />
                            <div className="h-6 w-11 rounded-full border-2 border-transparent bg-gray-200 transition-colors peer-checked:bg-emerald-500 peer-focus:ring-4 peer-focus:ring-emerald-200 peer-disabled:opacity-50"></div>
                            <div className="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Card list */}
            <div className="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div className="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4">
                    <div>
                        <h4 className="text-base font-semibold text-gray-900">
                            {meta.card_singular || 'Card'} cards
                            <span className="ml-2 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{cards.length}</span>
                        </h4>
                        <p className="mt-0.5 text-sm text-gray-500">
                            Use an absolute URL or a relative public path — e.g. <code className="rounded bg-gray-100 px-1 text-xs">images/board/name.jpg</code>
                        </p>
                    </div>
                    {canManage && (
                        <button onClick={onAdd} className="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                            <i className="fas fa-plus text-sm"></i>
                            Add {meta.card_singular || 'Card'}
                        </button>
                    )}
                </div>

                <div className="divide-y divide-gray-100">
                    {cards.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100">
                                <i className="fas fa-users text-2xl text-gray-400"></i>
                            </div>
                            <p className="text-base font-semibold text-gray-700">{meta.empty_state || 'No cards added yet.'}</p>
                            <p className="text-sm text-gray-400">Add a card using the button above to start building this public section.</p>
                        </div>
                    ) : (
                        cards.map((card, i) => {
                            const displayName = card.name || `${meta.card_singular || 'Card'} ${i + 1}`;
                            const imageSrc = cardImageSrc(card.image_url);
                            return (
                                <div key={i} className="p-6">
                                    <div className="mb-5 flex items-start justify-between gap-4">
                                        <div className="flex items-center gap-3">
                                            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">{i + 1}</span>
                                            <div>
                                                <p className="text-sm font-semibold text-gray-900">{displayName}</p>
                                                <p className="text-xs text-gray-500">{card.position || 'No position set yet'}</p>
                                            </div>
                                        </div>
                                        {canManage && (
                                            <button onClick={() => onRemove(i)} className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-100">
                                                <i className="fas fa-times text-xs"></i>
                                                Remove
                                            </button>
                                        )}
                                    </div>

                                    <div className="grid gap-5 xl:grid-cols-[200px_minmax(0,1fr)]">
                                        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                                            {imageSrc ? (
                                                <img src={imageSrc} alt={displayName} className="h-52 w-full object-cover" loading="lazy" decoding="async" />
                                            ) : (
                                                <div className="flex h-52 items-center justify-center bg-gradient-to-br from-slate-700 to-emerald-700">
                                                    <i className="fas fa-user text-5xl text-white/60"></i>
                                                </div>
                                            )}
                                            <div className="border-t border-gray-100 px-4 py-3">
                                                <p className="truncate text-sm font-semibold text-gray-900">{displayName}</p>
                                                <p className="truncate text-xs text-emerald-600">{card.position || '—'}</p>
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Name</label>
                                                    <input type="text" value={card.name} disabled={!canManage} onChange={e => onField(i, 'name', e.target.value)} placeholder="Full name" className="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400" />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Position / Title</label>
                                                    <input type="text" value={card.position} disabled={!canManage} onChange={e => onField(i, 'position', e.target.value)} placeholder="e.g. Chairperson" className="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400" />
                                                </div>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Image path or URL</label>
                                                <input type="text" value={card.image_url} disabled={!canManage} onChange={e => onField(i, 'image_url', e.target.value)} placeholder="images/board/member.jpg or https://…" className="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400" />
                                                <p className="mt-1.5 text-xs text-gray-400">Accepts a relative public path or any absolute URL. Leave blank to show the default avatar.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </>
    );
}
