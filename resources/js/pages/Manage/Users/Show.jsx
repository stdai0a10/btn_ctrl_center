import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { errorMessage } from '../../../lib/http';

export default function ManageUserShow({ userPublicId }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadUser();
    }, [userPublicId]);

    async function loadUser() {
        setLoading(true);
        setError('');

        try {
            const response = await window.axios.get(`/manage/api/users/${userPublicId}`);
            setUser(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '使用者資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    return (
        <>
            <Head title={user ? user.display_name : '使用者詳細'} />
            <AppLayout>
                <section className="page-header">
                    <p className="eyebrow">Management User</p>
                    <h1>{user ? user.display_name : '使用者詳細'}</h1>
                    <Link className="text-link" href="/manage/users">返回使用者一覽</Link>
                </section>

                {loading && <p className="muted">載入中...</p>}
                {error && <div className="notice error">{error}</div>}

                {user && (
                    <section className="grid-2">
                        <section className="panel">
                            <div className="panel-heading">
                                <h2>基本資料</h2>
                                <span className="status-pill">{statusLabel(user.status)}</span>
                            </div>
                            <dl className="detail-list">
                                <Detail label="使用者公開 ID" value={user.public_id} />
                                <Detail label="顯示名稱" value={user.display_name} />
                                <Detail label="Email" value={user.email ?? '-'} />
                                <Detail label="LINE 綁定" value={user.line_bound ? '已綁定' : '未綁定'} />
                                <Detail label="建立時間" value={formatDate(user.created_at)} />
                                <Detail label="最近登入" value={formatDate(user.last_login_at)} />
                            </dl>
                        </section>

                        <section className="panel">
                            <div className="panel-heading">
                                <h2>權限</h2>
                                <span className="status-pill">{user.roles.length > 0 ? user.roles.join(', ') : '無角色'}</span>
                            </div>
                            <div className="permission-list">
                                {user.permissions.length > 0
                                    ? user.permissions.map((permission) => <span className="status-pill" key={permission}>{permission}</span>)
                                    : <p className="muted">沒有權限</p>}
                            </div>
                        </section>

                        <section className="panel manage-wide-panel">
                            <div className="panel-heading">
                                <h2>已加入的房間</h2>
                                <span className="status-pill">{user.rooms.length} 間</span>
                            </div>
                            {user.rooms.length === 0 && <p className="muted">尚未加入任何房間</p>}
                            {user.rooms.length > 0 && (
                                <div className="table-wrap">
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>房間公開 ID</th>
                                                <th>房間名稱</th>
                                                <th>房間內身分</th>
                                                <th>加入時間</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {user.rooms.map((room) => (
                                                <tr key={room.public_id}>
                                                    <td><Link className="inline-link account-code" href={`/manage/rooms/${room.public_id}`}>{room.public_id}</Link></td>
                                                    <td>{room.name}</td>
                                                    <td>{room.role === 'owner' ? '房主' : '住戶'}</td>
                                                    <td>{formatDate(room.joined_at)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </section>
                )}
            </AppLayout>
        </>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('zh-TW', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function statusLabel(status) {
    return {
        active: '正常',
        pending: '待啟用',
        disabled: '停用',
        deleted: '刪除',
    }[status] ?? status;
}
