import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from "path";
import tailwindcss from "@tailwindcss/vite";
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        ViteImageOptimizer({
            svg: {
                multipass: true,
                plugins: [
                    {
                        name: 'preset-default',
                        params: {
                            overrides: {
                                cleanupNumericValues: false,
                                removeViewBox: false, // https://github.com/svg/svgo/issues/1128
                            },
                            cleanupIDs: {
                                minify: false,
                                remove: false,
                            },
                            convertPathData: false,
                        },
                    },
                    'sortAttrs',
                    {
                        name: 'addAttributesToSVGElement',
                        params: {
                            attributes: [{ xmlns: 'http://www.w3.org/2000/svg' }],
                        },
                    },
                ],
            },
            png: {
                quality: 70,
            },
            jpeg: {
                quality: 70,
            },
            jpg: {
                quality: 70,
            },
            tiff: {
                quality: 70,
            },
            webp: {
                // https://sharp.pixelplumbing.com/api-output#webp
                lossless: true,
            },
        }),
    ],
    resolve: {
        extensions: [".js", ".jsx"]
    },
    server: {
        host: '0.0.0.0',
        port: 5173,

        hmr: {
            host: 'localhost',
            port: 5173,
        },
    },
});
