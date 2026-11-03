import { useBrandStyle } from '@/lib/brand';
import { useCan, useT } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen, Building2, CalendarDays, CheckCircle2, ChevronDown, GraduationCap, Layers, LayoutDashboard, ListTree,
    LogOut, Menu, Moon, School, Settings, Shapes, Sun, UserCog, Users, X, AlertTriangle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const NAV = [
    { group: null, items: [{ label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard }] },
    {
        group: 'School setup',
        items: [
            { label: 'Settings', route: 'setup.settings', icon: Settings, can: 'settings.manage' },
            { label: 'Academic calendar', route: 'setup.calendar', icon: CalendarDays, can: 'calendar.manage' },
            { label: 'Levels', route: 'setup.levels', icon: Layers, can: 'structure.view' },
            { label: 'Combinations and trades', route: 'setup.combinations', icon: Shapes, can: 'structure.view' },
            { label: 'Subjects and modules', route: 'setup.subjects', icon: BookOpen, can: 'structure.view' },
            { label: 'Topics and units', route: 'setup.topics', icon: ListTree, can: 'structure.view' },
            { label: 'Classes', route: 'setup.classes', icon: School, can: 'structure.view', match: 'setup.classes*' },
        ],
    },
    {
        group: 'People',
        items: [
            { label: 'Staff', route: 'setup.staff', icon: GraduationCap, can: 'staff.view' },
            { label: 'Users and roles', route: 'setup.users', icon: UserCog, can: 'users.manage' },
        ],
    },
];

function useDark() {
    const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));
    const toggle = () => {
        const next = !dark;
        setDark(next);
        document.documentElement.classList.toggle('dark', next);
        try { localStorage.setItem('theme', next ? 'dark' : 'light'); } catch (e) { /* private mode */ }
    };
    return [dark, toggle];
}

export function LanguageSelect({ className = '' }) {
    const { locale, locales } = usePage().props;
    return (
        <select
            aria-label="Language"
            value={locale}
            onChange={(e) => router.post(route('language'), { locale: e.target.value }, { preserveScroll: true })}
            className={`rounded-lg border-slate-300 py-1.5 pl-2 pr-8 text-sm dark:border-slate-700 dark:bg-slate-900 ${className}`}
        >
            {Object.entries(locales).map(([code, name]) => <option key={code} value={code}>{name}</option>)}
        </select>
    );
}

