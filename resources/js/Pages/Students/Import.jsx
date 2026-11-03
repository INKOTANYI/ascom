import Field from '@/Components/Field';
import { Badge, Button, Card, PageHeader } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Download, FileSpreadsheet, ListChecks, Upload } from 'lucide-react';

function Step({ n, title, children, done }) {
    return (
        <Card className="p-5 sm:p-6">
            <div className="mb-4 flex items-center gap-3">
                <span className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ${done ? 'bg-brand text-white' : 'bg-brand-soft text-brand-dark dark:bg-brand/20 dark:text-brand-soft'}`}>
                    {done ? <CheckCircle2 className="h-4 w-4" /> : n}
                </span>
                <h2 className="text-lg font-bold">{title}</h2>
            </div>
            {children}
        </Card>
    );
}

/** Import a student list from Excel or CSV: upload, match the columns, check, then import. */
export default function Import({ sheet, report, fields, classes, enrollmentTypes, year }) {
    const t = useT();
    const upload = useForm({ file: null });
    const form = useForm({
        token: sheet?.token ?? '', action: 'check', mapping: sheet?.mapping ?? [],
        class_id: '', enrollment_type: 'new', update_existing: false,
    });

    // A new file arrives on the same page, so start the form again from its columns.
    useEffect(() => {
        if (sheet) form.setData((d) => ({ ...d, token: sheet.token, mapping: sheet.mapping }));
    }, [sheet?.token]);

    const run = (action) => {
        form.transform((d) => ({ ...d, action }));
        form.post(route('students.import.run'), { preserveScroll: true });
    };
    // Choosing a field for one column takes it away from any other column.
    const setColumn = (i, value) => form.setData('mapping', form.data.mapping.map((m, j) => (j === i ? value : m === value && value ? null : m)));
    const mapped = new Set(form.data.mapping.filter(Boolean));
    const checked = report && report.action === 'check' && sheet;
    const result = report?.result;

    return (
        <AppLayout title={t('Import from Excel')}>
            <Link href={route('students.index')} className="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
                <ArrowLeft className="h-4 w-4" />{t('Students')}
            </Link>
            <PageHeader title={t('Import from Excel')} description={t('Register many students at once from an SDMS list or the school’s own Excel file. Nothing is saved until you press Import.')} />

            {result && (
                <Card className="mb-6 border-brand/40 p-5 sm:p-6">
                    <div className="flex items-start gap-3">
                        <CheckCircle2 className="mt-0.5 h-6 w-6 text-brand" />
                        <div className="flex-1">
                            <h2 className="text-lg font-bold">{t('Import finished')}</h2>
                            <p className="mt-1 text-sm">{t('New students: :a · Updated: :b · Skipped: :c', { a: result.created, b: result.updated, c: result.skipped })}</p>
                            {result.failed.length > 0 && (
                                <ul className="mt-3 space-y-1 text-sm text-red-700 dark:text-red-300">
                                    {result.failed.map((f) => <li key={f.line}>{t('Row :n', { n: f.line })} · {f.name}: {f.error}</li>)}
                                </ul>
                            )}
                            <Link href={route('students.index')} className="mt-3 inline-block text-sm font-semibold text-brand hover:underline">{t('See the students')} →</Link>
                        </div>
                    </div>
                </Card>
            )}

            <div className="space-y-6">
                <Step n={1} title={t('Choose the file')} done={!!sheet}>
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="max-w-xl space-y-2 text-sm text-slate-600 dark:text-slate-300">
                            <p>{t('Excel (.xlsx, .xls) or CSV, one student per row, with a header row. Any column order works: you match the columns in the next step.')}</p>
                            <a href={route('students.import.template')} className="inline-flex items-center gap-1.5 font-semibold text-brand hover:underline">
                                <Download className="h-4 w-4" />{t('Download the template')}
                            </a>
                        </div>
                        <form onSubmit={(e) => { e.preventDefault(); upload.post(route('students.import.upload'), { forceFormData: true }); }} className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 px-4 py-2.5 text-sm hover:border-brand dark:border-slate-600">
                                <FileSpreadsheet className="h-4 w-4 text-brand" />
                                <span className="max-w-56 truncate">{upload.data.file?.name || sheet?.name || t('Choose a file…')}</span>
                                <input type="file" accept=".xlsx,.xls,.csv,.ods" className="sr-only" onChange={(e) => upload.setData('file', e.target.files?.[0] ?? null)} />
                            </label>
                            <Button type="submit" disabled={!upload.data.file || upload.processing}><Upload className="h-4 w-4" />{upload.processing ? t('Reading…') : t('Read the file')}</Button>
                        </form>
                    </div>
                    {upload.errors.file && <p className="mt-2 text-sm font-medium text-red-600">{upload.errors.file}</p>}
                </Step>

                {sheet && (
                    <Step n={2} title={t('Match the columns')} done={!!checked}>
                        <p className="mb-4 text-sm text-slate-600 dark:text-slate-300">
                            {t(':name: :n rows found. Check what each column contains; columns set to "Not imported" are ignored.', { name: sheet.name, n: sheet.count })}
                        </p>
                        {form.errors.mapping && <p role="alert" className="mb-3 rounded-lg bg-red-50 p-3 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">{form.errors.mapping}</p>}
                        <div className="relative overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                    <tr><th className="px-4 py-2.5">{t('Column in the file')}</th><th className="px-4 py-2.5">{t('Examples')}</th><th className="px-4 py-2.5">{t('Contains')}</th></tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {sheet.headers.map((h, i) => (
                                        <tr key={i} className={form.data.mapping[i] ? '' : 'opacity-60'}>
                                            <td className="px-4 py-2.5 font-semibold">{h || t('(no title)')}</td>
                                            <td className="max-w-xs truncate px-4 py-2.5 text-slate-500">{sheet.sample.slice(0, 3).map((r) => r[i]).filter((v) => v !== null && v !== '').join(' · ')}</td>
                                            <td className="px-4 py-2">
                                                <select aria-label={t('Contains')} value={form.data.mapping[i] ?? ''} onChange={(e) => setColumn(i, e.target.value || null)}
                                                    className="w-56 rounded-lg border-slate-300 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                                                    <option value="">{t('Not imported')}</option>
                                                    {fields.map((f) => <option key={f.value} value={f.value}>{f.label}{mapped.has(f.value) && form.data.mapping[i] !== f.value ? ' ✓' : ''}</option>)}
                                                </select>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-5 grid gap-4 sm:grid-cols-3">
                            <Field field={{ name: 'class_id', label: t('Class in :year', { year: year?.name ?? '' }), type: 'select',
                                options: classes, help: mapped.has('class') ? t('Leave empty to use the Class column of the file.') : t('Choose the class of all the students in this file.') }}
                                value={form.data.class_id} onChange={(v) => form.setData('class_id', v ?? '')} error={form.errors.class_id} />
                            <Field field={{ name: 'enrollment_type', label: t('Joined as'), type: 'select', required: true, options: enrollmentTypes }}
                                value={form.data.enrollment_type} onChange={(v) => form.setData('enrollment_type', v)} />
                            <div className="pt-6">
                                <Field field={{ name: 'update_existing', label: t('Update students already registered (same SDMS ID)'), type: 'checkbox' }}
                                    value={form.data.update_existing} onChange={(v) => form.setData('update_existing', v)} />
                            </div>
                        </div>
                        <div className="mt-5 flex justify-end">
                            <Button onClick={() => run('check')} disabled={form.processing}><ListChecks className="h-4 w-4" />{form.processing ? t('Checking…') : t('Check the file')}</Button>
                        </div>
                    </Step>
                )}

                {checked && (
                    <Step n={3} title={t('Check and import')}>
                        <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {[
                                [t('New students'), report.create, 'brand'],
                                [t('Will be updated'), report.update, 'slate'],
                                [t('Already registered, skipped'), report.skip, 'slate'],
                                [t('Rows with errors'), report.error_count, report.error_count ? 'red' : 'slate'],
                            ].map(([label, value, tone]) => (
                                <div key={label} className="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <p className={`text-2xl font-bold ${tone === 'red' ? 'text-red-600' : tone === 'brand' ? 'text-brand' : ''}`}>{value}</p>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">{label}</p>
                                </div>
                            ))}
                        </div>

                        {report.error_count > 0 && (
                            <div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                                <p className="mb-2 flex items-center gap-2 font-semibold text-red-800 dark:text-red-200"><AlertTriangle className="h-4 w-4" />{t('These rows will not be imported. Correct them in the file and read it again, or import the others now.')}</p>
                                <ul className="max-h-64 space-y-1 overflow-y-auto text-sm text-red-800 dark:text-red-200">
                                    {report.errors.map((e) => <li key={e.line}><strong>{t('Row :n', { n: e.line })}</strong>{e.name && ` · ${e.name}`}: {e.messages.join(' ')}</li>)}
                                </ul>
                            </div>
                        )}

                        {report.preview.length > 0 && (
                            <div className="relative mb-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                        <tr>{[t('Row'), t('Name'), t('Gender'), t('Date of birth'), t('Class'), t('Parent'), t('Phone'), ''].map((h, i) => <th key={i} className="px-3 py-2">{h}</th>)}</tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {report.preview.map((p) => (
                                            <tr key={p.line}>
                                                <td className="px-3 py-2 text-slate-400">{p.line}</td>
                                                <td className="px-3 py-2 font-semibold">{p.name}</td>
                                                <td className="px-3 py-2">{p.gender}</td>
                                                <td className="px-3 py-2">{p.date_of_birth ?? '—'}</td>
                                                <td className="px-3 py-2">{p.class ?? '—'}</td>
                                                <td className="px-3 py-2">{p.guardian ?? '—'}</td>
                                                <td className="px-3 py-2 whitespace-nowrap">{p.phone ?? '—'}</td>
                                                <td className="px-3 py-2"><Badge tone={p.action === 'create' ? 'brand' : 'slate'}>{p.action === 'create' ? t('New') : p.action === 'update' ? t('Update') : t('Skip')}</Badge></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <div className="flex flex-wrap items-center justify-end gap-3">
                            <span className="text-sm text-slate-500">{t('Each change is saved in the history.')}</span>
                            <Button onClick={() => run('import')} disabled={form.processing || report.create + report.update === 0}>
                                <Upload className="h-4 w-4" />{form.processing ? t('Importing…') : t('Import :n students', { n: report.create + report.update })}
                            </Button>
                        </div>
                    </Step>
                )}
            </div>
        </AppLayout>
    );
}
