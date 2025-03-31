import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import symfony from 'vite-plugin-symfony';
import path from 'path';

export default defineConfig({
    plugins: [
        vue(),
        symfony(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './assets/js'),
        }
    },
    build: {
        manifest: true,
        rollupOptions: {
            input: {
                app: './assets/js/app.js',
            },
        },
        outDir: './public/build',
    },
    server: {
        fs: {
            allow: ['..']
        },
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            }
        }
    }
});