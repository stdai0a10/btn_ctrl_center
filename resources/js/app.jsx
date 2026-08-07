import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp, router } from '@inertiajs/react';
import './bootstrap';
import '../css/app.css';

// Cloudflare Challenge Pages must run as top-level visits, not in Inertia's
// non-Inertia response iframe.
let latestInertiaVisitUrl = window.location.href;

router.on('before', (event) => {
    latestInertiaVisitUrl = event.detail.visit.url.href;
});

router.on('httpException', (event) => {
    if (event.detail.response.headers['cf-mitigated'] !== 'challenge') {
        return;
    }

    event.preventDefault();
    window.location.assign(latestInertiaVisitUrl);
});

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob('./pages/**/*.jsx')
        return (await pages[`./pages/${name}.jsx`]()).default
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
    title: (title) => (title ? `${title} - MyApp` : 'MyApp'),
})
