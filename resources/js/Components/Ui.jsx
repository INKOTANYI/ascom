import { useT } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect } from 'react';

export function Card({ className = '', children }) {
    return <div className={`rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 ${className}`}>{children}</div>;
}

export function Button({ variant = 'primary', className = '', ...props }) {
    const styles = {
        primary: 'bg-brand text-white hover:bg-brand-dark shadow-sm',
        secondary: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
        danger: 'bg-red-600 text-white hover:bg-red-700',
        ghost: 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
    };
    return (
        <button
            type="button"
            {...props}
            className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:opacity-50 ${styles[variant]} ${className}`}
        />
    );
}

export function Badge({ children, tone = 'brand' }) {
    const tones = {
        brand: 'bg-brand-soft text-brand-dark dark:bg-brand/20 dark:text-brand-soft',
        slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    };
    return <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${tones[tone]}`}>{children}</span>;
}

/** A side panel for add/edit forms; closes with Escape or the backdrop. */
export function Drawer({ open, title, onClose, children, footer }) {
    const t = useT();
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" aria-label={title}>
            <button type="button" className="absolute inset-0 bg-slate-900/40" onClick={onClose} aria-label={t('Close')} />
            <div className="relative flex h-full w-full max-w-lg flex-col bg-white shadow-2xl dark:bg-slate-900">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 className="text-lg font-bold">{title}</h2>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label={t('Close')}>
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-5 py-5">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">{footer}</div>}
            </div>
        </div>
    );
}

export function Empty({ children }) {
    return <div className="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">{children}</div>;
}

export function PageHeader({ title, description, actions }) {
    return (
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                {description && <p className="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

/** Page links of a Laravel paginator. */
export function Pagination({ paginator }) {
    const t = useT();
    if (!paginator || paginator.last_page <= 1) return null;

    return (
        <nav className="flex flex-wrap items-center justify-center gap-1 border-t border-slate-200 p-3 dark:border-slate-800" aria-label={t('Pages')}>
            {paginator.links.map((l, i) => (
                <button key={i} type="button" disabled={!l.url} aria-current={l.active ? 'page' : undefined}
                    onClick={() => l.url && router.get(l.url, {}, { preserveState: true, preserveScroll: false })}
                    className={`min-w-9 rounded-lg px-3 py-1.5 text-sm ${l.active ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800'}`}
                    dangerouslySetInnerHTML={{ __html: l.label }} />
            ))}
        </nav>
    );
}

/** A person's photo, or their initials on the brand colour. */
export function Avatar({ name, src, size = 'h-10 w-10', className = '' }) {
    const initials = (name || '?').split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();

    return src
        ? <img src={src} alt="" className={`${size} shrink-0 rounded-full object-cover ring-1 ring-slate-200 dark:ring-slate-700 ${className}`} />
        : <span aria-hidden="true" className={`${size} inline-flex shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-bold text-brand-dark dark:bg-brand/20 dark:text-brand-soft ${className}`}>{initials}</span>;
}
