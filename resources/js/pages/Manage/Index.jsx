import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../layouts/ManageLayout';
import { errorMessage } from '../../lib/http';

export default function ManageIndex() {
    const [dashboard, setDashboard] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadDashboard();
    }, []);

    async function loadDashboard() {
        setLoading(true);
        setError('');

        try {
            const response = await window.axios.get('/manage/api/dashboard');
            setDashboard(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '管理後台資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    const stats = dashboard?.stats ?? {};
    const admin = dashboard?.admin;

    return (
        <>
            <Head title="管理後台" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Management</p>
                    <h1>管理後台</h1>
                </section>

                {loading && <p className="muted">載入中...</p>}
                {error && <div className="notice error">{error}</div>}

                {dashboard && (
                    <>
                        <section className="stat-grid">
                            <Stat label="使用者總數" value={stats.users_count} />
                            <Stat label="房間總數" value={stats.rooms_count} />
                            <Stat label="今日新增使用者" value={stats.users_created_today_count} />
                            <Stat label="今日新增房間" value={stats.rooms_created_today_count} />
                        </section>

                        <section className="grid-2">
                            <section className="panel">
                                <div className="panel-heading">
                                    <h2>快速入口</h2>
                                </div>
                                <div className="item-list">
                                    <Link className="list-item manage-link-item" href="/manage/users">
                                        <div>
                                            <strong>使用者一覽</strong>
                                            <span>查看服務內所有使用者與加入的房間</span>
                                        </div>
                                    </Link>
                                    <Link className="list-item manage-link-item" href="/manage/rooms">
                                        <div>
                                            <strong>房間一覽</strong>
                                            <span>查看服務內所有房間與房間成員</span>
                                        </div>
                                    </Link>
                                    {admin.permissions.includes('manage.device_runtime.view') && (
                                        <Link className="list-item manage-link-item" href="/manage/device-runtime">
                                            <div>
                                                <strong>設備執行</strong>
                                                <span>查看設備 runtime、JWT 狀態與按鈕任務</span>
                                            </div>
                                        </Link>
                                    )}
                                    {admin.permissions.includes('manage.service_managers.view') && (
                                        <Link className="list-item manage-link-item" href="/manage/service-managers">
                                            <div>
                                                <strong>服務管理員</strong>
                                                <span>授予、撤銷與查看服務管理員身分</span>
                                            </div>
                                        </Link>
                                    )}
                                    {admin.permissions.includes('audit.access') && (
                                        <Link className="list-item manage-link-item" href="/manage/audit">
                                            <div>
                                                <strong>審計資料</strong>
                                                <span>查看管理後台登入失敗與操作紀錄</span>
                                            </div>
                                        </Link>
                                    )}
                                </div>
                            </section>

                            <section className="panel">
                                <div className="panel-heading">
                                    <h2>目前管理員</h2>
                                    <span className="status-pill">{admin.public_id}</span>
                                </div>
                                <dl className="detail-list">
                                    <div>
                                        <dt>顯示名稱</dt>
                                        <dd>{admin.display_name}</dd>
                                    </div>
                                    <div>
                                        <dt>Email</dt>
                                        <dd>{admin.email ?? '-'}</dd>
                                    </div>
                                    <div>
                                        <dt>管理角色</dt>
                                        <dd>{admin.roles.length > 0 ? admin.roles.join(', ') : '-'}</dd>
                                    </div>
                                    <div>
                                        <dt>最近登入管理後台</dt>
                                        <dd>{formatDateTime(dashboard.recent_manage_login_at)}</dd>
                                    </div>
                                </dl>
                                <div className="permission-list">
                                    {admin.permissions.map((permission) => (
                                        <span className="status-pill" key={permission}>{permission}</span>
                                    ))}
                                </div>
                            </section>
                        </section>
                    </>
                )}
            </ManageLayout>
        </>
    );
}

function Stat({ label, value }) {
    return (
        <article className="panel stat-card">
            <span>{label}</span>
            <strong>{value ?? 0}</strong>
        </article>
    );
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('zh-TW', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
