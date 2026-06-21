import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Be Vietnam Pro"', ...defaultTheme.fontFamily.sans],
                mono: ['"Space Mono"', ...defaultTheme.fontFamily.mono],
            },
            // Design tokens — handoff BỐP CAMPING (RULES.md mục 1, 10)
            colors: {
                grass: { DEFAULT: '#557A2B', light: '#7FA64B' },
                pine: '#2C3D22',
                moss: '#5C6E47',
                ink: '#18230F',
                campfire: '#C97B36',
                card: '#FBFCF7',
                cardBorder: '#E3E8D6',
                skyTop: '#a9d8f0',
                skyMid: '#dceff3',
            },
            borderRadius: {
                card: '16px',
                control: '12px',
                pill: '999px',
            },
            boxShadow: {
                cardhover: '0 20px 40px -22px rgba(44,61,34,.5)',
                btn: '0 12px 24px -10px rgba(85,122,43,.6)',
                hero: '0 30px 60px -24px rgba(44,61,34,.5)',
                toast: '0 16px 40px -12px rgba(44,61,34,.6)',
            },
        },
    },

    plugins: [forms],
};
