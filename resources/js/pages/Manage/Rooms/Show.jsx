import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

export default function ManageRoomShow({ roomPublicId }) {
    const [room, setRoom] = useState(null);
    const [devices, setDevices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const permissions = usePage().props.auth?.manage_permissions ?? [];
    const canViewDevices = permissions.includes('manage.devices.view');

    useEffect(() => {
        loadRoom();
    }, [roomPublicId]);

    async function loadRoom() {
        setLoading(true);
        setError('');

        try {
            const [roomResponse, deviceResponse] = await Promise.all([
                window.axios.get(`/manage/api/rooms/${roomPublicId}`),
                canViewDevices ? window.axios.get(`/manage/api/rooms/${roomPublicId}/devices`) : Promise.resolve(null),
            ]);
            setRoom(roomResponse.data.data);
            setDevices(deviceResponse?.data?.data?.items ?? []);
        } catch (caught) {
            setError(errorMessage(caught, '房間資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    return (
        <>
            <Head title={room ? room.name : '房間詳細'} />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Room</p>
                    <h1>{room ? room.name : '房間詳細'}</h1>
                    <Link className="text-link" href="/manage/rooms">返回房間一覽</Link>
                </section>

                {loading && <p className="muted">載入中...</p>}
                {error && <div className="notice error">{error}</div>}

                {room && (
                    <section className="grid-2">
                        <section className="panel">
                            <div className="panel-heading">
                                <h2>基本資料</h2>
                                <span className="status-pill">{statusLabel(room.status)}</span>
                            </div>
                            <dl className="detail-list">
                                <Detail label="房間公開 ID" value={room.public_id} />
                                <Detail label="房間名稱" value={room.name} />
                                <Detail label="建立者" value={room.creator ? room.creator.display_name : '-'} />
                                <Detail label="建立時間" value={formatDate(room.created_at)} />
                                <Detail label="成員數量" value={room.members_count} />
                                <Detail label="房主數量" value={room.owners_count} />
                            </dl>
                        </section>

                        <section className="panel">
                            <div className="panel-heading">
                                <h2>建立者</h2>
                            </div>
                            {room.creator ? (
                                <dl className="detail-list">
                                    <Detail label="使用者公開 ID" value={<Link className="inline-link account-code" href={`/manage/users/${room.creator.public_id}`}>{room.creator.public_id}</Link>} />
                                    <Detail label="顯示名稱" value={room.creator.display_name} />
                                    <Detail label="Email" value={room.creator.email ?? '-'} />
                                </dl>
                            ) : <p className="muted">無建立者資料</p>}
                        </section>

                        <section className="panel manage-wide-panel">
                            <div className="panel-heading">
                                <h2>房間內的使用者</h2>
                                <span className="status-pill">{room.members.length} 人</span>
                            </div>
                            {room.members.length === 0 && <p className="muted">房間內沒有使用者</p>}
                            {room.members.length > 0 && (
                                <div className="table-wrap">
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>使用者公開 ID</th>
                                                <th>顯示名稱</th>
                                                <th>Email</th>
                                                <th>房間內身分</th>
                                                <th>加入時間</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {room.members.map((member) => (
                                                <tr key={member.public_id}>
                                                    <td><Link className="inline-link account-code" href={`/manage/users/${member.public_id}`}>{member.public_id}</Link></td>
                                                    <td>{member.display_name}</td>
                                                    <td>{member.email ?? '-'}</td>
                                                    <td>{member.role === 'owner' ? '房主' : '住戶'}</td>
                                                    <td>{formatDate(member.joined_at)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                        {canViewDevices && (
                            <section className="panel manage-wide-panel">
                                <div className="panel-heading">
                                    <h2>房間內的設備</h2>
                                    <span className="status-pill">{devices.length} 部</span>
                                </div>
                                {devices.length === 0 && <p className="muted">房間內沒有設備</p>}
                                {devices.length > 0 && (
                                    <div className="table-wrap">
                                        <table className="data-table">
                                            <thead><tr><th>設備序號</th><th>設備名稱</th><th>鎖定狀態</th><th>啟用狀態</th><th>建立時間</th></tr></thead>
                                            <tbody>{devices.map((device) => (
                                                <tr key={device.serial_number}>
                                                    <td><Link className="inline-link account-code" href={`/manage/devices/${encodeURIComponent(device.serial_number)}`}>{device.serial_number}</Link></td>
                                                    <td>{device.name ?? '-'}</td>
                                                    <td>{device.is_locked ? '已上鎖' : '未上鎖'}</td>
                                                    <td>{device.is_enabled ? '已啟用' : '已停用'}</td>
                                                    <td>{formatDate(device.created_at)}</td>
                                                </tr>
                                            ))}</tbody>
                                        </table>
                                    </div>
                                )}
                            </section>
                        )}
                    </section>
                )}
            </ManageLayout>
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
        deleted: '已刪除',
    }[status] ?? status;
}
