import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Cấu hình riêng cho test component React (không dùng chung vite.config.js vì
 * plugin laravel-vite-plugin cần manifest/dev-server, vô nghĩa khi chạy test).
 * Test PHP vẫn do PHPUnit lo — phpunit.xml chỉ quét tests/Unit và tests/Feature
 * nên tests/js không đụng nhau.
 */
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./tests/js/setup.ts'],
        include: ['tests/js/**/*.test.{ts,tsx}'],
        restoreMocks: true,
    },
});
