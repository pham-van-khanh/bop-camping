import '../css/app.css';
import './bootstrap';

import { initAnalytics } from '@/lib/analytics';
import { documentTitle } from '@/lib/seo';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';

// Đo lường marketing (bopcamping-wi6h): page view khi điều hướng SPA + click liên hệ.
// Đăng ký TRƯỚC createInertiaApp để bắt được cả sự kiện navigate của lần tải đầu.
initAnalytics((cb) => {
    router.on('navigate', (event) => cb(event.detail.page.url));
});

createInertiaApp({
    title: documentTitle,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        // Trang khách đã được server render sẵn (bopcamping-zvrx) → hydrate lên HTML
        // có sẵn. Trang không SSR (admin, SSR tắt/lỗi) thì #app rỗng → render mới.
        if (el.hasChildNodes()) {
            hydrateRoot(el, <App {...props} />);
            return;
        }

        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
