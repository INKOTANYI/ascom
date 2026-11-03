import { inputClass } from '@/Components/Field';
import { LanguageSelect } from '@/Layouts/AppLayout';
import { useBrandStyle } from '@/lib/brand';
import { useT } from '@/lib/i18n';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Building2, Eye, EyeOff, LogIn } from 'lucide-react';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const t = useT();
    const { school } = usePage().props;
    const brandStyle = useBrandStyle();
    const [show, setShow] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ login: '', password: '', remember: false });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <div style={brandStyle} className="flex min-h-screen">
            <Head title={`${t('Log in')} · ${school.short_name || school.name}`} />
            <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-brand-dark p-12 text-white lg:flex">
                <div className="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-accent/20 blur-3xl" aria-hidden="true" />
                <div className="relative flex items-center gap-3">
                    {school.logo_url
                        ? <img src={school.logo_url} alt="" className="h-14 w-14 rounded-2xl bg-white object-contain p-1.5" />
                        : <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15"><Building2 className="h-7 w-7" /></span>}
                    <span className="text-xl font-bold">{school.name}</span>
                </div>
                <div className="relative">
                    <h1 className="text-4xl font-bold leading-tight">{t('Every learner, every mark, every franc in one place.')}</h1>
                    <p className="mt-4 max-w-md text-white/75">{t('Attendance, marks the CAMIS way, report cards, fees and messages to parents.')}</p>
                </div>
                <p className="relative text-sm text-white/60">{school.motto ? `“${school.motto}”` : 'Smart School'}</p>
            </div>

            <div className="flex w-full flex-col items-center justify-center px-6 py-10 lg:w-1/2">
                <div className="mb-8 flex w-full max-w-sm items-center justify-between">
                    <div className="flex items-center gap-2 lg:hidden">
                        {school.logo_url ? <img src={school.logo_url} alt="" className="h-10 w-10 rounded-xl object-contain" /> : <Building2 className="h-8 w-8 text-brand" />}
                        <span className="font-bold">{school.short_name}</span>
                    </div>
                    <LanguageSelect className="ml-auto" />
                </div>
                <div className="w-full max-w-sm">
                    <h2 className="text-2xl font-bold">{t('Log in')}</h2>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{t('Use your phone number, username or email.')}</p>
                    {status && <div className="mt-4 rounded-lg bg-brand-soft px-3 py-2 text-sm font-medium text-brand-dark">{status}</div>}

                    <form onSubmit={submit} className="mt-6 space-y-4">
                        <div>
                            <label htmlFor="login" className="block text-sm font-medium">{t('Phone, username or email')}</label>
                            <input id="login" value={data.login} autoComplete="username" autoFocus onChange={(e) => setData('login', e.target.value)} className={inputClass} placeholder="0788 123 456" />
                            {errors.login && <p className="mt-1 text-xs font-medium text-red-600">{errors.login}</p>}
                        </div>
                        <div>
                            <div className="flex items-center justify-between">
                                <label htmlFor="password" className="block text-sm font-medium">{t('Password')}</label>
                                {canResetPassword && <Link href={route('password.request')} className="text-xs font-semibold text-brand hover:underline">{t('Forgot password?')}</Link>}
                            </div>
                            <div className="relative">
                                <input id="password" type={show ? 'text' : 'password'} value={data.password} autoComplete="current-password" onChange={(e) => setData('password', e.target.value)} className={`${inputClass} pr-10`} />
                                <button type="button" onClick={() => setShow((v) => !v)} className="absolute inset-y-0 right-0 mt-1 flex items-center px-3 text-slate-400 hover:text-slate-600" aria-label={show ? t('Hide password') : t('Show password')}>
                                    {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                            {errors.password && <p className="mt-1 text-xs font-medium text-red-600">{errors.password}</p>}
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} className="rounded border-slate-300 text-brand focus:ring-brand" />
                            {t('Keep me signed in')}
                        </label>
                        <button type="submit" disabled={processing} className="flex w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-brand-dark disabled:opacity-60">
                            <LogIn className="h-4 w-4" /> {processing ? t('Signing in…') : t('Log in')}
                        </button>
                    </form>
                    <p className="mt-8 text-center text-xs text-slate-400">{t('Accounts are created by the school. Ask the head teacher or DOS if you have none.')}</p>
                </div>
            </div>
        </div>
    );
}
