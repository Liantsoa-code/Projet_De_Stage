import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                foret: { DEFAULT: '#00683A', fonce: '#00532E', pale: '#DCEDE2' },
                signal: { DEFAULT: '#C80000', pale: '#FBE3E1' },
                riviere: { DEFAULT: '#1D5C8A', pale: '#DFEAF3' },
                laterite: { DEFAULT: '#9A5F0C', pale: '#F6EAD3' },
                encre: { DEFAULT: '#15201A', 2: '#4B5850', 3: '#6E7A72' },
                papier: '#F2F4EF',
                filet: '#D3DAD1',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            // Même échelle que les variables --r-* de app.css
            borderRadius: {
                sm: '4px',
                DEFAULT: '8px',
                md: '8px',
                lg: '12px',
                xl: '16px',
                '2xl': '24px',
            },
        },
    },

    plugins: [forms],
};
