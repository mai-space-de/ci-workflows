import { defineConfig } from 'vite'

export default defineConfig({
    publicDir: false,
    base: '',
    build: {
        manifest: 'manifest.json',
        assetsInlineLimit: 0,
        rollupOptions: {
            input: {
                'mai-turbo/mai-turbo': 'Resources/Private/JavaScript/mai-turbo.js',
            },
            output: {
                dir: 'public/_assets/vite/',
                format: 'es',
            },
        },
    },
})
