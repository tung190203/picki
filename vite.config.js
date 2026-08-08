import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import svgLoader from 'vite-svg-loader'

export default defineConfig({
    base: '/build/',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // `refresh: true` khiến Laravel quét toàn bộ app view + vendor, đó là nguyên nhân chính
            // gây ENOSPC. Vô hiệu hoá, vẫn giữ HMR qua chokidar ở phần server.watch bên dưới.
            refresh: false,
        }),
        vue(),
        svgLoader({
            defaultImport: 'component',
        }),
    ],
    server: {
        watch: {
            // ENOSPC fix: chỉ watch resources/, public/, routes/, app/, config/, database/
            // Bỏ qua vendor/, node_modules/, storage/, bootstrap/cache/, .git/, public/build/
            // để tránh tràn giới hạn inotify của Linux.
            ignored: [
                '**/vendor/**',
                '**/node_modules/**',
                '**/storage/framework/**',
                '**/storage/logs/**',
                '**/bootstrap/cache/**',
                '**/.git/**',
                '**/public/build/**',
                '**/public/storage/**',
                '**/tests/**',
            ],
            // Tránh watcher đi sâu vào từng symlink/file ẩn
            ignoreInitial: true,
            persistent: true,
        },
        // Chỉ theo dõi đúng gốc (cwd) và các thư mục cần thiết
        fs: {
            strict: false,
        },
    },
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
});
