import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AppLayout({ children, contentClassName = '' }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const user = usePage().props.auth?.user;
    const authLabel = user ? '登出' : '登入/註冊';

    async function logout() {
        await window.axios.post('/api/auth/logout');
        router.visit('/');
    }

    function authAction() {
        if (user) {
            logout();
            return;
        }

        router.visit('/login');
    }

    const navItems = [
        { label: '按鈕', href: '/buttons' },
        { label: '房間管理', href: '/rooms' },
        { label: '帳號管理', href: '/account/profile' },
    ];

    return (
        <div className="site-frame">
            <header className="site-header">
                <div className="site-header-inner">
                    <Link className="site-title" href="/">Button Control Center</Link>
                    <nav className="desktop-nav" aria-label="主要導覽">
                        {navItems.map((item) => (
                            <Link className="nav-button" href={item.href} key={item.href}>{item.label}</Link>
                        ))}
                        <button type="button" className="nav-button" onClick={authAction}>{authLabel}</button>
                    </nav>
                    <div className="mobile-menu">
                        <button
                            type="button"
                            className="nav-button"
                            aria-expanded={menuOpen}
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            menu
                        </button>
                        {menuOpen && (
                            <nav className="mobile-menu-list" aria-label="主要導覽">
                                {navItems.map((item) => (
                                    <Link className="mobile-menu-item" href={item.href} key={item.href}>{item.label}</Link>
                                ))}
                                <button type="button" className="mobile-menu-item" onClick={authAction}>{authLabel}</button>
                            </nav>
                        )}
                    </div>
                </div>
            </header>
            <main className={`site-main ${contentClassName}`}>
                {children}
            </main>
        </div>
    );
}
