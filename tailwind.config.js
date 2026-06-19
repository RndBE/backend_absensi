/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                primary: {
                    DEFAULT: '#4F46E5',
                    light: '#6366F1',
                    dark: '#3730A3',
                    50: '#EEF2FF',
                },
                accent: {
                    DEFAULT: '#06B6D4',
                    light: '#22D3EE',
                },
                sidebar: {
                    from: '#1E1B4B',
                    via: '#312E81',
                    to: '#3730A3',
                },
            },
            spacing: {
                4.5: '1.125rem',
            },
        },
    },
    plugins: [],
};
