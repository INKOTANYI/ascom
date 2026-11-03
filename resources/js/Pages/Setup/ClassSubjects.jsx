import Field from '@/Components/Field';
import { Badge, Button, Card, Drawer, Empty, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, Pencil, Plus, Trash2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';

/** The subjects one class takes and who teaches them (CAMIS course assignments, must match SDMS). */
export default function ClassSubjects({ schoolClass, items, subjects, teachers, copyFrom, canManage }) {
    const t = useT();
    const [editing, setEditing] = useState(null);
    const [fromClass, setFromClass] = useState('');
    const copy = () => fromClass && router.post(route('setup.classes.subjects.copy', schoolClass.id), { from_class_id: fromClass }, { preserveScroll: true, onSuccess: () => setFromClass('') });
    const blank = { subject_id: '', teacher_id: '', weekly_periods: '', max_marks: '', sdms_synced: false };
    const form = useForm(blank);

    const open = (item) => {
        form.clearErrors();
        form.setData(item ? {
            subject_id: item.subject_id, teacher_id: item.teacher_id ?? '', weekly_periods: item.weekly_periods ?? '',
            max_marks: item.max_marks ?? '', sdms_synced: item.sdms_synced,
        } : blank);
        setEditing(item || {});
    };
    const save = () => {
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing.id
            ? form.put(route('setup.classes.subjects.update', [schoolClass.id, editing.id]), options)
            : form.post(route('setup.classes.subjects.store', schoolClass.id), options);
    };

    const taken = new Set(items.map((i) => i.subject_id));
    const subjectOptions = subjects.filter((s) => !taken.has(s.id) || s.id === editing?.subject_id).map((s) => ({ value: s.id, label: `${s.name} (${s.code})` }));
    const missingTeacher = items.filter((i) => !i.teacher_id).length;
    const notSynced = items.filter((i) => !i.sdms_synced).length;

    return (
        <AppLayout title={`${schoolClass.name} · ${t('Subjects and teachers')}`}>
            <Link href={route('setup.classes', { year: schoolClass.academic_year_id })} className="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
                <ArrowLeft className="h-4 w-4" />{t('Classes')}
            </Link>
            <PageHeader
                title={`${schoolClass.name} · ${t('Subjects and teachers')}`}
                description={`${schoolClass.level.name}${schoolClass.combination ? ` · ${schoolClass.combination.name}` : ''} · ${schoolClass.academic_year.name} · ${t('Class teacher')}: ${schoolClass.class_teacher ? `${schoolClass.class_teacher.first_name} ${schoolClass.class_teacher.last_name}` : '—'}`}
                actions={canManage && <Button onClick={() => open(null)}><Plus className="h-4 w-4" />{t('Add subject')}</Button>}
            />

            {(missingTeacher > 0 || notSynced > 0) && items.length > 0 && (
                <div className="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>
                        {missingTeacher > 0 && t('Subjects without a teacher: :n.', { n: missingTeacher })}{' '}
                        {notSynced > 0 && t('Not yet confirmed against SDMS: :n. Marks entry will check this.', { n: notSynced })}
                    </span>
                </div>
            )}

            {canManage && copyFrom.length > 0 && (
                <div className="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                    <Copy className="h-4 w-4 text-brand" />
                    <label htmlFor="copy-from" className="font-medium">{t('Copy subjects from another class')}</label>
                    <select id="copy-from" value={fromClass} onChange={(e) => setFromClass(e.target.value)}
                        className="rounded-lg border-slate-300 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <option value="">{t('Choose…')}</option>
                        {copyFrom.map((c) => <option key={c.id} value={c.id}>{c.name} ({t('Subjects: :n', { n: c.class_subjects_count })})</option>)}
                    </select>
                    <Button variant="secondary" onClick={copy} disabled={!fromClass}>{t('Copy')}</Button>
                    <span className="text-xs text-slate-500">{t('Subjects this class already has are kept.')}</span>
                </div>
            )}

            <Card>
                {items.length === 0 ? <Empty>{t('No subjects yet. Add the subjects this class takes.')}</Empty> : (
                    <div className="relative overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                <tr>
                                    <th className="px-4 py-3">{t('Subject')}</th>
                                    <th className="px-4 py-3">{t('Teacher')}</th>
                                    <th className="px-4 py-3">{t('Periods / week')}</th>
                                    <th className="px-4 py-3">{t('Report card weight')}</th>
                                    <th className="px-4 py-3">{t('Matches SDMS')}</th>
                                    {canManage && <th className="px-4 py-3"><span className="sr-only">{t('Actions')}</span></th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {items.map((item) => (
                                    <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                        <td className="px-4 py-3"><span className="font-semibold">{item.subject.name}</span> <code className="ml-1 text-xs text-slate-400">{item.subject.camis_course_code || item.subject.code}</code></td>
                                        <td className="px-4 py-3">{item.teacher ? `${item.teacher.first_name} ${item.teacher.last_name}` : <Badge tone="amber">{t('No teacher')}</Badge>}</td>
                                        <td className="px-4 py-3">{item.weekly_periods ?? '—'}</td>
                                        <td className="px-4 py-3">{item.max_marks ? Number(item.max_marks) : '—'}</td>
                                        <td className="px-4 py-3">{item.sdms_synced ? <Check className="h-4 w-4 text-brand" /> : <span className="text-slate-400">—</span>}</td>
                                        {canManage && (
                                            <td className="whitespace-nowrap px-4 py-2 text-right">
                                                <button type="button" onClick={() => open(item)} className="rounded-lg p-2 text-slate-500 hover:bg-brand-soft hover:text-brand-dark" aria-label={t('Edit')}><Pencil className="h-4 w-4" /></button>
                                                <button type="button" onClick={() => confirm(t('Delete this record?')) && router.delete(route('setup.classes.subjects.destroy', [schoolClass.id, item.id]), { preserveScroll: true })} className="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" aria-label={t('Delete')}><Trash2 className="h-4 w-4" /></button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Drawer open={editing !== null} title={editing?.id ? t('Edit') : t('Add subject')} onClose={() => setEditing(null)}
                footer={<><Button variant="secondary" onClick={() => setEditing(null)}>{t('Cancel')}</Button><Button onClick={save} disabled={form.processing}>{t('Save')}</Button></>}>
                <div className="space-y-4">
                    <Field field={{ name: 'subject_id', label: t('Subject'), type: 'select', required: true, options: subjectOptions }} value={form.data.subject_id} onChange={(v) => form.setData('subject_id', v)} error={form.errors.subject_id} />
                    <Field field={{ name: 'teacher_id', label: t('Teacher'), type: 'select', options: teachers.map((s) => ({ value: s.id, label: `${s.first_name} ${s.last_name}` })), help: t('Must be the same teacher as in SDMS.') }}
                        value={form.data.teacher_id} onChange={(v) => form.setData('teacher_id', v)} error={form.errors.teacher_id} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field field={{ name: 'weekly_periods', label: t('Periods / week'), type: 'number' }} value={form.data.weekly_periods} onChange={(v) => form.setData('weekly_periods', v)} error={form.errors.weekly_periods} />
                        <Field field={{ name: 'max_marks', label: t('Report card weight'), type: 'number', placeholder: '100' }} value={form.data.max_marks} onChange={(v) => form.setData('max_marks', v)} error={form.errors.max_marks} />
                    </div>
                    <Field field={{ name: 'sdms_synced', label: t('Confirmed: this assignment matches SDMS'), type: 'checkbox' }} value={form.data.sdms_synced} onChange={(v) => form.setData('sdms_synced', v)} />
                </div>
            </Drawer>
        </AppLayout>
    );
}
