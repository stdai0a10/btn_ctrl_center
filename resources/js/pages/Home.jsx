import { Head, Link } from '@inertiajs/react';

export default function Home({ appName }) {
    return (
        <>
            <Head title="首頁" />
            <main>
                <h1>{appName}</h1>
                <p>Laravel 12 + Inertia + React</p>
                <Link href="/about">About</Link>
            </main>
        </>
    )
};
