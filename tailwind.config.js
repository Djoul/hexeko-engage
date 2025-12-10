/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/tailwindui/**/*.{php,html,js}',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                // Neutral colors (Atlassian-inspired)
                'neutral': {
                    0: '#FFFFFF',
                    50: '#FAFBFC',
                    100: '#F4F5F7',
                    200: '#EBECF0',
                    300: '#DFE1E6',
                    400: '#C1C7D0',
                    500: '#A5ADBA',
                    600: '#6B778C',
                    700: '#505F79',
                    800: '#42526E',
                    900: '#344563',
                    950: '#172B4D',
                },
                // Primary - Blue Atlassian
                'primary': {
                    50: '#E9F2FF',
                    100: '#DEEBFF',
                    200: '#B3D4FF',
                    300: '#4C9AFF',
                    400: '#2684FF',
                    500: '#0C66E4', // Main primary color
                    600: '#0055CC',
                    700: '#0747A6',
                    800: '#003884',
                    900: '#002C69',
                    950: '#001F4C',
                },
                // Secondary - Purple UpEngage
                'secondary': {
                    50: '#F5F3FF',
                    100: '#EDE9FE',
                    200: '#DDD6FE',
                    300: '#C4B5FD',
                    400: '#A78BFA',
                    500: '#8B5CF6', // Main secondary color
                    600: '#7C3AED',
                    700: '#6D28D9',
                    800: '#5B21B6',
                    900: '#4C1D95',
                    950: '#2E1065',
                },
                // Success
                'success': {
                    50: '#E3FCEF',
                    100: '#ABF5D1',
                    200: '#79F2C0',
                    300: '#57D9A3',
                    400: '#36B37E',
                    500: '#00875A', // Main success color
                    600: '#006644',
                    700: '#00513B',
                    800: '#003D2E',
                    900: '#002921',
                },
                // Warning
                'warning': {
                    50: '#FFFAE6',
                    100: '#FFF0B3',
                    200: '#FFE380',
                    300: '#FFC400',
                    400: '#FFAB00', // Main warning color
                    500: '#FF991F',
                    600: '#FF8B00',
                    700: '#E56910',
                    800: '#CF5C00',
                    900: '#B85100',
                },
                // Error
                'error': {
                    50: '#FFEBE6',
                    100: '#FFBDAD',
                    200: '#FF8F73',
                    300: '#FF7452',
                    400: '#FF5630', // Main error color
                    500: '#DE350B',
                    600: '#BF2600',
                    700: '#A61300',
                    800: '#8D0003',
                    900: '#740000',
                },
                // Info
                'info': {
                    50: '#E9F2FF',
                    100: '#DEEBFF',
                    200: '#B3D4FF',
                    300: '#4C9AFF',
                    400: '#2684FF',
                    500: '#0065FF', // Main info color
                    600: '#0052CC',
                    700: '#0747A6',
                    800: '#003884',
                    900: '#002C69',
                },
                // Orange (UpPlus+ Brand Color)
                'orange': {
                    50: '#FFF7ED',
                    100: '#FFEDD5',
                    200: '#FED7AA',
                    300: '#FDB574',
                    400: '#FB923C',
                    500: '#FF8500', // UpPlus+ Brand Orange
                    600: '#EA6500',
                    700: '#C24E00',
                    800: '#9A3E00',
                    900: '#7C3200',
                },
            },
            fontFamily: {
                'sans': [
                    'Inter Variable',
                    'Inter',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    '"Segoe UI"',
                    'Roboto',
                    '"Helvetica Neue"',
                    'Arial',
                    'sans-serif',
                ],
                'mono': [
                    'JetBrains Mono',
                    'SFMono-Regular',
                    'SF Mono',
                    'Consolas',
                    'Liberation Mono',
                    'Menlo',
                    'monospace',
                ],
            },
            screens: {
                'xs': '475px',
                '3xl': '1920px',
            },
            spacing: {
                '18': '4.5rem',
                '88': '22rem',
                '128': '32rem',
            },
            transitionTimingFunction: {
                'in-expo': 'cubic-bezier(0.95, 0.05, 0.795, 0.035)',
                'out-expo': 'cubic-bezier(0.19, 1, 0.22, 1)',
            },
            animation: {
                'fadeIn': 'fadeIn 0.5s ease-in-out',
                'slideInRight': 'slideInRight 0.3s ease-out',
                'scaleUp': 'scaleUp 0.2s ease-out',
            },
            keyframes: {
                fadeIn: {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
                slideInRight: {
                    from: {
                        transform: 'translateX(100%)',
                        opacity: '0',
                    },
                    to: {
                        transform: 'translateX(0)',
                        opacity: '1',
                    },
                },
                scaleUp: {
                    from: {
                        transform: 'scale(0.95)',
                        opacity: '0',
                    },
                    to: {
                        transform: 'scale(1)',
                        opacity: '1',
                    },
                },
            },
            ringColor: {
                DEFAULT: '#0C66E4',
            },
            ringOffsetWidth: {
                DEFAULT: '2px',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        require('@tailwindcss/aspect-ratio'),
        require('@tailwindcss/container-queries'),
    ],
};
