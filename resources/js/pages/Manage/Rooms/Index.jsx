import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { errorMessage } from '../../../lib/http';

const statusOptions = [
    { value: '', label: '全部狀態' },
    { value: 'active', label: '正常' },
    { value: 'deleted', label: '已刪除' },
];

export default function ManageRoomsIndex() {
    const [filters, setFilters] = useState({ search: '', status: '' });
    const [payload, setPayload] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadRooms(1);
    }, []);

    async function loadRooms(page = 1, nextFilters = filters) {
        setLoading(true);
        setError('');

        try {
            const response = await window.axios.get('/manage/api/rooms', {
                params: { ...nextFilters, page },
            });
            setPayload(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '房間資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    function submit(event) {
        event.preventDefault();
        loadRooms(1);
    }

    const rooms = payload?.items ?? [];
    const pagination = payload?.pagination;

    return (
        <>
            <Head title="房間一覽" />
            <AppLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Rooms</p>
                    <h1>房間一覽</h1>
                </section>

                <section className="panel">
                    <form className="inline-form manage-filter-form" onSubmit={submit}>
                        <label>
                            搜尋
                            <input
                                value={filters.search}
                                onChange={(event) => setFilters({ ...filters, search: event.target.value })}
                                placeholder="房間 ID、房間名稱、建立者"
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
                        <h2>房間</h2>
                        {pagination && <span className="status-pill">{pagination.total} 筆</span>}
                    </div>

                    {loading && <p className="muted">載入中...</p>}
                    {!loading && rooms.length === 0 && <p className="muted">沒有符合條件的房間</p>}

                    {rooms.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>房間公開 ID</th>
                                        <th>房間名稱</th>
                                        <th>建立者</th>
                                        <th>建立時間</th>
                                        <th>成員數量</th>
                                        <th>房主數量</th>
                                        <th>狀態</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rooms.map((room) => (
                                        <tr key={room.public_id} onClick={() => router.visit(`/manage/rooms/${room.public_id}`)}>
                                            <td className="account-code">{room.public_id}</td>
                                            <td>{room.name}</td>
                                            <td>{room.creator?.display_name ?? '-'}</td>
                                            <td>{formatDate(room.created_at)}</td>
                                            <td>{room.members_count}</td>
                                            <td>{room.owners_count}</td>
                                            <td>{statusLabel(room.status)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination && pagination.last_page > 1 && (
                        <div className="pagination-row">
                            <button type="button" className="button-ghost" disabled={pagination.current_page <= 1 || loading} onClick={() => loadRooms(pagination.current_page - 1)}>上一頁</button>
                            <span>{pagination.current_page} / {pagination.last_page}</span>
                            <button type="button" className="button-ghost" disabled={pagination.current_page >= pagination.last_page || loading} onClick={() => loadRooms(pagination.current_page + 1)}>下一頁</button>
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
        deleted: '已刪除',
    }[status] ?? status;
}
