import '../css/app.css';
import './bootstrap';

import {createInertiaApp} from '@inertiajs/react';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {createRoot} from 'react-dom/client';
import Layout from "@/Layouts/Layout.jsx";
import {Provider} from "react-redux";
import store from "./store/store";

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) =>{
        const pages = import.meta.glob('./Pages/**/*.jsx');

        const page = await pages[`./Pages/${name}.jsx`]();

        page.default.layout =
            page.default.layout ||
            ((page) => <Layout>{page}</Layout>);

        return page;
    },
    setup({el, App, props}) {
        const root = createRoot(el);

        root.render(
            <Provider store={store}>
                <App {...props} />
            </Provider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
