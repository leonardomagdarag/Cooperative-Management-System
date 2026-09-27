import { useState } from 'react';
import type { FaqCategory, HelpContent } from '../../api/tickets';
import Button from '../../components/ui/Button';

interface FaqEditorItem {
    q: string;
    a: string;
    tags: string;
}

interface CategoryDraft {
    id: string;
    label: string;
    icon: string;
    description: string;
    faqs: FaqEditorItem[];
}

interface HelpKnowledgeBaseEditorProps {
    categories: FaqCategory[];
    content: HelpContent;
    isSaving: boolean;
    onSave: (categories: FaqCategory[], content: HelpContent) => void;
    onCancel: () => void;
}

const inputClass = 'w-full rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 focus:outline-none';
const labelClass = 'mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500';

function toDraft(categories: FaqCategory[]): CategoryDraft[] {
    return categories.map(cat => ({
        id: cat.id,
        label: cat.label,
        icon: cat.icon,
        description: cat.description,
        faqs: cat.faqs.map(f => ({ q: f.q, a: f.a, tags: (f.tags || []).join(', ') })),
    }));
}

function fromDraft(drafts: CategoryDraft[]): FaqCategory[] {
    return drafts.map(d => ({
        id: d.id,
        label: d.label,
        icon: d.icon,
        description: d.description,
        faqs: d.faqs.map(f => ({
            q: f.q,
            a: f.a,
            tags: f.tags.split(',').map(t => t.trim()).filter(Boolean),
        })),
    }));
}

