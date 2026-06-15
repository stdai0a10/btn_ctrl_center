import { Head, Link } from '@inertiajs/react';

export default function Home({ appName }) {
    return (
        <>
            <Head title="首頁" />
            <main>
                <h1>{appName}</h1>
                <p>Laravel 12 + Inertia + React</p>
                <Link href="/houses">房屋管理</Link>
                {' · '}
                <Link href="/login">登入</Link>
                {' · '}
                <Link href="/register">註冊</Link>
            </main>
        </>
    )
};
