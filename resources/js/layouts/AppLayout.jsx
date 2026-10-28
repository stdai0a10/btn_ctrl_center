import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { clearPendingAction, readPendingAction } from '../lib/pendingAction';

export default function AppLayout({ children, contentClassName = '' }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const [logoutState, setLogoutState] = useState('');
    const [loggingOut, setLoggingOut] = useState(false);
    const user = usePage().props.auth?.user;
    const authLabel = user ? '登出' : '登入/註冊';

    async function logout() {
        if (loggingOut || logoutState.includes('結果不確定')) return;
        if (window.navigator.onLine === false) {
            setLogoutState('目前離線，無法登出。請恢復連線後再試。'); return;
        }
        setLoggingOut(true);
        try {
            await window.axios.post('/api/auth/logout');
            clearPendingAction(); router.visit('/');
        } catch (caught) {
            setLogoutState(caught.response && caught.response.status < 500
                ? '登出失敗，請稍後再試。' : '登出結果不確定，請恢復連線後確認登入狀態。');
        } finally { setLoggingOut(false); }
    }

    useEffect(() => { readPendingAction(user?.public_id); }, [user?.public_id]);
    async function checkAuthentication() {
        try {
            await window.axios.get('/api/auth/me');
            setLogoutState('仍保持登入，請重新登出。');
        } catch (caught) {
            if (caught.response?.status === 401) { clearPendingAction(); router.visit('/'); }
        }
    }

    useEffect(() => {
        if (!logoutState.includes('結果不確定')) return undefined;
        window.addEventListener('online', checkAuthentication);
        return () => window.removeEventListener('online', checkAuthentication);
    }, [logoutState]);

    function authAction() {
        if (user) {
            logout();
            return;
        }

        window.location.assign('/login');
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
            <details className="site-main" style={{ paddingBlock: '0.75rem' }}>
                <summary style={{ cursor: 'pointer' }}>安裝到主畫面</summary>
                <p>iPhone／iPad：以 Safari 開啟，點選「分享」→「加入主畫面」。</p>
                <p>Android／電腦：開啟瀏覽器選單，選擇「安裝應用程式」或「加入主畫面」（依瀏覽器支援而定）。</p>
                <p>安裝後仍需網路連線才能操作按鈕。</p>
            </details>
            {logoutState && <div role="status" className="notice error">{logoutState}
                {logoutState.includes('結果不確定') && <button type="button" onClick={checkAuthentication}>確認登入狀態</button>}
            </div>}
            <main className={`site-main ${contentClassName}`}>
                {children}
            </main>
        </div>
    );
}
