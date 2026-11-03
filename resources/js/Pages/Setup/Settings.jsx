import Field from '@/Components/Field';
import { Button, Card, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/react';
import { Building2, ImagePlus, MessageSquare, Palette, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';

function Section({ icon: Icon, title, description, children }) {
    return (
        <Card className="p-6">
            <div className="mb-5 flex items-start gap-3">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand-dark dark:bg-brand/20 dark:text-brand-soft"><Icon className="h-5 w-5" /></span>
                <div>
                    <h2 className="text-lg font-bold">{title}</h2>
                    {description && <p className="text-sm text-slate-500 dark:text-slate-400">{description}</p>}
                </div>
            </div>
            {children}
        </Card>
    );
}

/** Everything that identifies the school. Saving updates every screen (and later report cards, receipts and SMS). */
export default function Settings({ profile, settings, options }) {
    const t = useT();
    const { school } = usePage().props;
    const [preview, setPreview] = useState(school.logo_url);
    const form = useForm({
        name: profile?.name ?? '', short_name: profile?.short_name ?? '', mineduc_code: profile?.mineduc_code ?? '',
        school_type: profile?.school_type ?? 'mixed', ownership: profile?.ownership ?? '', province: profile?.province ?? '',
        district: profile?.district === 'To be completed' ? '' : (profile?.district ?? ''),
        sector: profile?.sector === 'To be completed' ? '' : (profile?.sector ?? ''),
        phone: profile?.phone ?? '', email: profile?.email ?? '', motto: profile?.motto ?? '', domain: profile?.domain ?? '',
        is_boarding: profile?.is_boarding ?? false,
        brand_color: settings.brand_color, accent_color: settings.accent_color,
        default_language: settings.default_language, sms_sender: settings.sms_sender ?? '',
        logo: null, remove_logo: false,
    });
    const f = (name, label, type = 'text', extra = {}) => (
        <Field field={{ name, label, type, ...extra }} value={form.data[name]} onChange={(v) => form.setData(name, v)} error={form.errors[name]} />
    );

    const submit = (e) => {
        e.preventDefault();
        form.post(route('setup.settings.update'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <AppLayout title={t('Settings')}>
            <PageHeader title={t('Settings')} description={t('The school’s name, logo, colours and contacts. They appear on every screen, and later on report cards, receipts and SMS.')} />

            <form onSubmit={submit} className="space-y-6">
                <Section icon={Building2} title={t('School identity')} description={t('As registered with MINEDUC.')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">{f('name', t('Official school name'), 'text', { required: true, placeholder: 'Groupe Scolaire Kagombyi' })}</div>
                        {f('short_name', t('Short name'), 'text', { placeholder: 'GS Kagombyi', help: t('Shown in the menu and on phones.') })}
                        {f('mineduc_code', t('MINEDUC school code'))}
                        {f('school_type', t('School type'), 'select', { required: true, options: options.types })}
                        {f('ownership', t('Ownership'), 'select', { options: options.ownership })}
                        {f('province', t('Province'), 'select', { options: options.provinces.map((p) => ({ value: p, label: p })) })}
                        {f('district', t('District'), 'text', { required: true })}
                        {f('sector', t('Sector'), 'text', { required: true })}
                        {f('phone', t('School phone'), 'text', { placeholder: '0788 123 456' })}
                        {f('email', t('School email'), 'email')}
                        {f('domain', t('Website address'), 'text', { placeholder: 'gskagombyi.rw' })}
                        <div className="sm:col-span-2">{f('motto', t('Motto'))}</div>
                        <div className="sm:col-span-2">{f('is_boarding', t('Boarding school'), 'checkbox')}</div>
                    </div>
                </Section>

                <Section icon={ImagePlus} title={t('Logo')} description={t('PNG, JPG or WebP, up to 2 MB. A square logo looks best.')}>
                    <div className="flex flex-wrap items-center gap-5">
                        <div className="flex h-24 w-24 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700">
                            {preview ? <img src={preview} alt={t('Logo')} className="h-full w-full object-contain p-1" /> : <Building2 className="h-10 w-10 text-slate-300" />}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">
                                <ImagePlus className="h-4 w-4" />{t('Choose a logo')}
                                <input type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    if (file) { form.setData((d) => ({ ...d, logo: file, remove_logo: false })); setPreview(URL.createObjectURL(file)); }
                                }} />
                            </label>
                            {preview && (
                                <Button variant="secondary" onClick={() => { form.setData((d) => ({ ...d, logo: null, remove_logo: true })); setPreview(null); }}>
                                    <Trash2 className="h-4 w-4" />{t('Remove')}
                                </Button>
                            )}
                        </div>
                    </div>
                    {form.errors.logo && <p className="mt-2 text-xs font-medium text-red-600">{form.errors.logo}</p>}
                </Section>

                <Section icon={Palette} title={t('Colours and language')} description={t('The main colour is used for the menu, buttons and headings.')}>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {f('brand_color', t('Main colour'), 'color')}
                        {f('accent_color', t('Accent colour'), 'color')}
                        {f('default_language', t('Default language'), 'select', { required: true, options: options.languages })}
                    </div>
                    <div className="mt-5 flex items-center gap-3 rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
                        <span className="h-10 w-10 rounded-lg" style={{ background: form.data.brand_color }} />
                        <span className="h-10 w-10 rounded-lg" style={{ background: form.data.accent_color }} />
                        <span className="text-sm text-slate-500">{t('Preview. The whole system changes colour after you save.')}</span>
                    </div>
                </Section>

                <Section icon={MessageSquare} title={t('SMS')} description={t('Used for messages to parents (fees, absences, results).')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {f('sms_sender', t('SMS sender name'), 'text', { placeholder: 'GSKAGOMBYI', help: t('Up to 11 letters or digits. It must be approved by the SMS provider (RURA rules).') })}
                    </div>
                </Section>

                <div className="sticky bottom-4 flex justify-end">
                    <Button type="submit" disabled={form.processing} className="shadow-lg"><Save className="h-4 w-4" />{form.processing ? t('Saving…') : t('Save settings')}</Button>
                </div>
            </form>
        </AppLayout>
    );
}
