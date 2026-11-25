import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    /**
     * Configuration de compilation des assets (JS/CSS) :
     * - Cible 'es2020' pour transpiler les Class Static Blocks (ES2022 `static { ... }`)
     *   et garantir une compatibilité totale sur les postes clients (Chrome 80+, Edge 80+).
     * - Regroupement intelligent des dépendances stables (`codeSplitting`) afin de réduire
     *   drastiquement le nombre de micro-chunks HTTP/1.1 (de 35-56 requêtes JS à ~6-10)
     *   tout en maximisant le cache navigateur longue durée entre toutes les pages.
     */
    build: {
        target: 'es2020',
        rolldownOptions: {
            output: {
                codeSplitting: {
                    minSize: 15000,
                    groups: [
                        {
                            name: 'vendor-react',
                            test: /node_modules[\\/](react|react-dom|scheduler)[\\/]/,
                            priority: 30,
                        },
                        {
                            name: 'vendor-inertia',
                            test: /node_modules[\\/]@inertiajs[\\/]/,
                            priority: 25,
                        },
                        {
                            name: 'vendor-ui',
                            test: /node_modules[\\/](@radix-ui|sonner|class-variance-authority|clsx|tailwind-merge)[\\/]/,
                            priority: 20,
                        },
                        {
                            name: 'vendor-table',
                            test: /node_modules[\\/]@tanstack[\\/]/,
                            priority: 20,
                        },
                        {
                            name: 'vendor-icons',
                            test: /node_modules[\\/]lucide-react[\\/]/,
                            priority: 15,
                        },
                    ],
                },
            },
        },
    },
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'public/**',
            'vendor/**',
            'storage/**',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
