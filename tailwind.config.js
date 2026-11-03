import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // The school's own colours, set in Settings (CSS variables written by the layouts).
            colors: {
                brand: {
                    DEFAULT: 'rgb(var(--brand) / <alpha-value>)',
                    dark: 'rgb(var(--brand-dark) / <alpha-value>)',
                    soft: 'rgb(var(--brand-soft) / <alpha-value>)',
                },
                accent: 'rgb(var(--accent) / <alpha-value>)',
            },
        },
    },

    plugins: [forms],
};
