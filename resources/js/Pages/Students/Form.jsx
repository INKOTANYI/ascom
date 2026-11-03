import Field from '@/Components/Field';
import { Avatar, Button, Card, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, HeartHandshake, ImagePlus, MessageSquare, Plus, Save, School, Trash2, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';

const blankGuardian = (isPrimary) => ({ id: null, first_name: '', last_name: '', phone: '', email: '', national_id: '', occupation: '', relationship: 'mother', is_primary: isPrimary });

function Section({ icon: Icon, title, description, children }) {
    return (
        <Card className="p-5 sm:p-6">
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

/** One parent or guardian. When the phone already belongs to a parent, offers to use that record (brothers and sisters). */
function GuardianFields({ index, guardian, errors, relationships, onChange, onRemove, canRemove }) {
    const t = useT();
    const [matches, setMatches] = useState([]);

    useEffect(() => {
        if (guardian.id || guardian.phone.length !== 9) { setMatches([]); return; }
        let live = true;
        axios.get(route('guardians.lookup'), { params: { phone: guardian.phone } }).then((r) => live && setMatches(r.data)).catch(() => {});
        return () => { live = false; };
    }, [guardian.phone, guardian.id]);

    const set = (key, value) => onChange({ ...guardian, [key]: value });
    const err = (key) => errors[`guardians.${index}.${key}`];
    const f = (key, label, type = 'text', extra = {}) => <Field field={{ name: `g${index}-${key}`, label, type, ...extra }} value={guardian[key]} onChange={(v) => set(key, v ?? '')} error={err(key)} />;

    return (
        <fieldset className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <legend className="flex items-center gap-2 px-1 text-sm font-semibold">
                {t('Parent or guardian :n', { n: index + 1 })}
                {guardian.id && <span className="text-xs font-normal text-slate-500">({t('already registered')})</span>}
            </legend>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="sm:col-span-2">{f('phone', t('Phone'), 'phone', { required: guardian.is_primary })}</div>
                {matches.length > 0 && (
                    <div className="space-y-2 sm:col-span-2" role="status">
                        {matches.map((m) => (
                            <div key={m.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand/30 bg-brand-soft p-3 text-sm dark:bg-brand/10">
                                <span>
                                    <strong>{m.first_name} {m.last_name}</strong> {t('is already registered with this phone')}
                                    {m.children.length > 0 && <> · {t('parent of :names', { names: m.children.join(', ') })}</>}
                                </span>
                                <Button variant="secondary" className="py-1.5" onClick={() => onChange({ ...guardian, ...m, phone: guardian.phone, last_name: m.last_name ?? '', email: m.email ?? '', national_id: m.national_id ?? '', occupation: m.occupation ?? '' })}>
                                    {t('Use this parent')}
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
                {f('first_name', t('First name'), 'text', { required: true })}
                {f('last_name', t('Last name'))}
                {f('relationship', t('Relationship'), 'select', { required: true, options: relationships })}
                {f('occupation', t('Occupation'))}
                {f('email', t('Email'), 'email')}
                {f('national_id', t('National ID'))}
                <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm sm:col-span-2 dark:border-slate-700">
                    <input type="radio" name="primary-guardian" checked={guardian.is_primary} onChange={() => set('is_primary', true)} className="h-4 w-4 border-slate-300 text-brand focus:ring-brand" />
                    <MessageSquare className="h-4 w-4 text-brand" />
                    <span className="font-medium">{t('Receives SMS from the school (main contact)')}</span>
                </label>
            </div>
            {canRemove && (
                <div className="mt-3 text-right">
                    <Button variant="ghost" className="text-red-600" onClick={onRemove}><Trash2 className="h-4 w-4" />{t('Remove')}</Button>
                </div>
            )}
        </fieldset>
    );
}

/** Register a student or edit one: identity, class, parents and photo. */
export default function Form({ student, defaultClassId, classes, year, enrollmentTypes, relationships }) {
    const t = useT();
    const editing = !!student;
    const [preview, setPreview] = useState(student?.photo_url ?? null);
    const form = useForm({
        sdms_id: student?.sdms_id ?? '', reg_number: student?.reg_number ?? '',
        first_name: student?.first_name ?? '', last_name: student?.last_name ?? '', gender: student?.gender ?? '',
        date_of_birth: student?.date_of_birth ?? '', national_id: student?.national_id ?? '',
        district: student?.district ?? '', sector: student?.sector ?? '', special_needs: student?.special_needs ?? '',
        admission_date: student?.admission_date ?? (editing ? '' : new Date().toISOString().slice(0, 10)),
        class_id: student?.class_id ?? defaultClassId ?? '', enrollment_type: student?.enrollment_type ?? 'new',
        previous_school: '', previous_school_code: '',
        guardians: student?.guardians?.length ? student.guardians : [blankGuardian(true)],
        photo: null, remove_photo: false, add_another: false,
    });
    const { data, setData, errors } = form;
    const f = (name, label, type = 'text', extra = {}) => <Field field={{ name, label, type, ...extra }} value={data[name]} onChange={(v) => setData(name, v ?? '')} error={errors[name]} />;

    const setGuardian = (i, g) => setData('guardians', data.guardians.map((x, j) => {
        if (j === i) return g;
        return g.is_primary ? { ...x, is_primary: false } : x;
    }));
    const removeGuardian = (i) => {
        const rest = data.guardians.filter((_, j) => j !== i);
        if (rest.length && !rest.some((g) => g.is_primary)) rest[0] = { ...rest[0], is_primary: true };
        setData('guardians', rest);
    };

    const submit = (addAnother) => {
        form.transform((d) => ({
            ...d, add_another: addAnother,
            guardians: d.guardians.filter((g) => g.first_name.trim() || g.phone),
            ...(editing ? { _method: 'put' } : {}),
        }));
        form.post(editing ? route('students.update', student.id) : route('students.store'), {
            forceFormData: true,
            preserveScroll: (page) => Object.keys(page.props.errors || {}).length > 0,
            onSuccess: () => { if (addAnother) { form.reset(); setPreview(null); window.scrollTo({ top: 0 }); } },
        });
    };

    const title = editing ? t('Edit :name', { name: `${student.first_name} ${student.last_name}` }) : t('Add student');
    const classOptions = classes.map((c) => ({ value: c.value, label: `${c.label} · ${t(':n students', { n: c.students })}${c.capacity ? ` / ${c.capacity}` : ''}` }));
    const errorCount = Object.keys(errors).length;

    return (
        <AppLayout title={title}>
            <Link href={editing ? route('students.show', student.id) : route('students.index')} className="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
                <ArrowLeft className="h-4 w-4" />{editing ? `${student.first_name} ${student.last_name}` : t('Students')}
            </Link>
            <PageHeader title={title} description={t('Fields with * are required. Use the same names and SDMS ID as in SDMS.')} />

            {errorCount > 0 && (
                <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    {t('Please correct the fields marked in red.')}
                </div>
            )}

            <form onSubmit={(e) => { e.preventDefault(); submit(false); }} className="space-y-6">
                <Section icon={UserRound} title={t('Student')}>
                    <div className="flex flex-col gap-6 lg:flex-row">
                        <div className="flex flex-col items-center gap-3 lg:w-44">
                            <Avatar name={`${data.first_name} ${data.last_name}`} src={preview} size="h-32 w-32" className="text-3xl" />
                            <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                                <ImagePlus className="h-4 w-4" />{preview ? t('Change photo') : t('Add photo')}
                                <input type="file" accept="image/*" capture="environment" className="sr-only" onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    if (file) { setData((d) => ({ ...d, photo: file, remove_photo: false })); setPreview(URL.createObjectURL(file)); }
                                }} />
                            </label>
                            {preview && <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => { setData((d) => ({ ...d, photo: null, remove_photo: true })); setPreview(null); }}>{t('Remove photo')}</button>}
                            {errors.photo && <p className="text-xs font-medium text-red-600">{errors.photo}</p>}
                        </div>
                        <div className="grid flex-1 gap-4 sm:grid-cols-2">
                            {f('last_name', t('Last name'), 'text', { required: true, placeholder: 'UWIMANA' })}
                            {f('first_name', t('First name'), 'text', { required: true, placeholder: 'Aline' })}
                            {f('gender', t('Gender'), 'select', { required: true, options: [{ value: 'M', label: t('Male') }, { value: 'F', label: t('Female') }] })}
                            {f('date_of_birth', t('Date of birth'), 'date')}
                            {f('sdms_id', t('SDMS ID'), 'text', { help: t('The learner’s unique ID in SDMS.') })}
                            {f('reg_number', t('Registration number'))}
                        </div>
                    </div>
                </Section>

                <Section icon={School} title={t('Class')} description={year ? t('The student’s class in :year. A student has only one class per year.', { year: year.name }) : t('Add an academic year first.')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {f('class_id', t('Class'), 'select', { options: classOptions, help: classes.length ? null : t('Create the classes first (School setup › Classes).') })}
                        {f('enrollment_type', t('Joined as'), 'select', { required: true, options: enrollmentTypes })}
                        {f('admission_date', t('Admission date'), 'date')}
                        {data.enrollment_type === 'transfer_in' && !editing && <>
                            {f('previous_school', t('Previous school'))}
                            {f('previous_school_code', t('Previous school code'))}
                        </>}
                    </div>
                </Section>

                <Section icon={HeartHandshake} title={t('Parents and guardians')} description={t('Up to three. The main contact receives the school’s SMS (fees, absences, results).')}>
                    <div className="space-y-4">
                        {data.guardians.map((g, i) => (
                            <GuardianFields key={i} index={i} guardian={g} errors={errors} relationships={relationships}
                                onChange={(next) => setGuardian(i, next)} onRemove={() => removeGuardian(i)} canRemove={data.guardians.length > 1 || !!g.id} />
                        ))}
                        {data.guardians.length < 3 && (
                            <Button variant="secondary" onClick={() => setData('guardians', [...data.guardians, blankGuardian(data.guardians.length === 0)])}>
                                <Plus className="h-4 w-4" />{t('Add a parent or guardian')}
                            </Button>
                        )}
                    </div>
                </Section>

                <Section icon={UserRound} title={t('More details')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {f('national_id', t('National ID'))}
                        <div />
                        {f('district', t('District'))}
                        {f('sector', t('Sector'))}
                        <div className="sm:col-span-2">{f('special_needs', t('Special needs or health information'), 'textarea', { help: t('Only staff with access to students see this.') })}</div>
                    </div>
                </Section>

                <div className="sticky bottom-4 flex flex-wrap justify-end gap-2">
                    {!editing && <Button variant="secondary" className="shadow-lg" disabled={form.processing} onClick={() => submit(true)}>{t('Save and add another')}</Button>}
                    <Button type="submit" className="shadow-lg" disabled={form.processing}><Save className="h-4 w-4" />{form.processing ? t('Saving…') : t('Save')}</Button>
                </div>
            </form>
        </AppLayout>
    );
}
