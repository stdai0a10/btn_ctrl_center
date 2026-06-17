import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { errorMessage } from '../../../lib/http';

const statusOptions = [
    { value: '', label: '全部狀態' },
    { value: 'active', label: '正常' },
    { value: 'pending', label: '待啟用' },
    { value: 'disabled', label: '停用' },
];

export default function ManageUsersIndex() {
    const [filters, setFilters] = useState({ search: '', status: '' });
    const [payload, setPayload] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadUsers(1);
    }, []);

    async function loadUsers(page = 1, nextFilters = filters) {
        setLoading(true);
        setError('');

        try {
            const response = await window.axios.get('/manage/api/users', {
                params: { ...nextFilters, page },
            });
            setPayload(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '使用者資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    function submit(event) {
        event.preventDefault();
        loadUsers(1);
    }

    const users = payload?.items ?? [];
    const pagination = payload?.pagination;

    return (
        <>
            <Head title="使用者一覽" />
            <AppLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Users</p>
                    <h1>使用者一覽</h1>
                </section>

                <section className="panel">
                    <form className="inline-form manage-filter-form" onSubmit={submit}>
                        <label>
                            搜尋
                            <input
                                value={filters.search}
                                onChange={(event) => setFilters({ ...filters, search: event.target.value })}
                                placeholder="公開 ID、Email、顯示名稱"
                            />
                        </label>
                        <label>
                            狀態
                            <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
                                {statusOptions.map((option) => (
                                    <option value={option.value} key={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <button type="submit" disabled={loading}>查詢</button>
                    </form>
                </section>

                {error && <div className="notice error">{error}</div>}

                <section className="panel">
                    <div className="panel-heading">
                        <h2>使用者</h2>
                        {pagination && <span className="status-pill">{pagination.total} 筆</span>}
                    </div>

                    {loading && <p className="muted">載入中...</p>}
                    {!loading && users.length === 0 && <p className="muted">沒有符合條件的使用者</p>}

                    {users.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>使用者公開 ID</th>
                                        <th>顯示名稱</th>
                                        <th>Email</th>
                                        <th>LINE</th>
                                        <th>建立時間</th>
                                        <th>最近登入</th>
                                        <th>狀態</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.public_id} onClick={() => router.visit(`/manage/users/${user.public_id}`)}>
                                            <td className="account-code">{user.public_id}</td>
                                            <td>{user.display_name}</td>
                                            <td>{user.email ?? '-'}</td>
                                            <td>{user.line_bound ? '已綁定' : '未綁定'}</td>
                                            <td>{formatDate(user.created_at)}</td>
                                            <td>{formatDate(user.last_login_at)}</td>
                                            <td>{statusLabel(user.status)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination && pagination.last_page > 1 && (
                        <div className="pagination-row">
                            <button type="button" className="button-ghost" disabled={pagination.current_page <= 1 || loading} onClick={() => loadUsers(pagination.current_page - 1)}>上一頁</button>
                            <span>{pagination.current_page} / {pagination.last_page}</span>
                            <button type="button" className="button-ghost" disabled={pagination.current_page >= pagination.last_page || loading} onClick={() => loadUsers(pagination.current_page + 1)}>下一頁</button>
                        </div>
                    )}
                </section>
            </AppLayout>
        </>
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
