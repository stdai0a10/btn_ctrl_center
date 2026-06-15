import { Head } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';

export default function Home({ appName }) {
    return (
        <>
            <Head title="首頁" />
            <AppLayout>
                <section className="home-panel">
                    <h1>{appName}</h1>
                    <p>Laravel 12 + Inertia + React</p>
                </section>
            </AppLayout>
        </>
    )
};
