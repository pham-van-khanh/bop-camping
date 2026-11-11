import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            // Bundle render phía server (bopcamping-zvrx) → bootstrap/ssr/ssr.js.
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        // route() phía server: dùng bản JS đi kèm package PHP tightenco/ziggy
        // (trình duyệt thì nhận qua @routes). tsconfig đã trỏ y hệt cho type.
        alias: { 'ziggy-js': path.resolve('vendor/tightenco/ziggy') },
    },
});
