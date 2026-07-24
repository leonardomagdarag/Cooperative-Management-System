import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function PublicContentPage() {
    const [activeSection, setActiveSection] = useState('');
    const [form, setForm] = useState<Record<string, any>>({});
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['public-content'],
        queryFn: () => api.get('/api/public-content'),
    });

    const saveMutation = useMutation({
        mutationFn: () => api.put('/api/public-content', { form }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['public-content'] }); addToast('Content updated', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const sections = data?.data?.sections || {};
    const canManage = data?.data?.can_manage;

    // Initialize form from data when sections load
    if (Object.keys(form).length === 0 && data?.data?.form) {
        setForm(data.data.form);
        const firstSection = Object.keys(sections)[0];
        if (firstSection) setActiveSection(firstSection);
    }

    const sectionKeys = Object.keys(sections);
    const currentSectionMeta = sections[activeSection];

    const getFieldValue = (section: string, path: string) => {
        return form[section]?.[path] ?? '';
    };

    const setFieldValue = (section: string, path: string, value: any) => {
        setForm(prev => ({ ...prev, [section]: { ...prev[section], [path]: value } }));
    };

    const addCard = (section: string) => {
        const cards = form[section]?.cards || [];
        setForm(prev => ({ ...prev, [section]: { ...prev[section], cards: [...cards, { name: '', position: '', image_url: '' }] } }));
    };

    const removeCard = (section: string, index: number) => {
        const cards = [...(form[section]?.cards || [])];
        cards.splice(index, 1);
        setForm(prev => ({ ...prev, [section]: { ...prev[section], cards } }));
    };

    if (isLoading) {
        return <div className="space-y-4">{[...Array(3)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}</div>;
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Public Content</h1>
                {canManage && (
                    <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        {saveMutation.isPending ? 'Saving...' : 'Save Changes'}
                    </button>
                )}
            </div>

            <div className="flex gap-6">
                {/* Section Tabs */}
                <div className="w-48 shrink-0">
                    <nav className="space-y-1">
                        {sectionKeys.map(key => (
                            <button key={key} onClick={() => setActiveSection(key)} className={`w-full rounded-lg px-3 py-2 text-left text-sm font-medium ${activeSection === key ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700'}`}>
                                {sections[key]?.label || key}
                            </button>
                        ))}
                    </nav>
                </div>

                {/* Section Content */}
                <div className="flex-1 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                    {activeSection && currentSectionMeta && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">{currentSectionMeta.label}</h2>
                            {currentSectionMeta.fields?.map((field: any) => (
                                <div key={field.path}>
                                    <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{field.label}</label>
                                    {field.control === 'textarea' || field.storage === 'json' ? (
                                        <textarea value={getFieldValue(activeSection, field.path)} onChange={e => setFieldValue(activeSection, field.path, e.target.value)} rows={4} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                                    ) : field.control === 'toggle' || field.storage === 'boolean' ? (
                                        <label className="flex items-center gap-2"><input type="checkbox" checked={getFieldValue(activeSection, field.path) === true || getFieldValue(activeSection, field.path) === '1'} onChange={e => setFieldValue(activeSection, field.path, e.target.checked)} className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" /><span className="text-sm text-slate-500">Enabled</span></label>
                                    ) : (
                                        <input type={field.control || 'text'} value={getFieldValue(activeSection, field.path)} onChange={e => setFieldValue(activeSection, field.path, e.target.value)} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                                    )}
                                </div>
                            ))}
                            {currentSectionMeta.editor === 'cards' && (
                                <div className="space-y-3">
                                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Cards</h3>
                                    {(form[activeSection]?.cards || []).map((card: any, i: number) => (
                                        <div key={i} className="rounded-lg border border-slate-200 p-3 dark:border-slate-600">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1 space-y-2">
                                                    <input placeholder="Name" value={card.name} onChange={e => { const cards = [...(form[activeSection]?.cards || [])]; cards[i] = { ...cards[i], name: e.target.value }; setForm(prev => ({ ...prev, [activeSection]: { ...prev[activeSection], cards } })); }} className="w-full rounded border border-slate-200 px-2 py-1 text-sm dark:border-slate-600 dark:bg-slate-700" />
                                                    <input placeholder="Position" value={card.position} onChange={e => { const cards = [...(form[activeSection]?.cards || [])]; cards[i] = { ...cards[i], position: e.target.value }; setForm(prev => ({ ...prev, [activeSection]: { ...prev[activeSection], cards } })); }} className="w-full rounded border border-slate-200 px-2 py-1 text-sm dark:border-slate-600 dark:bg-slate-700" />
                                                </div>
                                                <button onClick={() => removeCard(activeSection, i)} className="ml-2 text-red-400 hover:text-red-600"><i className="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    ))}
                                    <button onClick={() => addCard(activeSection)} className="rounded-lg border border-dashed border-slate-300 px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700"><i className="fas fa-plus mr-1"></i> Add Card</button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
