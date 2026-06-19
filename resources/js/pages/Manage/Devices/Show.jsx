import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

export default function ManageDeviceShow({ serialNumber }) {
    const [device, setDevice] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadDevice(1);
    }, [serialNumber]);

    async function loadDevice(page = 1) {
        setLoading(true);
        setError('');
        try {
            const response = await window.axios.get(`/manage/api/devices/${encodeURIComponent(serialNumber)}`, {
                params: { transfers_page: page },
            });
            setDevice(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '設備詳細資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    const transfers = device?.transfer_logs;

    return (
        <>
            <Head title={`設備 ${serialNumber}`} />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Device Detail</p>
                    <h1>{device?.serial_number ?? serialNumber}</h1>
                    <Link className="text-link" href="/manage/devices">返回設備一覽</Link>
                </section>
                {loading && <p className="muted">載入中...</p>}
                {error && <div className="notice error">{error}</div>}
                {device && (
                    <section className="grid-2">
                        <section className="panel">
                            <div className="panel-heading"><h2>設備資料</h2><span className="status-pill">{device.assignment_status === 'assigned' ? '已加入房間' : '未加入房間'}</span></div>
                            <dl className="detail-list">
                                <Detail label="設備序號" value={device.serial_number} />
                                <Detail label="設備名稱" value={device.name ?? '-'} />
                                <Detail label="鎖定狀態" value={device.is_locked ? '已上鎖' : '未上鎖'} />
                                <Detail label="啟用狀態" value={device.is_enabled ? '已啟用' : '已停用'} />
                                <Detail label="建立時間" value={formatDate(device.created_at)} />
                                <Detail label="更新時間" value={formatDate(device.updated_at)} />
                            </dl>
                        </section>
                        <section className="panel">
                            <h2>目前房間</h2>
                            {device.room ? (
                                <dl className="detail-list">
                                    <Detail label="房間公開 ID" value={<Link className="inline-link account-code" href={`/manage/rooms/${device.room.public_id}`}>{device.room.public_id}</Link>} />
                                    <Detail label="房間名稱" value={device.room.name} />
                                    <Detail label="房間狀態" value={device.room.status === 'deleted' ? '已刪除' : '正常'} />
                                </dl>
                            ) : <p className="muted">設備尚未加入房間</p>}
                        </section>
                        <section className="panel manage-wide-panel">
                            <div className="panel-heading"><h2>設備移轉紀錄</h2>{transfers && <span className="status-pill">{transfers.pagination.total} 筆</span>}</div>
                            {transfers?.items.length === 0 && <p className="muted">沒有移轉紀錄</p>}
                            {transfers?.items.length > 0 && (
                                <div className="table-wrap">
                                    <table className="data-table">
                                        <thead><tr><th>時間</th><th>原房間</th><th>目標房間</th><th>操作者</th></tr></thead>
                                        <tbody>{transfers.items.map((log) => (
                                            <tr key={log.id}>
                                                <td>{formatDate(log.created_at)}</td><td>{log.from_room_public_id ?? '-'}</td>
                                                <td>{log.to_room_public_id ?? '-'}</td><td>{log.transferred_by_user_public_id ?? '-'}</td>
                                            </tr>
                                        ))}</tbody>
                                    </table>
                                </div>
                            )}
                            <Pagination pagination={transfers?.pagination} loading={loading} onPage={loadDevice} />
                        </section>
                    </section>
                )}
            </ManageLayout>
        </>
    );
}

function Detail({ label, value }) {
    return <div><dt>{label}</dt><dd>{value}</dd></div>;
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
