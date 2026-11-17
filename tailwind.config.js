import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    safelist: ['dark', 'positive', 'negative', 'light'],
    plugins: [
        forms,
        function({ addVariant }) {
            addVariant('dark', '&.dark, .dark &');
            addVariant('light', '&.light, .light &');
            addVariant('positive', '&.positive, .positive &');
            addVariant('negative', '&.negative, .negative &');
        }
    ],
};
