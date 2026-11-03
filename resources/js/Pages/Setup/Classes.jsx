import Field from '@/Components/Field';
import { Badge, Button, Card, Drawer, Empty, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useCan, useT } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { BookOpen, Pencil, Plus, Trash2, UserRound, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

const CYCLE_ORDER = ['nursery', 'primary', 'o_level', 'a_level', 'tvet'];
const CYCLE_LABEL = { nursery: 'Nursery', primary: 'Primary', o_level: 'O-level', a_level: 'A-level', tvet: 'TVET' };

/** Classes of one academic year, grouped by cycle. The class name is built from level, combination and stream. */
export default function Classes({ year, years, classes, levels, combinations, teachers, canManage }) {
    const t = useT();
    const can = useCan();
    const [editing, setEditing] = useState(null);
    const blank = { academic_year_id: year?.id ?? '', level_id: '', combination_id: '', stream: '', class_teacher_id: '', capacity: '' };
    const form = useForm(blank);

    const level = levels.find((l) => String(l.id) === String(form.data.level_id));
    const needs = level?.cycle === 'a_level' ? 'a_level' : level?.cycle === 'tvet' ? 'tvet_trade' : null;
    const combo = combinations.find((c) => String(c.id) === String(form.data.combination_id));
    const preview = level ? [level.code, needs ? combo?.code : null, form.data.stream?.toUpperCase()].filter(Boolean).join(' ') : '';

    const grouped = useMemo(() => CYCLE_ORDER.map((cycle) => ({ cycle, items: classes.filter((c) => c.level.cycle === cycle) })).filter((g) => g.items.length), [classes]);

    const open = (c) => {
        form.clearErrors();
        form.setData(c ? {
            academic_year_id: c.academic_year_id, level_id: c.level_id, combination_id: c.combination_id ?? '', stream: c.stream ?? '',
            class_teacher_id: c.class_teacher_id ?? '', capacity: c.capacity ?? '',
        } : blank);
        setEditing(c || {});
    };
    const save = () => {
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing.id ? form.put(route('setup.classes.update', editing.id), options) : form.post(route('setup.classes.store'), options);
    };

    const teacherOptions = teachers.map((s) => ({ value: s.id, label: `${s.first_name} ${s.last_name}` }));

    return (
        <AppLayout title={t('Classes')}>
            <PageHeader
                title={t('Classes')}
                description={t('Each class (CAMIS "class group") belongs to one academic year and has a class teacher, who approves the marks of the class.')}
                actions={<>
                    <select aria-label={t('Academic year')} value={year?.id ?? ''} onChange={(e) => router.get(route('setup.classes'), { year: e.target.value })}
                        className="rounded-lg border-slate-300 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                    </select>
                    {canManage && year && <Button onClick={() => open(null)}><Plus className="h-4 w-4" />{t('Add class')}</Button>}
                </>}
            />

            {!year ? <Card><Empty>{t('Add an academic year first.')}</Empty></Card>
                : grouped.length === 0 ? <Card><Empty>{t('No classes for :year yet.', { year: year.name })}</Empty></Card>
                : (
                    <div className="space-y-6">
                        {grouped.map(({ cycle, items }) => (
                            <section key={cycle}>
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">{t(CYCLE_LABEL[cycle])} · {items.length}</h2>
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {items.map((c) => (
                                        <Card key={c.id} className="flex flex-col p-5">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="text-xl font-bold">{c.name}</p>
                                                    <p className="text-sm text-slate-500">{c.level.name}{c.combination ? ` · ${c.combination.name}` : ''}</p>
                                                </div>
                                                {canManage && (
                                                    <div className="flex">
                                                        <button type="button" onClick={() => open(c)} className="rounded-lg p-2 text-slate-500 hover:bg-brand-soft hover:text-brand-dark" aria-label={t('Edit')}><Pencil className="h-4 w-4" /></button>
                                                        <button type="button" onClick={() => confirm(t('Delete this record?')) && router.delete(route('setup.classes.destroy', c.id), { preserveScroll: true })} className="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" aria-label={t('Delete')}><Trash2 className="h-4 w-4" /></button>
                                                    </div>
                                                )}
                                            </div>
                                            <p className="mt-3 flex items-center gap-2 text-sm">
                                                <UserRound className="h-4 w-4 text-slate-400" />
                                                {c.class_teacher ? `${c.class_teacher.first_name} ${c.class_teacher.last_name}` : <span className="text-amber-700 dark:text-amber-400">{t('No class teacher yet')}</span>}
                                            </p>
                                            <p className="mt-1.5 flex items-center gap-2 text-sm">
                                                <Users className="h-4 w-4 text-slate-400" />
                                                {can('students.view')
                                                    ? <Link href={route('students.index', { class_id: c.id })} className="hover:text-brand hover:underline">{t('Students: :n', { n: c.active_enrollments_count })}{c.capacity ? ` / ${c.capacity}` : ''}</Link>
                                                    : <span>{t('Students: :n', { n: c.active_enrollments_count })}{c.capacity ? ` / ${c.capacity}` : ''}</span>}
                                            </p>
                                            <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                                                <Badge tone={c.class_subjects_count ? 'brand' : 'amber'}>{t('Subjects: :n', { n: c.class_subjects_count })}</Badge>
                                                <Link href={route('setup.classes.subjects', c.id)} className="inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
                                                    <BookOpen className="h-4 w-4" />{t('Subjects and teachers')}
                                                </Link>
                                            </div>
                                        </Card>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}

            <Drawer open={editing !== null} title={editing?.id ? t('Edit class') : t('Add class')} onClose={() => setEditing(null)}
                footer={<><Button variant="secondary" onClick={() => setEditing(null)}>{t('Cancel')}</Button><Button onClick={save} disabled={form.processing}>{t('Save')}</Button></>}>
                <div className="space-y-4">
                    <Field field={{ name: 'level_id', label: t('Level'), type: 'select', required: true, options: levels.map((l) => ({ value: l.id, label: `${l.code} · ${l.name}` })) }}
                        value={form.data.level_id} onChange={(v) => form.setData((d) => ({ ...d, level_id: v, combination_id: '' }))} error={form.errors.level_id} />
                    {needs && (
                        <Field field={{ name: 'combination_id', label: needs === 'a_level' ? t('A-level combination') : t('TVET trade'), type: 'select', required: true,
                            options: combinations.filter((c) => c.type === needs).map((c) => ({ value: c.id, label: `${c.code} · ${c.name}` })) }}
                            value={form.data.combination_id} onChange={(v) => form.setData('combination_id', v)} error={form.errors.combination_id} />
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field field={{ name: 'stream', label: t('Stream'), placeholder: 'A', help: t('Leave empty if the level has one class.') }} value={form.data.stream} onChange={(v) => form.setData('stream', v)} error={form.errors.stream} />
                        <Field field={{ name: 'capacity', label: t('Maximum students'), type: 'number' }} value={form.data.capacity} onChange={(v) => form.setData('capacity', v)} error={form.errors.capacity} />
                    </div>
                    <Field field={{ name: 'class_teacher_id', label: t('Class teacher'), type: 'select', options: teacherOptions, help: t('Approves the marks of this class (CAMIS rule).') }}
                        value={form.data.class_teacher_id} onChange={(v) => form.setData('class_teacher_id', v)} error={form.errors.class_teacher_id} />
                    {preview && (
                        <div className="rounded-xl bg-brand-soft p-4 dark:bg-brand/15">
                            <p className="text-xs font-semibold uppercase tracking-wider text-brand-dark dark:text-brand-soft">{t('Class name')}</p>
                            <p className="text-2xl font-bold">{preview}</p>
                        </div>
                    )}
                </div>
            </Drawer>
        </AppLayout>
    );
}
