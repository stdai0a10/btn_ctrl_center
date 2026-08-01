import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

export default function ServiceManagerShow({ userPublicId }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    useEffect(() => {
        loadUser();
    }, [userPublicId]);

    async function loadUser() {
        setLoading(true);
        setError('');
        try {
            const response = await window.axios.get(`/manage/api/service-managers/${userPublicId}`);
            setUser(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '服務管理員詳細資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    async function changeRole(operation) {
        setProcessing(true);
        setError('');
        setNotice('');
        try {
            const response = await window.axios.post(`/manage/api/service-managers/${userPublicId}/${operation}`);
            setNotice(response.data.message);
            await loadUser();
        } catch (caught) {
            setError(errorMessage(caught, '角色異動失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title={`服務管理員 ${userPublicId}`} />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Service Manager Detail</p>
                    <h1>{user?.display_name ?? userPublicId}</h1>
                    <Link className="text-link" href="/manage/service-managers">返回服務管理員一覽</Link>
                </section>

                {loading && <p className="muted">載入中...</p>}
                {error && <div className="notice error">{error}</div>}
                {notice && <div className="notice success">{notice}</div>}

                {user && (
                    <section className="grid-2">
                        <section className="panel">
                            <div className="panel-heading">
                                <h2>使用者資料</h2>
                                <div className="compact-actions">
                                    <span className="status-pill">{user.has_service_manager ? '服務管理員' : '一般使用者'}</span>
                                    {user.has_service_manager
                                        ? <button type="button" className="button-danger status-action-button" disabled={processing} onClick={() => changeRole('revoke')}>撤銷服務管理員</button>
                                        : <button type="button" className="status-action-button" disabled={processing || user.status !== 'active'} onClick={() => changeRole('grant')}>授予服務管理員</button>}
                                </div>
                            </div>
                            <dl className="detail-list">
                                <Detail label="公開 ID" value={user.public_id} />
                                <Detail label="顯示名稱" value={user.display_name} />
                                <Detail label="Email" value={user.email} />
                                <Detail label="帳號狀態" value={user.status} />
                                <Detail label="建立時間" value={formatDate(user.created_at)} />
                                <Detail label="最近登入" value={formatDate(user.last_login_at)} />
                            </dl>
                        </section>

                        <section className="panel">
                            <h2>角色與權限</h2>
                            <h3>Roles</h3>
                            <div className="permission-list">{user.roles.map((role) => <span className="status-pill" key={role}>{role}</span>)}</div>
                            <h3>Permissions</h3>
                            <div className="permission-list">{user.permissions.map((permission) => <span className="status-pill" key={permission}>{permission}</span>)}</div>
                        </section>

                        <LogPanel title="最近管理後台登入" items={user.recent_manage_logins} render={(log) => (
                            <span>{formatDate(log.created_at)} · {log.success ? '成功' : `失敗 (${log.failure_reason ?? '-'})`} · {log.ip_address ?? '-'}</span>
                        )} />
                        <LogPanel title="最近身分異動" items={user.recent_role_changes} render={(log) => (
                            <span>{formatDate(log.created_at)} · {log.action} · 操作者 {log.actor_public_id ?? log.actor_type}</span>
                        )} />
                        <section className="panel manage-wide-panel">
                            <h2>最近管理後台操作</h2>
                            {user.recent_manage_actions.length === 0 && <p className="muted">沒有操作紀錄</p>}
                            <div className="item-list">
                                {user.recent_manage_actions.map((log) => (
                                    <div className="list-item" key={log.id}>
                                        <strong>{log.action}</strong>
                                        <span>{formatDate(log.created_at)} · 操作者 {log.actor_public_id ?? log.actor_type} · 目標 {log.target_public_id ?? '-'}</span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </section>
                )}
            </ManageLayout>
        </>
    );
}

function Detail({ label, value }) {
    return <div><dt>{label}</dt><dd>{value ?? '-'}</dd></div>;
}

function LogPanel({ title, items, render }) {
    return (
        <section className="panel">
            <h2>{title}</h2>
            {items.length === 0 && <p className="muted">沒有紀錄</p>}
            <div className="item-list">
                {items.map((item) => <div className="list-item" key={item.id}>{render(item)}</div>)}
            </div>
        </section>
    );
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('zh-TW', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
