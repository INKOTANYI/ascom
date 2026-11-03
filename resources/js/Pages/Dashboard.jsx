import { Card } from '@/Components/Ui';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpen, CalendarDays, CheckCircle2, Circle, GraduationCap, School, UserX } from 'lucide-react';

const fmt = (d, locale) => new Date(d + 'T00:00:00').toLocaleDateString(locale === 'rw' ? 'en-GB' : locale, { day: 'numeric', month: 'short', year: 'numeric' });

export default function Dashboard({ term, stats, steps }) {
    const t = useT();
    const { auth, school, locale } = usePage().props;
    const done = steps.filter((s) => s.done).length;
    const pct = steps.length ? Math.round((done / steps.length) * 100) : 100;

    const cards = [
        { label: 'Classes this year', value: stats.classes, icon: School },
        { label: 'Active staff', value: stats.staff, icon: GraduationCap },
        { label: 'Subjects', value: stats.subjects, icon: BookOpen },
        { label: 'Subjects without a teacher', value: stats.unassigned, icon: UserX, warn: stats.unassigned > 0 },
    ];

    return (
        <AppLayout title={t('Dashboard')}>
            <div className="relative mb-6 overflow-hidden rounded-2xl bg-brand-dark p-6 text-white sm:p-8">
                <div className="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-accent/25 blur-3xl" aria-hidden="true" />
                <p className="relative text-sm font-medium text-white/70">{school.name}</p>
                <h1 className="relative mt-1 text-2xl font-bold sm:text-3xl">{t('Welcome, :name', { name: auth.user.name.split(' ')[0] })}</h1>
                {term && (
                    <p className="relative mt-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm">
                        <CalendarDays className="h-4 w-4" />
                        {t('Term :n of :year', { n: term.number, year: term.year })} · {fmt(term.start, locale)} – {fmt(term.end, locale)}
                    </p>
                )}
            </div>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {cards.map(({ label, value, icon: Icon, warn }) => (
                    <Card key={label} className="p-5">
                        <span className={`flex h-10 w-10 items-center justify-center rounded-xl ${warn ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-brand-soft text-brand-dark dark:bg-brand/20 dark:text-brand-soft'}`}>
                            <Icon className="h-5 w-5" />
                        </span>
                        <p className="mt-3 text-3xl font-bold">{value}</p>
                        <p className="text-sm text-slate-500 dark:text-slate-400">{t(label)}</p>
                    </Card>
                ))}
            </div>

            {steps.length > 0 && (
                <Card className="mt-6 p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-bold">{t('Setting up the school')}</h2>
                            <p className="text-sm text-slate-500 dark:text-slate-400">{t(':done of :total steps done', { done, total: steps.length })}</p>
                        </div>
                        <span className="text-2xl font-bold text-brand">{pct}%</span>
                    </div>
                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div className="h-full rounded-full bg-brand transition-all" style={{ width: `${pct}%` }} />
                    </div>
                    <ol className="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                        {steps.map((step, i) => (
                            <li key={step.key}>
                                <Link href={route(step.route)} className="group flex items-center gap-3 py-3">
                                    {step.done
                                        ? <CheckCircle2 className="h-5 w-5 shrink-0 text-brand" />
                                        : <Circle className="h-5 w-5 shrink-0 text-slate-300 dark:text-slate-600" />}
                                    <span className={`flex-1 text-sm ${step.done ? 'text-slate-500 line-through decoration-slate-300' : 'font-medium'}`}>{i + 1}. {step.label}</span>
                                    {!step.done && <ArrowRight className="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-brand" />}
                                </Link>
                            </li>
                        ))}
                    </ol>
                </Card>
            )}
        </AppLayout>
    );
}
