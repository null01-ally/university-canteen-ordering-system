export default {
    theme: {
        extend: {
            maxWidth: {
                dashboard: '90rem',
            },
            spacing: {
                'gutter-mobile': '1rem',
                'gutter-tablet': '1.5rem',
                'gutter-desktop': '2rem',
                sidebar: '16rem',
            },
            colors: {
                maroon: {
                    50: '#fcf3f5',
                    100: '#f5dfe4',
                    800: '#6b1d2f',
                    900: '#501322',
                    classic: '#800000',
                },
                gold: {
                    50: '#fffbeb',
                    400: '#d4af37',
                    500: '#f59e0b',
                },
                canteen: {
                    canvas: '#fdfbf7',
                    cream: '#f9f6f0',
                    surface: '#ffffff',
                },
                status: {
                    pending: { bg: '#fef3c7', text: '#78350f' },
                    preparing: { bg: '#dbeafe', text: '#1e3a8a' },
                    ready: { bg: '#dcfce7', text: '#14532d' },
                    completed: { bg: '#f3f4f6', text: '#374151' },
                },
            },
        },
    },
};
