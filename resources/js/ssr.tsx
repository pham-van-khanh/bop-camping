import { documentTitle } from '@/lib/seo';
import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import { route, type Config } from 'ziggy-js';

/**
 * Render phía server cho trang khách (bopcamping-zvrx) — để HTML gửi về đã có sẵn
 * h1, nội dung và link nội bộ, crawler không phải chạy JS mới thấy.
 *
 * Laravel chỉ gửi trang khách sang đây (App\Ssr\PublicPagesGateway lọc bỏ Admin/
 * Shipper). Process chết hay lỗi thì Laravel tự rơi về render phía client như cũ.
 *
 * `ziggy`: cấu hình route() mà trình duyệt nhận qua @routes. Gateway gửi kèm trong
 * payload, ở đây tách ra trước khi render — nếu để lại, Inertia sẽ in nó vào
 * data-page và mọi trang nặng thêm ~16 KB vô ích.
 */
type SsrPage = Parameters<Parameters<typeof createServer>[0]>[0] & {
    ziggy?: Config & { location: string };
};

const PORT = Number(process.env.INERTIA_SSR_PORT) || 13714;

createServer(
    (incoming) => {
        const { ziggy, ...page } = incoming as SsrPage;

        // Component gọi route(...) lúc render → cần bản cho đúng request này.
        globalThis.route = ((
            name?: string,
            params?: unknown,
            absolute?: boolean,
        ) =>
            route(
                name as string,
                params as never,
                absolute,
                ziggy && {
                    ...ziggy,
                    location: new URL(ziggy.location),
                },
            )) as typeof route;

        return createInertiaApp({
            page,
            render: ReactDOMServer.renderToString,
            title: documentTitle,
            resolve: (name) =>
                resolvePageComponent(
                    `./Pages/${name}.tsx`,
                    import.meta.glob('./Pages/**/*.tsx'),
                ),
            setup: ({ App, props }) => <App {...props} />,
        });
    },
    { port: PORT },
);
