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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['ui-monospace', 'SFMono-Regular', ...defaultTheme.fontFamily.mono],
            },
            // Bảng màu xanh cỏ — vibe thiên nhiên (xem KE_HOACH.md)
            colors: {
                ground: '#F1F4EA',
                surface: '#FBFCF7',
                ink: '#18230F',
                forest: { DEFAULT: '#2C3D22', deep: '#22341A' },
                grass: { DEFAULT: '#557A2B', soft: '#7FA64B' },
                moss: '#5C6E47',
                sand: '#E6D6A8',
                ember: '#C97B36',
            },
        },
    },

    plugins: [forms],
};
