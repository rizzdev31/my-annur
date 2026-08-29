import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import "../css/app.css";

createInertiaApp({
    title: (title) => `${title} — An Nur Smart System`,

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Plugin Inertia
        app.use(plugin);

        // Ziggy — untuk helper route() di Vue
        app.use(ZiggyVue);

        // Directive v-click-outside — untuk tutup dropdown saat klik luar
        app.directive("click-outside", {
            mounted(el, binding) {
                el._clickOutsideHandler = (event) => {
                    if (!el.contains(event.target)) {
                        binding.value(event);
                    }
                };
                document.addEventListener("click", el._clickOutsideHandler);
            },
            unmounted(el) {
                document.removeEventListener("click", el._clickOutsideHandler);
            },
        });

        app.mount(el);
    },

    progress: {
        color: "#6366f1",
        showSpinner: false,
    },
});
