import Field from '@/Components/Field';
import { Avatar, Badge, Button, Card, Drawer, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, History, MessageSquare, Pencil, Phone, School, UserCheck } from 'lucide-react';
import { useState } from 'react';
import { STATUS_TONE } from './Index';

const fmtDate = (d, locale) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString(locale === 'rw' ? 'en-GB' : locale, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

const FIELD_LABELS = {
    sdms_id: 'SDMS ID', reg_number: 'Registration number', first_name: 'First name', last_name: 'Last name', gender: 'Gender',
    date_of_birth: 'Date of birth', national_id: 'National ID', district: 'District', sector: 'Sector', special_needs: 'Special needs',
    status: 'Status', admission_date: 'Admission date', class_id: 'Class', enrollment_type: 'Joined as', date_joined: 'Joined',
    date_left: 'Left', reason: 'Reason', guardians: 'Parents and guardians',
};

function Row({ label, children }) {
    return (
        <div className="py-2.5 sm:grid sm:grid-cols-3 sm:gap-4">
            <dt className="text-sm text-slate-500 dark:text-slate-400">{label}</dt>
            <dd className="text-sm font-medium sm:col-span-2">{children || '—'}</dd>
        </div>
    );
}

/** One student: details, parents, class history, transfers and the record of changes. */
export default function Show({ student, guardians, enrollments, transfers, history, classes, statuses, year, canManage }) {
    const t = useT();
    const { locale } = usePage().props;
    const [panel, setPanel] = useState(null);
    const classForm = useForm({ class_id: student.class_id ?? '' });
    const statusForm = useForm({ status: '', date: new Date().toISOString().slice(0, 10), other_school: '', other_school_code: '', reason: '' });

    const close = () => setPanel(null);
    const name = `${student.last_name.toUpperCase()} ${student.first_name}`;
    const options = { preserveScroll: true, onSuccess: close };

    return (
        <AppLayout title={`${student.first_name} ${student.last_name}`}>
            <Link href={route('students.index')} className="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
                <ArrowLeft className="h-4 w-4" />{t('Students')}
            </Link>

            <Card className="mb-6 p-5 sm:p-6">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <Avatar name={`${student.first_name} ${student.last_name}`} src={student.photo_url} size="h-24 w-24" className="text-2xl" />
                    <div className="min-w-0 flex-1">
                        <h1 className="text-2xl font-bold tracking-tight">{name}</h1>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <Badge tone={STATUS_TONE[student.status]}>{student.status_label}</Badge>
                            {student.class ? <Badge tone="slate">{student.class}{student.enrollment_status === 'left' ? ` · ${t('left')}` : ''}</Badge> : <Badge tone="amber">{t('No class this year')}</Badge>}
                            <span className="text-sm text-slate-500 dark:text-slate-400">
                                {[student.gender === 'M' ? t('Boy') : t('Girl'), student.age !== null && t(':n years', { n: student.age }), student.sdms_id && `SDMS ${student.sdms_id}`].filter(Boolean).join(' · ')}
                            </span>
                        </div>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('students.edit', student.id)} className="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">
                                <Pencil className="h-4 w-4" />{t('Edit')}
                            </Link>
                            {student.status === 'active' && year && (
                                <Button variant="secondary" onClick={() => { classForm.setData('class_id', student.class_id ?? ''); classForm.clearErrors(); setPanel('class'); }}>
                                    <School className="h-4 w-4" />{student.class ? t('Change class') : t('Put in a class')}
                                </Button>
                            )}
                            <Button variant="secondary" onClick={() => { statusForm.reset(); statusForm.clearErrors(); setPanel('status'); }}>
                                <UserCheck className="h-4 w-4" />{t('Change status')}
                            </Button>
                        </div>
                    )}
                </div>
            </Card>

            <div className="grid gap-6 lg:grid-cols-5">
                <div className="space-y-6 lg:col-span-3">
                    <Card className="p-5 sm:p-6">
                        <h2 className="mb-2 text-lg font-bold">{t('Details')}</h2>
                        <dl className="divide-y divide-slate-100 dark:divide-slate-800">
                            <Row label={t('SDMS ID')}>{student.sdms_id}</Row>
                            <Row label={t('Registration number')}>{student.reg_number}</Row>
                            <Row label={t('Date of birth')}>{student.date_of_birth && `${fmtDate(student.date_of_birth, locale)} (${t(':n years', { n: student.age })})`}</Row>
                            <Row label={t('National ID')}>{student.national_id}</Row>
                            <Row label={t('Admission date')}>{student.admission_date && fmtDate(student.admission_date, locale)}</Row>
                            <Row label={t('District')}>{[student.district, student.sector].filter(Boolean).join(' · ')}</Row>
                            <Row label={t('Special needs or health information')}>{student.special_needs && <span className="whitespace-pre-line">{student.special_needs}</span>}</Row>
                        </dl>
                    </Card>

                    <Card>
                        <h2 className="px-5 pb-2 pt-5 text-lg font-bold sm:px-6">{t('Classes')}</h2>
                        {enrollments.length === 0 ? <p className="px-6 pb-6 text-sm text-slate-500">{t('Not in any class yet.')}</p> : (
                            <div className="relative overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                        <tr><th className="px-5 py-2.5">{t('Academic year')}</th><th className="px-5 py-2.5">{t('Class')}</th><th className="px-5 py-2.5">{t('Joined as')}</th><th className="px-5 py-2.5">{t('Dates')}</th></tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {enrollments.map((e) => (
                                            <tr key={e.id}>
                                                <td className="px-5 py-3">{e.year} {e.is_current && <Badge>{t('Current year')}</Badge>}</td>
                                                <td className="px-5 py-3 font-semibold">{e.class}</td>
                                                <td className="px-5 py-3">{e.type}</td>
                                                <td className="px-5 py-3 text-slate-500">{fmtDate(e.date_joined, locale)}{e.date_left && ` → ${fmtDate(e.date_left, locale)}`}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>

                    {transfers.length > 0 && (
                        <Card className="p-5 sm:p-6">
                            <h2 className="mb-3 text-lg font-bold">{t('Transfers')}</h2>
                            <ul className="space-y-3">
                                {transfers.map((tr) => (
                                    <li key={tr.id} className="flex gap-3 text-sm">
                                        <ArrowRightLeft className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                        <div>
                                            <p className="font-semibold">{tr.direction === 'in' ? t('Came from :school', { school: tr.other_school || '—' }) : t('Left for :school', { school: tr.other_school || '—' })}{tr.other_school_code && ` (${tr.other_school_code})`}</p>
                                            <p className="text-slate-500">{fmtDate(tr.date, locale)}{tr.by && ` · ${t('recorded by :name', { name: tr.by })}`}</p>
                                            {tr.reason && <p className="mt-1">{tr.reason}</p>}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>

                <div className="space-y-6 lg:col-span-2">
                    <Card className="p-5 sm:p-6">
                        <h2 className="mb-3 text-lg font-bold">{t('Parents and guardians')}</h2>
                        {guardians.length === 0 ? (
                            <p className="text-sm text-amber-700 dark:text-amber-400">{t('No parent yet. Add one so the school can send SMS.')}</p>
                        ) : (
                            <ul className="space-y-4">
                                {guardians.map((g) => (
                                    <li key={g.id} className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-semibold">{g.full_name}</p>
                                                <p className="text-sm text-slate-500 dark:text-slate-400">{g.relationship_label}{g.occupation && ` · ${g.occupation}`}</p>
                                            </div>
                                            {g.is_primary && <Badge><MessageSquare className="mr-1 h-3 w-3" />{t('Receives SMS')}</Badge>}
                                        </div>
                                        {g.phone_display && <a href={`tel:${g.phone_display.replace(/\s/g, '')}`} className="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline"><Phone className="h-4 w-4" />{g.phone_display}</a>}
                                        {g.email && <p className="text-sm text-slate-500">{g.email}</p>}
                                        {g.children > 1 && (
                                            <Link href={route('guardians.index', { search: g.phone_display || g.full_name })} className="mt-1 block text-xs text-slate-500 hover:underline">
                                                {t('Parent of :n students at this school', { n: g.children })}
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    {history && (
                        <Card className="p-5 sm:p-6">
                            <h2 className="mb-3 flex items-center gap-2 text-lg font-bold"><History className="h-5 w-5 text-slate-400" />{t('History of changes')}</h2>
                            {history.length === 0 ? <p className="text-sm text-slate-500">{t('Nothing yet.')}</p> : (
                                <ol className="space-y-3 text-sm">
                                    {history.map((h) => (
                                        <li key={h.id} className="border-l-2 border-slate-200 pl-3 dark:border-slate-700">
                                            <p className="text-xs text-slate-500">{new Date(h.at).toLocaleString(locale === 'rw' ? 'en-GB' : locale, { dateStyle: 'medium', timeStyle: 'short' })} · {h.user || t('System')}</p>
                                            <p className="font-medium">
                                                {h.action === 'create' ? (h.table === 'enrollments' ? t('Put in a class') : t('Registered')) : h.table === 'enrollments' ? t('Class record changed') : h.table === 'student_guardians' ? t('Parents changed') : t('Changed')}
                                            </p>
                                            {h.action === 'update' && h.new && h.table !== 'student_guardians' && (
                                                <ul className="mt-1 space-y-0.5 text-xs text-slate-600 dark:text-slate-300">
                                                    {Object.entries(h.new).map(([k, v]) => (
                                                        <li key={k}>{t(FIELD_LABELS[k] || k)}: <span className="text-slate-400 line-through">{String(h.old?.[k] ?? '—')}</span> → {String(v ?? '—')}</li>
                                                    ))}
                                                </ul>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Card>
                    )}
                </div>
            </div>

            <Drawer open={panel === 'class'} title={student.class ? t('Change class') : t('Put in a class')} onClose={close}
                footer={<><Button variant="secondary" onClick={close}>{t('Cancel')}</Button><Button disabled={classForm.processing} onClick={() => classForm.put(route('students.class', student.id), options)}>{t('Save')}</Button></>}>
                <div className="space-y-4">
                    <Field field={{ name: 'class_id', label: t('Class in :year', { year: year?.name ?? '' }), type: 'select', required: true,
                        options: classes.map((c) => ({ value: c.value, label: `${c.label} · ${t(':n students', { n: c.students })}${c.capacity ? ` / ${c.capacity}` : ''}` })) }}
                        value={classForm.data.class_id} onChange={(v) => classForm.setData('class_id', v ?? '')} error={classForm.errors.class_id} />
                    <p className="text-sm text-slate-500">{t('The student keeps one class per year. Moving them is saved in the history.')}</p>
                </div>
            </Drawer>

            <Drawer open={panel === 'status'} title={t('Change status')} onClose={close}
                footer={<><Button variant="secondary" onClick={close}>{t('Cancel')}</Button><Button disabled={statusForm.processing || !statusForm.data.status} onClick={() => statusForm.post(route('students.status', student.id), options)}>{t('Save')}</Button></>}>
                <div className="space-y-4">
                    <Field field={{ name: 'status', label: t('New status'), type: 'select', required: true, options: statuses.filter((s) => s.value !== student.status) }}
                        value={statusForm.data.status} onChange={(v) => statusForm.setData('status', v ?? '')} error={statusForm.errors.status} />
                    <Field field={{ name: 'date', label: t('Date'), type: 'date', required: true }} value={statusForm.data.date} onChange={(v) => statusForm.setData('date', v)} error={statusForm.errors.date} />
                    {statusForm.data.status === 'transferred' && <>
                        <Field field={{ name: 'other_school', label: t('New school'), required: true }} value={statusForm.data.other_school} onChange={(v) => statusForm.setData('other_school', v)} error={statusForm.errors.other_school} />
                        <Field field={{ name: 'other_school_code', label: t('New school code') }} value={statusForm.data.other_school_code} onChange={(v) => statusForm.setData('other_school_code', v)} error={statusForm.errors.other_school_code} />
                    </>}
                    <Field field={{ name: 'reason', label: t('Reason'), type: 'textarea' }} value={statusForm.data.reason} onChange={(v) => statusForm.setData('reason', v)} error={statusForm.errors.reason} />
                    {['transferred', 'dropped_out', 'graduated'].includes(statusForm.data.status) && (
                        <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            {t('The student leaves their class. They will get no new marks or invoices. You can set them back to active later.')}
                        </p>
                    )}
                </div>
            </Drawer>
        </AppLayout>
    );
}
