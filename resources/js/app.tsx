import '../css/app.css';
import './bootstrap';

import { initAnalytics } from '@/lib/analytics';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// Đo lường marketing (bopcamping-wi6h): page view khi điều hướng SPA + click liên hệ.
// Đăng ký TRƯỚC createInertiaApp để bắt được cả sự kiện navigate của lần tải đầu.
initAnalytics((cb) => {
    router.on('navigate', (event) => cb(event.detail.page.url));
});

const appName = import.meta.env.VITE_APP_NAME || 'BỐP CAMPING';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
