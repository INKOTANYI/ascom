import { Avatar, Badge, Button, Card, Empty, PageHeader, Pagination } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { ChevronRight, Download, FileSpreadsheet, Phone, Plus, Search, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

export const STATUS_TONE = { active: 'brand', suspended: 'amber', transferred: 'slate', dropped_out: 'red', graduated: 'slate' };

const selectClass = 'rounded-lg border-slate-300 py-2 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900';

/** All students, filtered by class, status and gender; search by name, SDMS ID, registration number or parent phone. */
export default function Index({ students, filters, classes, statuses, summary, year, canManage }) {
    const t = useT();
    const [term, setTerm] = useState(filters.search || '');

    const apply = (changes) => {
        const next = { ...filters, search: term, ...changes };
        router.get(route('students.index'), Object.fromEntries(Object.entries(next).filter(([k, v]) => v !== null && v !== '' && !(k === 'status' && v === 'active'))), { preserveState: true, replace: true });
    };

    useEffect(() => {
        if (term === (filters.search || '')) return;
        const id = setTimeout(() => apply({ search: term }), 350);
        return () => clearTimeout(id);
    }, [term]);

    const exportUrl = route('students.export', Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== null && v !== '')));

    return (
        <AppLayout title={t('Students')}>
            <PageHeader
                title={t('Students')}
                description={year ? t('Students of the school and their class in :year.', { year: year.name }) : t('Add an academic year first.')}
                actions={<>
                    <a href={exportUrl} className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <Download className="h-4 w-4" />{t('Download Excel')}
                    </a>
                    {canManage && <>
                        <Link href={route('students.import')} className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                            <FileSpreadsheet className="h-4 w-4" />{t('Import from Excel')}
                        </Link>
                        <Link href={route('students.create', filters.class_id > 0 ? { class_id: filters.class_id } : {})}
                            className="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-dark">
                            <Plus className="h-4 w-4" />{t('Add student')}
                        </Link>
                    </>}
                </>}
            />

            <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[
                    { label: t('Active students'), value: summary.active, onClick: () => apply({ status: 'active', gender: null, class_id: null }) },
                    { label: t('Boys'), value: summary.boys, onClick: () => apply({ status: 'active', gender: 'M' }) },
                    { label: t('Girls'), value: summary.girls, onClick: () => apply({ status: 'active', gender: 'F' }) },
                    { label: t('Without a class this year'), value: summary.no_class, warn: summary.no_class > 0, onClick: () => apply({ status: 'active', class_id: -1 }) },
                ].map((s) => (
                    <button key={s.label} type="button" onClick={s.onClick}
                        className="rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-brand dark:border-slate-800 dark:bg-slate-900">
                        <p className={`text-2xl font-bold ${s.warn ? 'text-amber-600 dark:text-amber-400' : ''}`}>{s.value}</p>
                        <p className="text-sm text-slate-500 dark:text-slate-400">{s.label}</p>
                    </button>
                ))}
            </div>

            <Card>
                <div className="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center dark:border-slate-800">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={t('Name, SDMS ID, reg. number or parent phone…')} aria-label={t('Search')}
                            className="w-full rounded-lg border-slate-300 py-2 pl-9 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900" />
                    </div>
                    <div className="grid grid-cols-3 gap-2 lg:flex">
                        <select aria-label={t('Class')} value={filters.class_id ?? ''} onChange={(e) => apply({ class_id: e.target.value || null })} className={selectClass}>
                            <option value="">{t('All classes')}</option>
                            <option value="-1">{t('No class this year')}</option>
                            {classes.map((c) => <option key={c.value} value={c.value}>{c.label} ({c.students})</option>)}
                        </select>
                        <select aria-label={t('Status')} value={filters.status} onChange={(e) => apply({ status: e.target.value })} className={selectClass}>
                            {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                            <option value="all">{t('All statuses')}</option>
                        </select>
                        <select aria-label={t('Gender')} value={filters.gender ?? ''} onChange={(e) => apply({ gender: e.target.value || null })} className={selectClass}>
                            <option value="">{t('Boys and girls')}</option>
                            <option value="M">{t('Boys')}</option>
                            <option value="F">{t('Girls')}</option>
                        </select>
                    </div>
                </div>

                <p className="px-4 pt-3 text-sm text-slate-500 dark:text-slate-400">{t('Records: :n', { n: students.total })}</p>

                {students.data.length === 0 ? (
                    <Empty>
                        <Users className="mx-auto mb-2 h-8 w-8 text-slate-300" />
                        {filters.search || filters.class_id || filters.gender ? t('Nothing matches this search.') : t('No students yet. Add them one by one or import an Excel list.')}
                    </Empty>
                ) : (
                    <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                        {students.data.map((s) => (
                            <li key={s.id}>
                                <Link href={route('students.show', s.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 focus-visible:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <Avatar name={`${s.first_name} ${s.last_name}`} src={s.photo_url} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold">
                                            <span className="uppercase">{s.last_name}</span> {s.first_name}
                                            {s.status !== 'active' && <span className="ml-2"><Badge tone={STATUS_TONE[s.status]}>{s.status_label}</Badge></span>}
                                        </p>
                                        <p className="truncate text-xs text-slate-500 dark:text-slate-400">
                                            {[s.sdms_id && `SDMS ${s.sdms_id}`, s.reg_number && `${t('Reg.')} ${s.reg_number}`, s.gender === 'M' ? t('Boy') : t('Girl'), s.age !== null && t(':n years', { n: s.age })].filter(Boolean).join(' · ')}
                                        </p>
                                    </div>
                                    <div className="hidden w-48 text-sm md:block">
                                        {s.guardian ? <>
                                            <p className="truncate">{s.guardian.name}</p>
                                            <p className="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400"><Phone className="h-3 w-3" />{s.guardian.phone || '—'}</p>
                                        </> : <span className="text-xs text-amber-700 dark:text-amber-400">{t('No parent yet')}</span>}
                                    </div>
                                    <div className="w-24 text-right">
                                        {s.class ? <Badge tone="slate">{s.class}</Badge> : <span className="text-xs text-amber-700 dark:text-amber-400">{t('No class')}</span>}
                                    </div>
                                    <ChevronRight className="hidden h-4 w-4 text-slate-400 sm:block" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination paginator={students} />
            </Card>
        </AppLayout>
    );
}
