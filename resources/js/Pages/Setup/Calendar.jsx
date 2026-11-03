import Field from '@/Components/Field';
import { Badge, Button, Card, Drawer, Empty, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';

const fmt = (d, locale) => new Date(d + 'T00:00:00').toLocaleDateString(locale === 'rw' ? 'en-GB' : locale, { day: 'numeric', month: 'short', year: 'numeric' });

/** Academic years, each with exactly three terms (BR-19); one term is current (BR-02). */
export default function Calendar({ years }) {
    const t = useT();
    const { locale } = usePage().props;
    const [editing, setEditing] = useState(null);
    const blank = { name: '', terms: [1, 2, 3].map(() => ({ start_date: '', end_date: '' })) };
    const form = useForm(blank);

    const open = (year) => {
        form.clearErrors();
        form.setData(year
            ? { name: year.name, terms: year.terms.map((tm) => ({ start_date: tm.start_date, end_date: tm.end_date })) }
            : blank);
        setEditing(year || {});
    };
    const setTerm = (i, key, value) => form.setData('terms', form.data.terms.map((tm, j) => (j === i ? { ...tm, [key]: value } : tm)));
    const save = () => {
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing.id ? form.put(route('setup.calendar.update', editing.id), options) : form.post(route('setup.calendar.store'), options);
    };

    return (
        <AppLayout title={t('Academic calendar')}>
            <PageHeader
                title={t('Academic calendar')}
                description={t('Each academic year has exactly three terms. The current term is used for attendance, marks and fees.')}
                actions={<Button onClick={() => open(null)}><Plus className="h-4 w-4" />{t('Add academic year')}</Button>}
            />

            {years.length === 0 ? <Card><Empty>{t('Nothing here yet.')}</Empty></Card> : (
                <div className="space-y-5">
                    {years.map((year) => (
                        <Card key={year.id} className="overflow-hidden">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                                <div className="flex items-center gap-3">
                                    <CalendarDays className="h-5 w-5 text-brand" />
                                    <h2 className="text-lg font-bold">{year.name}</h2>
                                    {year.is_current && <Badge>{t('Current year')}</Badge>}
                                    <span className="text-sm text-slate-500">{t('Classes: :n', { n: year.classes_count })}</span>
                                </div>
                                <div className="flex gap-1">
                                    <Button variant="ghost" onClick={() => open(year)}><Pencil className="h-4 w-4" />{t('Edit')}</Button>
                                    {!year.is_current && year.classes_count === 0 && (
                                        <Button variant="ghost" className="text-red-600" onClick={() => confirm(t('Delete this record?')) && router.delete(route('setup.calendar.destroy', year.id), { preserveScroll: true })}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <div className="grid gap-px bg-slate-200 sm:grid-cols-3 dark:bg-slate-800">
                                {year.terms.map((term) => (
                                    <div key={term.id} className={`p-5 ${term.is_current ? 'bg-brand-soft dark:bg-brand/15' : 'bg-white dark:bg-slate-900'}`}>
                                        <div className="flex items-center justify-between">
                                            <p className="font-semibold">{t('Term :n', { n: term.term_number })}</p>
                                            {term.is_current
                                                ? <Badge>{t('Current term')}</Badge>
                                                : <button type="button" onClick={() => router.post(route('setup.terms.current', term.id), {}, { preserveScroll: true })}
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-brand hover:underline"><Star className="h-3.5 w-3.5" />{t('Make current')}</button>}
                                        </div>
                                        <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{fmt(term.start_date, locale)} – {fmt(term.end_date, locale)}</p>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <Drawer open={editing !== null} title={editing?.id ? t('Edit') : t('Add academic year')} onClose={() => setEditing(null)}
                footer={<><Button variant="secondary" onClick={() => setEditing(null)}>{t('Cancel')}</Button><Button onClick={save} disabled={form.processing}>{t('Save')}</Button></>}>
                <div className="space-y-5">
                    <Field field={{ name: 'name', label: t('Academic year'), required: true, placeholder: '2027-2028' }} value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    {form.data.terms.map((term, i) => (
                        <fieldset key={i} className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <legend className="px-1 text-sm font-semibold">{t('Term :n', { n: i + 1 })}</legend>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field field={{ name: `t${i}s`, label: t('Starts'), type: 'date', required: true }} value={term.start_date} onChange={(v) => setTerm(i, 'start_date', v)} error={form.errors[`terms.${i}.start_date`]} />
                                <Field field={{ name: `t${i}e`, label: t('Ends'), type: 'date', required: true }} value={term.end_date} onChange={(v) => setTerm(i, 'end_date', v)} error={form.errors[`terms.${i}.end_date`]} />
                            </div>
                        </fieldset>
                    ))}
                    <p className="text-xs text-slate-500">{t('Use the official NESA / MINEDUC school calendar.')}</p>
                </div>
            </Drawer>
        </AppLayout>
    );
}
