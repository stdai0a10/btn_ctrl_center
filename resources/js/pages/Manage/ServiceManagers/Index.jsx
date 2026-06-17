import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

const roleOptions = [
    { value: 'service_manager', label: '服務管理員' },
    { value: 'not_service_manager', label: '非服務管理員' },
    { value: 'all', label: '全部角色' },
];

const statusOptions = [
    { value: '', label: '全部狀態' },
    { value: 'active', label: '正常' },
    { value: 'pending', label: '待啟用' },
    { value: 'disabled', label: '停用' },
];

export default function ServiceManagersIndex() {
    const [filters, setFilters] = useState({ search: '', role: 'service_manager', status: '' });
    const [payload, setPayload] = useState(null);
    const [selected, setSelected] = useState([]);
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    useEffect(() => {
        loadUsers(1);
    }, []);

    async function loadUsers(page = 1, nextFilters = filters) {
        setLoading(true);
        setError('');

        try {
            const response = await window.axios.get('/manage/api/service-managers', {
                params: { ...nextFilters, page },
            });
            setPayload(response.data.data);
            setSelected([]);
        } catch (caught) {
            setError(errorMessage(caught, '服務管理員資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    async function changeRole(user, operation) {
        setProcessing(true);
        setError('');
        setNotice('');

        try {
            const response = await window.axios.post(`/manage/api/service-managers/${user.public_id}/${operation}`);
            setNotice(response.data.message);
            await loadUsers(payload?.pagination?.current_page ?? 1);
        } catch (caught) {
            setError(errorMessage(caught, '角色異動失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    async function changeMany(operation) {
        if (selected.length === 0) return;

        setProcessing(true);
        setError('');
        setNotice('');

        try {
            const response = await window.axios.post(`/manage/api/service-managers/${operation}-many`, {
                user_public_ids: selected,
            });
            setNotice(response.data.message);
            await loadUsers(payload?.pagination?.current_page ?? 1);
        } catch (caught) {
            setError(errorMessage(caught, '批次角色異動失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    function toggle(publicId) {
        setSelected((current) => current.includes(publicId)
            ? current.filter((item) => item !== publicId)
            : [...current, publicId]);
    }

    const users = payload?.items ?? [];
    const pagination = payload?.pagination;

    return (
        <>
            <Head title="服務管理員" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Service Managers</p>
                    <h1>服務管理員</h1>
                </section>

                <section className="panel">
                    <form className="inline-form manage-filter-form" onSubmit={(event) => { event.preventDefault(); loadUsers(1); }}>
                        <label>
                            搜尋
                            <input
                                value={filters.search}
                                onChange={(event) => setFilters({ ...filters, search: event.target.value })}
                                placeholder="公開 ID、Email、顯示名稱"
                            />
                        </label>
                        <label>
                            角色
                            <select value={filters.role} onChange={(event) => setFilters({ ...filters, role: event.target.value })}>
                                {roleOptions.map((option) => <option value={option.value} key={option.value}>{option.label}</option>)}
                            </select>
                        </label>
                        <label>
                            狀態
                            <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
                                {statusOptions.map((option) => <option value={option.value} key={option.value}>{option.label}</option>)}
                            </select>
                        </label>
                        <button type="submit" disabled={loading}>查詢</button>
                    </form>
                </section>

                {error && <div className="notice error">{error}</div>}
                {notice && <div className="notice success">{notice}</div>}

                <section className="panel">
                    <div className="panel-heading">
                        <h2>使用者</h2>
                        <div className="compact-actions">
                            {pagination && <span className="status-pill">{pagination.total} 筆</span>}
                            <button type="button" disabled={processing || selected.length === 0} onClick={() => changeMany('grant')}>批次授權</button>
                            <button type="button" className="button-danger" disabled={processing || selected.length === 0} onClick={() => changeMany('revoke')}>批次撤銷</button>
                        </div>
                    </div>

                    {loading && <p className="muted">載入中...</p>}
                    {!loading && users.length === 0 && <p className="muted">沒有符合條件的使用者</p>}

                    {users.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>選取</th>
                                        <th>公開 ID</th>
                                        <th>顯示名稱</th>
                                        <th>Email</th>
                                        <th>狀態</th>
                                        <th>服務管理員</th>
                                        <th>建立時間</th>
                                        <th>最近登入</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.public_id} onClick={() => router.visit(`/manage/service-managers/${user.public_id}`)}>
                                            <td onClick={(event) => event.stopPropagation()}>
                                                <input type="checkbox" checked={selected.includes(user.public_id)} onChange={() => toggle(user.public_id)} aria-label={`選取 ${user.public_id}`} />
                                            </td>
                                            <td className="account-code">{user.public_id}</td>
                                            <td>{user.display_name}</td>
                                            <td>{user.email ?? '-'}</td>
                                            <td>{statusLabel(user.status)}</td>
                                            <td>{user.has_service_manager ? '是' : '否'}</td>
                                            <td>{formatDate(user.created_at)}</td>
                                            <td>{formatDate(user.last_login_at)}</td>
                                            <td onClick={(event) => event.stopPropagation()}>
                                                <div className="compact-actions">
                                                    <button type="button" className="button-ghost" onClick={() => router.visit(`/manage/service-managers/${user.public_id}`)}>詳細</button>
                                                    {user.has_service_manager
                                                        ? <button type="button" className="button-danger" disabled={processing} onClick={() => changeRole(user, 'revoke')}>撤銷</button>
                                                        : <button type="button" disabled={processing || user.status !== 'active'} onClick={() => changeRole(user, 'grant')}>授權</button>}
                                                </div>
                                            </td>
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
            </ManageLayout>
        </>
    );
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('zh-TW', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function statusLabel(status) {
    return { active: '正常', pending: '待啟用', disabled: '停用', deleted: '刪除' }[status] ?? status;
}
