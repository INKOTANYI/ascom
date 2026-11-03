import { usePage } from '@inertiajs/react';

const hexToRgb = (hex) => {
    const h = (hex || '#0E7C4A').replace('#', '');
    return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
};

const mix = (rgb, target, amount) => rgb.map((c, i) => Math.round(c + (target[i] - c) * amount));

/**
 * CSS variables for the school's colours from Settings: bg-brand, text-brand, bg-brand-soft, bg-accent...
 * Put the returned style on a layout's root element.
 */
export function useBrandStyle() {
    const { school } = usePage().props;
    const brand = hexToRgb(school?.brand_color);
    const accent = hexToRgb(school?.accent_color || '#E3A008');

    return {
        '--brand': brand.join(' '),
        '--brand-dark': mix(brand, [0, 0, 0], 0.35).join(' '),
        '--brand-soft': mix(brand, [255, 255, 255], 0.88).join(' '),
        '--accent': accent.join(' '),
    };
}
