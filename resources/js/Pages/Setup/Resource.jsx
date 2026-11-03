import Field from '@/Components/Field';
import { Badge, Button, Card, Drawer, Empty, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Generic list + add/edit screen for the simple setup tables (levels, subjects, staff, ...). */
function Cell({ column, row }) {
    const value = row[column.key];
    if (column.type === 'bool') {
        return value ? <Check className="h-4 w-4 text-brand" aria-label="yes" /> : <X className="h-4 w-4 text-slate-300" aria-label="no" />;
    }
    if (value === null || value === undefined || value === '') return <span className="text-slate-300">—</span>;
    if (column.type === 'badge') return <Badge>{value}</Badge>;
    if (column.type === 'code') return <code className="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold dark:bg-slate-800">{value}</code>;
    if (column.type === 'strong') return <span className="font-semibold">{value}</span>;
    return <span>{value}</span>;
}

export default function Resource({ title, description, routeName, columns, fields, records, search, canManage, filters = [] }) {
    const t = useT();
    const [editing, setEditing] = useState(null); // null = closed, {} = new, row = edit
    const [term, setTerm] = useState(search || '');
    const blank = Object.fromEntries(fields.map((f) => [f.name, f.default ?? (f.type === 'checkbox' ? false : '')]));
    const form = useForm(blank);

    // Search as you type (no page reload).
    useEffect(() => {
        if (term === (search || '')) return;
        const id = setTimeout(() => router.get(route(routeName), { ...currentFilters(), search: term || undefined }, { preserveState: true, replace: true }), 350);
        return () => clearTimeout(id);
    }, [term]);

    const currentFilters = () => Object.fromEntries(filters.filter((f) => f.value).map((f) => [f.name, f.value]));

    const open = (row) => {
        form.clearErrors();
        form.setData(row ? Object.fromEntries(fields.map((f) => [f.name, row[f.name] ?? (f.type === 'checkbox' ? false : '')])) : blank);
        setEditing(row || {});
    };

    const save = (e) => {
        e?.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing.id ? form.put(route(`${routeName}.update`, editing.id), options) : form.post(route(`${routeName}.store`), options);
    };

    const remove = (row) => {
        if (confirm(t('Delete this record?'))) router.delete(route(`${routeName}.destroy`, row.id), { preserveScroll: true });
    };

    return (
        <AppLayout title={title}>
            <PageHeader
                title={title}
                description={description}
                actions={canManage && <Button onClick={() => open(null)}><Plus className="h-4 w-4" />{t('Add')}</Button>}
            />

            <Card>
                <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center dark:border-slate-800">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={t('Search…')} aria-label={t('Search')}
                            className="w-full rounded-lg border-slate-300 py-2 pl-9 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900" />
                    </div>
                    {filters.map((f) => (
                        <select key={f.name} aria-label={f.label} value={f.value || ''}
                            onChange={(e) => router.get(route(routeName), { ...currentFilters(), [f.name]: e.target.value || undefined, search: term || undefined }, { preserveState: true, replace: true })}
                            className="rounded-lg border-slate-300 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option value="">{f.label}: {t('All')}</option>
                            {f.options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                    ))}
                    <span className="text-sm text-slate-500">{t('Records: :n', { n: records.total })}</span>
                </div>

                {records.data.length === 0 ? (
                    <Empty>{search ? t('Nothing matches this search.') : t('Nothing here yet.')}</Empty>
                ) : (
                    <div className="relative overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                <tr>
                                    {columns.map((c) => <th key={c.key} scope="col" className="px-4 py-3">{c.label}</th>)}
                                    {canManage && <th scope="col" className="px-4 py-3 text-right"><span className="sr-only">{t('Actions')}</span></th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {records.data.map((row) => (
                                    <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                        {columns.map((c) => <td key={c.key} className="px-4 py-3"><Cell column={c} row={row} /></td>)}
                                        {canManage && (
                                            <td className="whitespace-nowrap px-4 py-2 text-right">
                                                <button type="button" onClick={() => open(row)} className="rounded-lg p-2 text-slate-500 hover:bg-brand-soft hover:text-brand-dark" aria-label={t('Edit')}><Pencil className="h-4 w-4" /></button>
                                                <button type="button" onClick={() => remove(row)} className="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" aria-label={t('Delete')}><Trash2 className="h-4 w-4" /></button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {records.last_page > 1 && (
                    <nav className="flex flex-wrap justify-center gap-1 border-t border-slate-200 p-3 dark:border-slate-800" aria-label={t('Pages')}>
                        {records.links.map((l, i) => (
                            <button key={i} type="button" disabled={!l.url} onClick={() => l.url && router.get(l.url, {}, { preserveState: true })}
                                className={`min-w-9 rounded-lg px-3 py-1.5 text-sm ${l.active ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </nav>
                )}
            </Card>

            <Drawer
                open={editing !== null}
                title={editing?.id ? t('Edit') : t('Add')}
                onClose={() => setEditing(null)}
                footer={<>
                    <Button variant="secondary" onClick={() => setEditing(null)}>{t('Cancel')}</Button>
                    <Button onClick={save} disabled={form.processing}>{form.processing ? t('Saving…') : t('Save')}</Button>
                </>}
            >
                <form onSubmit={save} className="grid gap-4 sm:grid-cols-2">
                    {fields.map((f) => (
                        <div key={f.name} className={f.width === 'full' || f.type === 'checkbox' || f.type === 'textarea' ? 'sm:col-span-2' : ''}>
                            <Field field={f} value={form.data[f.name]} onChange={(v) => form.setData(f.name, v)} error={form.errors[f.name]} />
                        </div>
                    ))}
                    <button type="submit" className="hidden" />
                </form>
            </Drawer>
        </AppLayout>
    );
}
