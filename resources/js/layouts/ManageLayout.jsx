import { Link, router, usePage } from '@inertiajs/react';
import { Cpu, DoorOpen, LayoutDashboard, LogOut, PanelLeftClose, PanelLeftOpen, ShieldAlert, ShieldCheck, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

const navItems = [
    { label: '管理首頁', href: '/manage', icon: LayoutDashboard, permission: 'manage.dashboard.view' },
    { label: '使用者一覽', href: '/manage/users', icon: Users, permission: 'manage.users.view' },
    { label: '房間一覽', href: '/manage/rooms', icon: DoorOpen, permission: 'manage.rooms.view' },
    { label: '設備一覽', href: '/manage/devices', icon: Cpu, permission: 'manage.devices.view' },
    { label: '服務管理員', href: '/manage/service-managers', icon: ShieldCheck, permission: 'manage.service_managers.view' },
    { label: '審計資料', href: '/manage/audit', icon: ShieldAlert, permission: 'audit.access' },
];

export default function ManageLayout({ children }) {
    const { url, props } = usePage();
    const permissions = props.auth?.manage_permissions ?? [];
    const [collapsed, setCollapsed] = useState(() => window.localStorage.getItem('manageSidebarCollapsed') === 'true');
    const [processing, setProcessing] = useState(false);
    const ToggleIcon = collapsed ? PanelLeftOpen : PanelLeftClose;

    useEffect(() => {
        window.localStorage.setItem('manageSidebarCollapsed', collapsed ? 'true' : 'false');
    }, [collapsed]);

    async function logout() {
        setProcessing(true);

        try {
            const response = await window.axios.post('/manage/api/logout');
            router.visit(response.data.data.redirect_to ?? '/manage/login');
        } finally {
            setProcessing(false);
        }
    }

    return (
        <div className={`manage-shell ${collapsed ? 'is-collapsed' : ''}`}>
            <aside className="manage-sidebar" aria-label="管理後台導覽">
                <div className="manage-sidebar-top">
                    {!collapsed && (
                        <Link className="manage-sidebar-title" href="/manage">
                            <span>Button Control</span>
                            <strong>管理後台</strong>
                        </Link>
                    )}
                    <button
                        type="button"
                        className="manage-sidebar-toggle"
                        aria-label={collapsed ? '展開側邊欄' : '收合側邊欄'}
                        title={collapsed ? '展開側邊欄' : '收合側邊欄'}
                        onClick={() => setCollapsed((value) => !value)}
                    >
                        <ToggleIcon size={22} strokeWidth={2.4} />
                    </button>
                </div>

                <nav className="manage-nav">
                    {navItems.filter((item) => permissions.includes(item.permission)).map((item) => {
                        const Icon = item.icon;

                        return (
                            <Link
                                className={`manage-nav-item ${isActive(url, item.href) ? 'is-active' : ''}`}
                                href={item.href}
                                key={item.href}
                                title={collapsed ? item.label : undefined}
                            >
                                <span className="manage-nav-icon" aria-hidden="true">
                                    <Icon size={20} strokeWidth={2.2} />
                                </span>
                                {!collapsed && <span className="manage-nav-label">{item.label}</span>}
                            </Link>
                        );
                    })}
                </nav>

                <div className="manage-sidebar-actions">
                    <button
                        type="button"
                        className="manage-nav-item manage-sidebar-logout"
                        disabled={processing}
                        onClick={logout}
                        title={collapsed ? '登出管理後台' : undefined}
                    >
                        <span className="manage-nav-icon" aria-hidden="true">
                            <LogOut size={20} strokeWidth={2.2} />
                        </span>
                        {!collapsed && <span className="manage-nav-label">登出</span>}
                    </button>
                </div>
            </aside>

            <main className="manage-main">
                {children}
            </main>
        </div>
    );
}

function isActive(url, href) {
    if (href === '/manage') {
        return url === '/manage';
    }

    return url === href || url.startsWith(`${href}/`);
}
