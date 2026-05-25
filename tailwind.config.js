export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"DM Sans"', 'system-ui', '-apple-system', 'sans-serif'],
            },
            colors: {
                primary: {
                    50:  '#effefa',
                    100: '#c8fff1',
                    200: '#91fee3',
                    300: '#52f5d4',
                    400: '#1edebf',
                    500: '#06c4a7',
                    600: '#019e89',
                    700: '#067e6f',
                    800: '#0a6359',
                    900: '#0d524a',
                    950: '#003330',
                },
                // Tinted neutrals — slate-teal undertone, never pure gray
                surface: {
                    50:  '#f7f9f9',
                    100: '#edf1f1',
                    200: '#d8e0e0',
                    300: '#b5c2c2',
                    400: '#8b9e9e',
                    500: '#6e8383',
                    600: '#596c6c',
                    700: '#495858',
                    800: '#3e4b4b',
                    900: '#293434',
                    950: '#161e1e',
                },
            },
            transitionTimingFunction: {
                'out-quart': 'cubic-bezier(0.25, 1, 0.5, 1)',
                'out-expo':  'cubic-bezier(0.16, 1, 0.3, 1)',
                'spring':    'cubic-bezier(0.34, 1.56, 0.64, 1)',
            },
            transitionDuration: {
                '120': '120ms',
                '160': '160ms',
            },
            keyframes: {
                'fade-in-up': {
                    '0%':   { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'scale-in': {
                    '0%':   { opacity: '0', transform: 'scale(0.96)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.4s cubic-bezier(0.25, 1, 0.5, 1)',
                'scale-in':   'scale-in 0.3s cubic-bezier(0.25, 1, 0.5, 1)',
            },
        },
    },
    plugins: [],
};
