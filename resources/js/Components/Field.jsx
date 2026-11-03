import { useT } from '@/lib/i18n';

export const inputClass =
    'mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 disabled:opacity-60';

/** Rwandan phone: +250 prefix and the 9 local digits (7XXXXXXXX). */
export function PhoneInput({ id, value, onChange, disabled }) {
    return (
        <div className="mt-1 flex rounded-lg shadow-sm">
            <span className="inline-flex items-center gap-1.5 rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                <svg viewBox="0 0 30 20" className="h-3.5 w-5 rounded-sm" aria-hidden="true">
                    <rect width="30" height="10" fill="#00A1DE" />
                    <rect y="10" width="30" height="5" fill="#FAD201" />
                    <rect y="15" width="30" height="5" fill="#20603D" />
                    <circle cx="24" cy="5" r="2.6" fill="#E5BE01" />
                </svg>
                +250
            </span>
            <input
                id={id}
                type="tel"
                inputMode="numeric"
                maxLength={9}
                placeholder="788123456"
                value={value ?? ''}
                disabled={disabled}
                onChange={(e) => onChange(e.target.value.replace(/\D/g, '').replace(/^250/, '').replace(/^0/, '').slice(0, 9))}
                className="block w-full rounded-r-lg border-slate-300 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            />
        </div>
    );
}

/**
 * One form field from a server description: { name, label, type, options, required, help, placeholder }.
 * Types: text, number, date, textarea, select, checkbox, phone, color.
 */
export default function Field({ field, value, onChange, error }) {
    const t = useT();
    const id = `f-${field.name}`;

    if (field.type === 'checkbox') {
        return (
            <label htmlFor={id} className="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700">
                <input id={id} type="checkbox" checked={!!value} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand" />
                <span className="font-medium">{field.label}</span>
            </label>
        );
    }

    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                {field.label}
                {field.required && <span className="text-red-600"> *</span>}
            </label>
            {field.type === 'select' ? (
                <select id={id} value={value ?? ''} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)} className={inputClass}>
                    <option value="">{field.required ? t('Choose…') : t('None')}</option>
                    {(field.options || []).map((o) => (
                        <option key={o.value} value={o.value}>{o.label}</option>
                    ))}
                </select>
            ) : field.type === 'textarea' ? (
                <textarea id={id} rows={3} value={value ?? ''} onChange={(e) => onChange(e.target.value)} className={inputClass} placeholder={field.placeholder} />
            ) : field.type === 'phone' ? (
                <PhoneInput id={id} value={value} onChange={onChange} />
            ) : field.type === 'color' ? (
                <div className="mt-1 flex items-center gap-3">
                    <input id={id} type="color" value={value || '#000000'} onChange={(e) => onChange(e.target.value)} className="h-10 w-14 cursor-pointer rounded border border-slate-300 bg-white p-1 dark:border-slate-700" />
                    <code className="text-sm text-slate-500">{value}</code>
                </div>
            ) : (
                <input
                    id={id}
                    type={field.type || 'text'}
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={field.placeholder}
                    className={inputClass}
                />
            )}
            {field.help && !error && <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{field.help}</p>}
            {error && <p className="mt-1 text-xs font-medium text-red-600">{error}</p>}
        </div>
    );
}
