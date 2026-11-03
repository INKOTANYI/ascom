import Field from '@/Components/Field';
import { Badge, Button, Card, Drawer, Empty, PageHeader, Pagination } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { MessageSquare, Pencil, Phone, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Parents and guardians with their children. One record per parent, shared by brothers and sisters. */
export default function Guardians({ guardians, search, noPhone, canManage }) {
    const t = useT();
    const [term, setTerm] = useState(search || '');
    const [editing, setEditing] = useState(null);
    const form = useForm({ first_name: '', last_name: '', phone: '', email: '', national_id: '', occupation: '' });

    const go = (params) => router.get(route('guardians.index'), Object.fromEntries(Object.entries({ search: term, no_phone: noPhone ? 1 : null, ...params }).filter(([, v]) => v)), { preserveState: true, replace: true });

    useEffect(() => {
        if (term === (search || '')) return;
        const id = setTimeout(() => go({ search: term }), 350);
        return () => clearTimeout(id);
    }, [term]);

    const open = (g) => {
        form.clearErrors();
        form.setData({ first_name: g.first_name, last_name: g.last_name, phone: g.phone, email: g.email, national_id: g.national_id, occupation: g.occupation });
        setEditing(g);
    };
    const f = (name, label, type = 'text', extra = {}) => <Field field={{ name, label, type, ...extra }} value={form.data[name]} onChange={(v) => form.setData(name, v ?? '')} error={form.errors[name]} />;

    return (
        <AppLayout title={t('Parents and guardians')}>
            <PageHeader title={t('Parents and guardians')} description={t('Each parent is registered once, even with several children at the school. The main contact receives the SMS.')} />

            <Card>
                <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center dark:border-slate-800">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={t('Parent name, phone or child’s name…')} aria-label={t('Search')}
                            className="w-full rounded-lg border-slate-300 py-2 pl-9 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900" />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={noPhone} onChange={(e) => go({ no_phone: e.target.checked ? 1 : null })} className="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand" />
                        {t('Only parents without a phone')}
                    </label>
                </div>
                <p className="px-4 pt-3 text-sm text-slate-500 dark:text-slate-400">{t('Records: :n', { n: guardians.total })}</p>

                {guardians.data.length === 0 ? <Empty>{search ? t('Nothing matches this search.') : t('Parents are added with their children on the student form or by importing an Excel list.')}</Empty> : (
                    <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                        {guardians.data.map((g) => (
                            <li key={g.id} className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start">
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold">{g.full_name}</p>
                                    <p className="mt-0.5 flex flex-wrap items-center gap-x-3 text-sm text-slate-500 dark:text-slate-400">
                                        {g.phone_display
                                            ? <a href={`tel:${g.phone_display.replace(/\s/g, '')}`} className="inline-flex items-center gap-1 font-medium text-brand hover:underline"><Phone className="h-3.5 w-3.5" />{g.phone_display}</a>
                                            : <span className="text-amber-700 dark:text-amber-400">{t('No phone')}</span>}
                                        {g.email && <span>{g.email}</span>}
                                        {g.occupation && <span>{g.occupation}</span>}
                                    </p>
                                </div>
                                <ul className="flex flex-wrap gap-2 sm:max-w-md sm:justify-end">
                                    {g.children.map((c) => (
                                        <li key={c.id}>
                                            <Link href={route('students.show', c.id)} className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1 text-xs hover:border-brand dark:border-slate-700">
                                                {c.is_primary && <MessageSquare className="h-3 w-3 text-brand" aria-label={t('Receives SMS')} />}
                                                <span className="font-semibold">{c.name}</span>
                                                {c.class && <span className="text-slate-500">{c.class}</span>}
                                                {c.status !== 'active' && <Badge tone="slate">{c.status}</Badge>}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                                {canManage && (
                                    <button type="button" onClick={() => open(g)} className="self-start rounded-lg p-2 text-slate-500 hover:bg-brand-soft hover:text-brand-dark" aria-label={t('Edit')}>
                                        <Pencil className="h-4 w-4" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination paginator={guardians} />
            </Card>

            <Drawer open={editing !== null} title={t('Edit parent')} onClose={() => setEditing(null)}
                footer={<><Button variant="secondary" onClick={() => setEditing(null)}>{t('Cancel')}</Button>
                    <Button disabled={form.processing} onClick={() => form.put(route('guardians.update', editing.id), { preserveScroll: true, onSuccess: () => setEditing(null) })}>{t('Save')}</Button></>}>
                <div className="grid gap-4 sm:grid-cols-2">
                    {f('first_name', t('First name'), 'text', { required: true })}
                    {f('last_name', t('Last name'))}
                    <div className="sm:col-span-2">{f('phone', t('Phone'), 'phone')}</div>
                    {f('email', t('Email'), 'email')}
                    {f('occupation', t('Occupation'))}
                    {f('national_id', t('National ID'))}
                    {editing?.children.length > 1 && <p className="text-sm text-slate-500 sm:col-span-2">{t('This parent is shared by :n students; the change applies to all of them.', { n: editing.children.length })}</p>}
                </div>
            </Drawer>
        </AppLayout>
    );
}
