import Field from '@/Components/Field';
import { Badge, Button, Card, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { KeyRound, Save, UserRound } from 'lucide-react';

/** The signed-in person's own account: contact details, language and password. */
export default function Edit({ profile, languages }) {
    const t = useT();
    const info = useForm({ name: profile.name, phone: profile.phone, email: profile.email ?? '', language: profile.language });
    const pass = useForm({ current_password: '', password: '', password_confirmation: '' });

    const field = (form, name, label, type = 'text', extra = {}) => (
        <Field field={{ name, label, type, ...extra }} value={form.data[name]} onChange={(v) => form.setData(name, v)} error={form.errors[name]} />
    );

    return (
        <AppLayout title={t('My profile')}>
            <PageHeader title={t('My profile')} description={t('You sign in with your phone number, username or email.')} />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card className="p-6">
                    <form onSubmit={(e) => { e.preventDefault(); info.patch(route('profile.update'), { preserveScroll: true }); }} className="space-y-4">
                        <div className="flex items-center gap-3">
                            <UserRound className="h-5 w-5 text-brand" />
                            <h2 className="text-lg font-bold">{t('Account details')}</h2>
                            {profile.role_label && <Badge>{profile.role_label}</Badge>}
                        </div>
                        {profile.username && <p className="text-sm text-slate-500">{t('Username')}: <span className="font-semibold">@{profile.username}</span></p>}
                        {field(info, 'name', t('Full name'), 'text', { required: true })}
                        {field(info, 'phone', t('Phone'), 'phone')}
                        {field(info, 'email', t('Email'), 'email')}
                        {field(info, 'language', t('Language'), 'select', { required: true, options: languages })}
                        <Button type="submit" disabled={info.processing}><Save className="h-4 w-4" />{t('Save')}</Button>
                    </form>
                </Card>

                <Card className="p-6">
                    <form onSubmit={(e) => { e.preventDefault(); pass.put(route('password.update'), { preserveScroll: true, onSuccess: () => pass.reset() }); }} className="space-y-4">
                        <div className="flex items-center gap-3">
                            <KeyRound className="h-5 w-5 text-brand" />
                            <h2 className="text-lg font-bold">{t('Change password')}</h2>
                        </div>
                        {field(pass, 'current_password', t('Current password'), 'password', { required: true })}
                        {field(pass, 'password', t('New password'), 'password', { required: true })}
                        {field(pass, 'password_confirmation', t('Confirm password'), 'password', { required: true })}
                        <Button type="submit" disabled={pass.processing}><Save className="h-4 w-4" />{t('Change password')}</Button>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
