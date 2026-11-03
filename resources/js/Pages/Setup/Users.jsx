import Field from '@/Components/Field';
import { Badge, Button, Card, Drawer, Empty, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Accounts and roles. The school creates every account; people sign in with phone, username or email. */
export default function Users({ users, search, roleFilter, roles, staff, languages }) {
    const t = useT();
    const { locale } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [term, setTerm] = useState(search || '');
    const blank = { name: '', username: '', phone: '', email: '', role_id: '', language: 'rw', is_active: true, password: '', password_confirmation: '', staff_id: '' };
    const form = useForm(blank);

    useEffect(() => {
        if (term === (search || '')) return;
        const id = setTimeout(() => router.get(route('setup.users'), { search: term || undefined, role: roleFilter || undefined }, { preserveState: true, replace: true }), 350);
        return () => clearTimeout(id);
    }, [term]);

    const open = (u) => {
        form.clearErrors();
        form.setData(u ? { ...blank, ...Object.fromEntries(Object.keys(blank).map((k) => [k, u[k] ?? blank[k]])), password: '', password_confirmation: '', staff_id: '' } : blank);
        setEditing(u || {});
    };
    const save = () => {
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing.id ? form.put(route('setup.users.update', editing.id), options) : form.post(route('setup.users.store'), options);
    };

    // Picking a staff member fills the name and phone.
    const pickStaff = (id) => {
        const s = staff.find((x) => String(x.id) === String(id));
        form.setData((d) => ({ ...d, staff_id: id ?? '', name: s ? `${s.first_name} ${s.last_name}` : d.name, phone: s?.phone ? s.phone.slice(4) : d.phone }));
    };

    const f = (name, label, type = 'text', extra = {}) => <Field field={{ name, label, type, ...extra }} value={form.data[name]} onChange={(v) => form.setData(name, v)} error={form.errors[name]} />;

    return (
        <AppLayout title={t('Users and roles')}>
            <PageHeader title={t('Users and roles')} description={t('Create an account for each staff member, parent or student. There is no public sign-up.')}
                actions={<Button onClick={() => open(null)}><Plus className="h-4 w-4" />{t('Add account')}</Button>} />

            <Card>
                <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row dark:border-slate-800">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={t('Search…')} aria-label={t('Search')}
                            className="w-full rounded-lg border-slate-300 py-2 pl-9 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900" />
                    </div>
                    <select aria-label={t('Role')} value={roleFilter || ''} onChange={(e) => router.get(route('setup.users'), { search: term || undefined, role: e.target.value || undefined }, { preserveState: true })}
                        className="rounded-lg border-slate-300 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <option value="">{t('Role')}: {t('All')}</option>
                        {roles.map((r) => <option key={r.value} value={r.name}>{r.label}</option>)}
                    </select>
                </div>
                {users.data.length === 0 ? <Empty>{t('Nothing here yet.')}</Empty> : (
                    <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                        {users.data.map((u) => (
                            <li key={u.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold ${u.is_active ? 'bg-brand text-white' : 'bg-slate-200 text-slate-500'}`}>{u.name.charAt(0)}</span>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold">{u.name} {!u.is_active && <Badge tone="slate">{t('Disabled')}</Badge>}</p>
                                    <p className="truncate text-sm text-slate-500">{[u.username && `@${u.username}`, u.phone_display, u.email].filter(Boolean).join(' · ')}</p>
                                </div>
                                <Badge>{u.role_label}</Badge>
                                <span className="hidden w-40 text-right text-xs text-slate-400 md:block">
                                    {u.last_login_at ? t('Last sign-in :date', { date: new Date(u.last_login_at).toLocaleDateString(locale === 'rw' ? 'en-GB' : locale) }) : t('Never signed in')}
                                </span>
                                <button type="button" onClick={() => open(u)} className="rounded-lg p-2 text-slate-500 hover:bg-brand-soft hover:text-brand-dark" aria-label={t('Edit')}><Pencil className="h-4 w-4" /></button>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <Drawer open={editing !== null} title={editing?.id ? t('Edit account') : t('Add account')} onClose={() => setEditing(null)}
                footer={<><Button variant="secondary" onClick={() => setEditing(null)}>{t('Cancel')}</Button><Button onClick={save} disabled={form.processing}>{t('Save')}</Button></>}>
                <div className="grid gap-4 sm:grid-cols-2">
                    {staff.length > 0 && (
                        <div className="sm:col-span-2">
                            <Field field={{ name: 'staff_id', label: t('Link to a staff member'), type: 'select', options: staff.map((s) => ({ value: s.id, label: `${s.first_name} ${s.last_name}` })), help: t('Optional. Fills the name and phone.') }}
                                value={form.data.staff_id} onChange={pickStaff} />
                        </div>
                    )}
                    <div className="sm:col-span-2">{f('name', t('Full name'), 'text', { required: true })}</div>
                    {f('role_id', t('Role'), 'select', { required: true, options: roles })}
                    {f('language', t('Language'), 'select', { required: true, options: languages })}
                    <div className="sm:col-span-2">{f('phone', t('Phone'), 'phone')}</div>
                    {f('username', t('Username'), 'text', { placeholder: 'jean.uwimana' })}
                    {f('email', t('Email'), 'email')}
                    {f('password', editing?.id ? t('New password (leave empty to keep)') : t('Password'), 'password', { required: !editing?.id })}
                    {f('password_confirmation', t('Confirm password'), 'password', { required: !editing?.id })}
                    <div className="sm:col-span-2">{f('is_active', t('Account active (can sign in)'), 'checkbox')}</div>
                </div>
            </Drawer>
        </AppLayout>
    );
}
