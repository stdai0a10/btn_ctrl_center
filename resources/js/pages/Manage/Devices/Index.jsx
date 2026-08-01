import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

export default function ManageDevicesIndex() {
    const permissions = usePage().props.auth?.manage_permissions ?? [];
    const [filters, setFilters] = useState({
        search: '',
        room_public_id: '',
        assignment_status: 'all',
        locked: '',
        enabled: '',
    });
    const [payload, setPayload] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadDevices(1);
    }, []);

    async function loadDevices(page = 1) {
        setLoading(true);
        setError('');

        try {
            const response = await window.axios.get('/manage/api/devices', {
                params: {
                    ...filters,
                    locked: filters.locked === '' ? undefined : filters.locked,
                    enabled: filters.enabled === '' ? undefined : filters.enabled,
                    page,
                },
            });
            setPayload(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '設備資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    const devices = payload?.items ?? [];
    const pagination = payload?.pagination;

    return (
        <>
            <Head title="設備一覽" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Devices</p>
                    <div className="panel-heading">
                        <h1>設備一覽</h1>
                        {permissions.includes('manage.devices.create') && (
                            <Link className="button-link manage-header-action" href="/manage/devices/create">新增設備</Link>
                        )}
                    </div>
                </section>

                <section className="panel">
                    <form className="manage-audit-filters" onSubmit={(event) => { event.preventDefault(); loadDevices(1); }}>
                        <label>搜尋 <input value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="序號、設備名稱、產品或房間" /></label>
                        <label>房間公開 ID <input value={filters.room_public_id} onChange={(event) => setFilters({ ...filters, room_public_id: event.target.value })} /></label>
                        <label>
                            加入狀態
                            <select value={filters.assignment_status} onChange={(event) => setFilters({ ...filters, assignment_status: event.target.value })}>
                                <option value="all">全部</option>
                                <option value="assigned">已加入房間</option>
                                <option value="unassigned">未加入房間</option>
                            </select>
                        </label>
                        <BooleanFilter label="鎖定狀態" value={filters.locked} onChange={(locked) => setFilters({ ...filters, locked })} trueLabel="已上鎖" falseLabel="未上鎖" />
                        <BooleanFilter label="啟用狀態" value={filters.enabled} onChange={(enabled) => setFilters({ ...filters, enabled })} trueLabel="已啟用" falseLabel="已停用" />
                        <button type="submit" disabled={loading}>查詢</button>
                    </form>
                </section>

                {error && <div className="notice error">{error}</div>}

                <section className="panel">
                    <div className="panel-heading">
                        <h2>設備</h2>
                        {pagination && <span className="status-pill">{pagination.total} 筆</span>}
                    </div>
                    {loading && <p className="muted">載入中...</p>}
                    {!loading && devices.length === 0 && <p className="muted">沒有符合條件的設備</p>}
                    {devices.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>設備序號</th><th>產品</th><th>房間狀態</th><th>所在房間</th><th>設備名稱</th>
                                        <th>鎖定</th><th>啟用</th><th>建立時間</th><th>更新時間</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {devices.map((device) => (
                                        <tr key={device.serial_number} onClick={() => router.visit(`/manage/devices/${encodeURIComponent(device.serial_number)}`)}>
                                            <td className="account-code">{device.serial_number}</td>
                                            <td>{device.product ? `${device.product.model_number} · ${device.product.name}` : '未指定產品'}</td>
                                            <td>{device.assignment_status === 'assigned' ? '已加入' : '未加入'}</td>
                                            <td>{device.room ? `${device.room.name} (${device.room.public_id})` : '-'}</td>
                                            <td>{device.name ?? '-'}</td>
                                            <td>{device.is_locked ? '已上鎖' : '未上鎖'}</td>
                                            <td>{device.is_enabled ? '已啟用' : '已停用'}</td>
                                            <td>{formatDate(device.created_at)}</td>
                                            <td>{formatDate(device.updated_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination pagination={pagination} loading={loading} onPage={loadDevices} />
                </section>
            </ManageLayout>
        </>
    );
}

function BooleanFilter({ label, value, onChange, trueLabel, falseLabel }) {
    return (
        <label>
            {label}
            <select value={value} onChange={(event) => onChange(event.target.value)}>
                <option value="">全部</option>
                <option value="1">{trueLabel}</option>
                <option value="0">{falseLabel}</option>
            </select>
        </label>
    );
}

function Pagination({ pagination, loading, onPage }) {
    if (!pagination || pagination.last_page <= 1) return null;
    return (
        <div className="pagination-row">
            <button type="button" className="button-ghost" disabled={pagination.current_page <= 1 || loading} onClick={() => onPage(pagination.current_page - 1)}>上一頁</button>
            <span>{pagination.current_page} / {pagination.last_page}</span>
            <button type="button" className="button-ghost" disabled={pagination.current_page >= pagination.last_page || loading} onClick={() => onPage(pagination.current_page + 1)}>下一頁</button>
        </div>
    );
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('zh-TW', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