function Sidebar({ onNavigate }) {
    const t = useT();
    const can = useCan();
    const { school } = usePage().props;

    return (
        <div className="flex h-full flex-col">
            <Link href={route('dashboard')} className="flex items-center gap-3 px-4 py-5" onClick={onNavigate}>
                {school.logo_url ? (
                    <img src={school.logo_url} alt="" className="h-11 w-11 rounded-xl bg-white object-contain p-1" />
                ) : (
                    <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-white"><Building2 className="h-6 w-6" /></span>
                )}
                <span className="min-w-0">
                    <span className="block truncate text-base font-bold text-white">{school.short_name}</span>
                    <span className="block text-[11px] font-medium uppercase tracking-wider text-white/60">Smart School</span>
                </span>
            </Link>
            <nav className="flex-1 space-y-5 overflow-y-auto px-3 pb-6" aria-label={t('Main menu')}>
                {NAV.map((section, i) => {
                    const items = section.items.filter((item) => !item.can || can(item.can));
                    if (!items.length) return null;
                    return (
                        <div key={i}>
                            {section.group && <p className="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-white/50">{t(section.group)}</p>}
                            <ul className="space-y-0.5">
                                {items.map((item) => {
                                    const active = route().current(item.match || item.route);
                                    const Icon = item.icon;
                                    return (
                                        <li key={item.route}>
                                            <Link
                                                href={route(item.route)}
                                                onClick={onNavigate}
                                                aria-current={active ? 'page' : undefined}
                                                className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ${active ? 'bg-white text-brand-dark shadow' : 'text-white/85 hover:bg-white/10 hover:text-white'}`}
                                            >
                                                <Icon className="h-[18px] w-[18px] shrink-0" />
                                                {t(item.label)}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    );
                })}
            </nav>
            {school.motto && <p className="border-t border-white/10 px-5 py-4 text-xs italic text-white/60">“{school.motto}”</p>}
        </div>
    );
}

function Flash() {
    const { flash } = usePage().props;
    const [shown, setShown] = useState(null);
    useEffect(() => {
        if (flash?.success || flash?.error) {
            setShown(flash.error ? { tone: 'error', text: flash.error } : { tone: 'success', text: flash.success });
            const id = setTimeout(() => setShown(null), 4500);
            return () => clearTimeout(id);
        }
    }, [flash]);
    if (!shown) return null;
    const ok = shown.tone === 'success';
    return (
        <div role="status" className={`fixed bottom-5 right-5 z-50 flex max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-xl ${ok ? 'bg-brand' : 'bg-red-600'}`}>
            {ok ? <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" /> : <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />}
            <span>{shown.text}</span>
            <button type="button" onClick={() => setShown(null)} className="ml-1 opacity-80 hover:opacity-100" aria-label="Close"><X className="h-4 w-4" /></button>
        </div>
    );
}

export default function AppLayout({ title, children }) {
    const t = useT();
    const brandStyle = useBrandStyle();
    const { auth, calendar, school } = usePage().props;
    const [open, setOpen] = useState(false);
    const [menu, setMenu] = useState(false);
    const [dark, toggleDark] = useDark();

    return (
        <div style={brandStyle} className="min-h-screen">
            <Head title={`${title} · ${school.short_name || school.name}`} />
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 bg-brand-dark lg:block">
                <Sidebar />
            </aside>
            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button type="button" className="absolute inset-0 bg-slate-900/50" onClick={() => setOpen(false)} aria-label={t('Close')} />
                    <aside className="relative h-full w-64 bg-brand-dark shadow-2xl"><Sidebar onNavigate={() => setOpen(false)} /></aside>
                </div>
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90 sm:px-6">
                    <button type="button" className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden" onClick={() => setOpen(true)} aria-label={t('Open menu')}>
                        <Menu className="h-5 w-5" />
                    </button>
                    <div className="min-w-0 flex-1">
                        {calendar && (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-brand-dark dark:bg-brand/20 dark:text-brand-soft">
                                <CalendarDays className="h-3.5 w-3.5" />
                                {t('Term :n', { n: calendar.term })} · {calendar.year}
                            </span>
                        )}
                    </div>
                    <LanguageSelect className="hidden sm:block" />
                    <button type="button" onClick={toggleDark} className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" aria-label={dark ? t('Light mode') : t('Dark mode')}>
                        {dark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
                    </button>
                    <div className="relative">
                        <button type="button" onClick={() => setMenu((v) => !v)} className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800" aria-expanded={menu}>
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">{auth.user.name.charAt(0)}</span>
                            <span className="hidden text-left sm:block">
                                <span className="block text-sm font-semibold leading-tight">{auth.user.name}</span>
                                <span className="block text-xs text-slate-500">{t(auth.user.role_label || '')}</span>
                            </span>
                            <ChevronDown className="h-4 w-4 text-slate-400" />
                        </button>
                        {menu && (
                            <div className="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                                <Link href={route('profile.edit')} className="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><Users className="h-4 w-4" />{t('My account')}</Link>
                                <div className="px-4 py-2 sm:hidden"><LanguageSelect className="w-full" /></div>
                                <Link href={route('logout')} method="post" as="button" className="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"><LogOut className="h-4 w-4" />{t('Log out')}</Link>
                            </div>
                        )}
                    </div>
                </header>

                {school.example_data && (
                    <div className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200 sm:px-6">
                        <AlertTriangle className="mr-1.5 inline h-4 w-4" />
                        {t('Example data: complete the school profile in Settings and check the subjects and codes against CAMIS / SDMS.')}
                    </div>
                )}

                <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{children}</main>
            </div>
            <Flash />
        </div>
    );
}