export default function HelpKnowledgeBaseEditor({ categories, content, isSaving, onSave, onCancel }: HelpKnowledgeBaseEditorProps) {
    const [drafts, setDrafts] = useState<CategoryDraft[]>(() => toDraft(categories));
    const [copy, setCopy] = useState<HelpContent>(() => ({ ...content }));

    const setCategory = (index: number, patch: Partial<CategoryDraft>) => {
        setDrafts(prev => prev.map((c, i) => (i === index ? { ...c, ...patch } : c)));
    };

    const setFaq = (catIndex: number, faqIndex: number, patch: Partial<FaqEditorItem>) => {
        setDrafts(prev => prev.map((c, ci) => ci !== catIndex ? c : {
            ...c,
            faqs: c.faqs.map((f, fi) => (fi === faqIndex ? { ...f, ...patch } : f)),
        }));
    };

    const addFaq = (catIndex: number) => {
        setDrafts(prev => prev.map((c, ci) => ci !== catIndex ? c : { ...c, faqs: [...c.faqs, { q: '', a: '', tags: '' }] }));
    };

    const removeFaq = (catIndex: number, faqIndex: number) => {
        setDrafts(prev => prev.map((c, ci) => ci !== catIndex ? c : { ...c, faqs: c.faqs.filter((_, fi) => fi !== faqIndex) }));
    };

    const moveCategory = (index: number, direction: -1 | 1) => {
        setDrafts(prev => {
            const target = index + direction;
            if (target < 0 || target >= prev.length) return prev;
            const next = [...prev];
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
    };

    const addCategory = () => {
        setDrafts(prev => [...prev, {
            id: `category-${Date.now().toString(36)}`,
            label: '',
            icon: 'fa-circle-question',
            description: '',
            faqs: [],
        }]);
    };

    const removeCategory = (index: number) => {
        setDrafts(prev => prev.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-6">
            {/* Page copy */}
            <section className="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div className="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h3 className="text-sm font-semibold text-gray-800">Page copy</h3>
                    <p className="mt-0.5 text-xs text-gray-500">Header description and the support callout shown at the bottom of the FAQs tab.</p>
                </div>
                <div className="grid grid-cols-1 gap-5 p-6 lg:grid-cols-2">
                    <div className="lg:col-span-2">
                        <label className={labelClass}>Header description</label>
                        <textarea
                            rows={3}
                            value={copy.header_description}
                            onChange={e => setCopy(prev => ({ ...prev, header_description: e.target.value }))}
                            className={inputClass}
                            placeholder="Explore system guides, search frequently asked questions..."
                        />
                    </div>
                    <div>
                        <label className={labelClass}>Callout title</label>
                        <input
                            type="text"
                            value={copy.callout_title}
                            onChange={e => setCopy(prev => ({ ...prev, callout_title: e.target.value }))}
                            className={inputClass}
                            placeholder="Still need assistance?"
                        />
                    </div>
                    <div>
                        <label className={labelClass}>Callout description</label>
                        <textarea
                            rows={3}
                            value={copy.callout_description}
                            onChange={e => setCopy(prev => ({ ...prev, callout_description: e.target.value }))}
                            className={inputClass}
                            placeholder="Our support team and officers are ready to help..."
                        />
                    </div>
                </div>
            </section>

            {/* Categories */}
            <section className="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div className="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4">
                    <div>
                        <h3 className="text-sm font-semibold text-gray-800">FAQ categories</h3>
                        <p className="mt-0.5 text-xs text-gray-500">
                            Categories, questions, answers, and searchable tags. Order categories with the arrows.
                        </p>
                    </div>
                    <Button variant="secondary" size="sm" onClick={addCategory} disabled={isSaving}>
                        <i className="fas fa-plus mr-1.5"></i> Add Category
                    </Button>
                </div>

                <div className="space-y-6 p-6">
                    {drafts.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-gray-300 px-6 py-12 text-center">
                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100">
                                <i className="fas fa-circle-question text-2xl text-gray-400"></i>
                            </div>
                            <p className="text-sm font-semibold text-gray-700">No categories yet</p>
                            <p className="text-sm text-gray-400">Add a category to start building your knowledge base.</p>
                        </div>
                    ) : (
                        drafts.map((cat, ci) => (
                            <div key={cat.id || ci} className="rounded-2xl border border-gray-200 overflow-hidden">
                                <div className="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50/70 px-5 py-3">
                                    <div className="flex items-center gap-2">
                                        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">{ci + 1}</span>
                                        <span className="text-sm font-semibold text-gray-800">{cat.label || 'Untitled category'}</span>
                                        <span className="font-mono text-[11px] text-gray-400">({cat.id})</span>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            onClick={() => moveCategory(ci, -1)}
                                            disabled={ci === 0 || isSaving}
                                            className="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-40"
                                            aria-label="Move category up"
                                        >
                                            <i className="fas fa-chevron-up text-xs"></i>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => moveCategory(ci, 1)}
                                            disabled={ci === drafts.length - 1 || isSaving}
                                            className="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-40"
                                            aria-label="Move category down"
                                        >
                                            <i className="fas fa-chevron-down text-xs"></i>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => removeCategory(ci)}
                                            disabled={isSaving}
                                            className="rounded-lg border border-red-200 p-1.5 text-red-500 hover:bg-red-50 disabled:opacity-40"
                                            aria-label="Remove category"
                                        >
                                            <i className="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>

                                <div className="space-y-4 p-5">
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <label className={labelClass}>Label</label>
                                            <input
                                                type="text"
                                                value={cat.label}
                                                onChange={e => setCategory(ci, { label: e.target.value })}
                                                placeholder="e.g. Membership & Onboarding"
                                                className={inputClass}
                                            />
                                        </div>
                                        <div>
                                            <label className={labelClass}>Icon (Font Awesome)</label>
                                            <input
                                                type="text"
                                                value={cat.icon}
                                                onChange={e => setCategory(ci, { icon: e.target.value })}
                                                placeholder="e.g. fa-id-card"
                                                className={inputClass}
                                            />
                                        </div>
                                        <div>
                                            <label className={labelClass}>Category ID</label>
                                            <input
                                                type="text"
                                                value={cat.id}
                                                onChange={e => setCategory(ci, { id: e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '-') })}
                                                placeholder="e.g. membership"
                                                className={inputClass}
                                            />
                                        </div>
                                        <div className="sm:col-span-3">
                                            <label className={labelClass}>Description</label>
                                            <input
                                                type="text"
                                                value={cat.description}
                                                onChange={e => setCategory(ci, { description: e.target.value })}
                                                placeholder="Short summary shown under the category title"
                                                className={inputClass}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-3">
                                        {cat.faqs.map((faq, fi) => (
                                            <div key={fi} className="rounded-xl border border-gray-200 p-4">
                                                <div className="flex items-center justify-between gap-3">
                                                    <label className={labelClass}>Question {fi + 1}</label>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeFaq(ci, fi)}
                                                        disabled={isSaving}
                                                        className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-40"
                                                    >
                                                        <i className="fas fa-times text-[10px]"></i> Remove
                                                    </button>
                                                </div>
                                                <input
                                                    type="text"
                                                    value={faq.q}
                                                    onChange={e => setFaq(ci, fi, { q: e.target.value })}
                                                    placeholder="Question"
                                                    className={inputClass}
                                                />
                                                <label className={`${labelClass} mt-3`}>Answer</label>
                                                <textarea
                                                    rows={4}
                                                    value={faq.a}
                                                    onChange={e => setFaq(ci, fi, { a: e.target.value })}
                                                    placeholder="Answer"
                                                    className={inputClass}
                                                />
                                                <label className={`${labelClass} mt-3`}>Tags (comma separated)</label>
                                                <input
                                                    type="text"
                                                    value={faq.tags}
                                                    onChange={e => setFaq(ci, fi, { tags: e.target.value })}
                                                    placeholder="withdrawal, otp, security"
                                                    className={inputClass}
                                                />
                                            </div>
                                        ))}
                                        <Button variant="secondary" size="sm" onClick={() => addFaq(ci)} disabled={isSaving}>
                                            <i className="fas fa-plus mr-1.5"></i> Add Question
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </section>

            {/* Footer actions */}
            <div className="flex justify-end gap-3 rounded-2xl border border-gray-200 bg-white px-6 py-4 shadow-sm">
                <Button variant="secondary" size="md" onClick={onCancel} disabled={isSaving}>
                    Cancel
                </Button>
                <Button
                    variant="primary"
                    size="md"
                    isLoading={isSaving}
                    loadingText="Saving..."
                    onClick={() => onSave(fromDraft(drafts), copy)}
                >
                    <i className="fas fa-check mr-1.5"></i> Save Changes
                </Button>
            </div>
        </div>
    );
}