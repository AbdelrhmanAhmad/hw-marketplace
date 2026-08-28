import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#E7F4EC',
                    100: '#C4E6D1',
                    200: '#9AD5B3',
                    300: '#6FC494',
                    400: '#419E6C',
                    500: '#198754',
                    600: '#15703F',
                    700: '#115934',
                    800: '#0C4227',
                    900: '#082B1A',
                },
                gold: {
                    50: '#FEF7E7',
                    100: '#FCEAC0',
                    200: '#FADC96',
                    300: '#F9CD6C',
                    400: '#F7C043',
                    500: '#F6B41B',
                    600: '#D89A0C',
                    700: '#A87709',
                },
                forest: '#00793A',
                maroon: {
                    50: '#FBECE8',
                    100: '#F3D0C6',
                    200: '#E5A896',
                    300: '#D07D63',
                    400: '#B85238',
                    500: '#96371F',
                    600: '#742815',
                    700: '#53190D',
                    800: '#3A1108',
                },
                cream: '#FBF3EA',
            },
        },
    },

    plugins: [forms],
};
