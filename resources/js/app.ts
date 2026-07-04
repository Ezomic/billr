import { createApp, h, DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { Toaster } from '@/components/ui/sonner';

createInertiaApp({
    title: (title) => title ? `${title} — Billr` : 'Billr',
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // Mount Toaster globally
        app.component('Toaster', Toaster);
        app.mount(el);
    },
    progress: {
        color: '#4f46e5',
    },
});
