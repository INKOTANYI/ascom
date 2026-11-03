import { usePage } from '@inertiajs/react';

/**
 * English text is the key; French and Kinyarwanda come from lang/fr.json and lang/rw.json.
 * t('Term :n of :year', { n: 1, year: '2026-2027' })
 */
export function useT() {
    const { translations = {} } = usePage().props;

    return (key, replace = {}) => {
        let text = translations[key] ?? key;
        Object.entries(replace).forEach(([name, value]) => {
            text = text.replaceAll(`:${name}`, String(value));
        });
        return text;
    };
}

/** True when the signed-in user has the permission (admins and owners have all). */
export function useCan() {
    const { auth } = usePage().props;
    const codes = auth?.can ?? [];

    return (code) => codes.includes('*') || codes.includes(code);
}
