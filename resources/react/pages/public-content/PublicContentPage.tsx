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
        return <div className="space-y-4">{[...Array(3)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-xl bg-gray-100" />)}</div>;
    }

    return (
        <div className="space-y-6">
            <div className="rounded-2xl border bg-white shadow-sm overflow-hidden">
                <div className="bg-gradient-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white">
                    <p className="text-xs font-semibold uppercase tracking-wider text-white/70">Content Management</p>
                    <h1 className="text-xl font-bold mt-0.5">Public Content</h1>
                </div>
                {canManage && (
                    <div className="px-6 py-3 border-b border-gray-200 bg-gray-50/50 flex justify-end">
                        <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {saveMutation.isPending ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                )}
            </div>

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
                <div className="flex-1 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    {activeSection && currentSectionMeta && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold text-gray-900">{currentSectionMeta.label}</h2>
                            {currentSectionMeta.fields?.map((field: any) => (
                                <div key={field.path}>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">{field.label}</label>
                                    {field.control === 'textarea' || field.storage === 'json' ? (
                                        <textarea value={getFieldValue(activeSection, field.path)} onChange={e => setFieldValue(activeSection, field.path, e.target.value)} rows={4} className="w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 border" />
                                    ) : field.control === 'toggle' || field.storage === 'boolean' ? (
                                        <label className="flex items-center gap-2"><input type="checkbox" checked={getFieldValue(activeSection, field.path) === true || getFieldValue(activeSection, field.path) === '1'} onChange={e => setFieldValue(activeSection, field.path, e.target.checked)} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" /><span className="text-sm text-gray-500">Enabled</span></label>
                                    ) : (
                                        <input type={field.control || 'text'} value={getFieldValue(activeSection, field.path)} onChange={e => setFieldValue(activeSection, field.path, e.target.value)} className="w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 border" />
                                    )}
                                </div>
                            ))}
                            {currentSectionMeta.editor === 'cards' && (
                                <div className="space-y-3">
                                    <h3 className="font-medium text-gray-700">Cards</h3>
                                    {(form[activeSection]?.cards || []).map((card: any, i: number) => (
                                        <div key={i} className="rounded-lg border border-gray-200 p-3">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1 space-y-2">
                                                    <input placeholder="Name" value={card.name} onChange={e => { const cards = [...(form[activeSection]?.cards || [])]; cards[i] = { ...cards[i], name: e.target.value }; setForm(prev => ({ ...prev, [activeSection]: { ...prev[activeSection], cards } })); }} className="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                                    <input placeholder="Position" value={card.position} onChange={e => { const cards = [...(form[activeSection]?.cards || [])]; cards[i] = { ...cards[i], position: e.target.value }; setForm(prev => ({ ...prev, [activeSection]: { ...prev[activeSection], cards } })); }} className="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                                </div>
                                                <button onClick={() => removeCard(activeSection, i)} className="ml-2 text-red-400 hover:text-red-600"><i className="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    ))}
                                    <button onClick={() => addCard(activeSection)} className="rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm text-gray-500 hover:bg-gray-50"><i className="fas fa-plus mr-1"></i> Add Card</button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
