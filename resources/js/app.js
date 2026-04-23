import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

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
