import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';
import { errorMessage, formErrors } from '../../lib/http';

const emptyDeviceForm = { serial_number: '', secret: '', name: '', lock: false };

export default function HouseShow({ housePublicId }) {
    const [house, setHouse] = useState(null);
    const [devices, setDevices] = useState([]);
    const [invitations, setInvitations] = useState([]);
    const [joinRequests, setJoinRequests] = useState([]);
    const [inviteePublicId, setInviteePublicId] = useState('');
    const [deviceForm, setDeviceForm] = useState(emptyDeviceForm);
    const [deviceNames, setDeviceNames] = useState({});
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);

    const isOwner = house?.role === 'owner';

    useEffect(() => {
        reload();
    }, [housePublicId]);

    async function reload() {
        setLoading(true);
        const [houseResponse, deviceResponse, invitationResponse, joinRequestResponse] = await Promise.all([
            window.axios.get(`/api/houses/${housePublicId}`),
            window.axios.get(`/api/houses/${housePublicId}/devices`),
            window.axios.get('/api/house-invitations'),
            window.axios.get('/api/house-join-requests'),
        ]);

        const nextHouse = houseResponse.data.data;
        const nextDevices = deviceResponse.data.data;
        setHouse(nextHouse);
        setDevices(nextDevices);
        setDeviceNames(Object.fromEntries(nextDevices.map((device) => [device.id, device.name ?? ''])));
        setInvitations(invitationResponse.data.data.sent.filter((item) => item.house.public_id === housePublicId));
        setJoinRequests(joinRequestResponse.data.data.received.filter((item) => item.house.public_id === housePublicId));
        setLoading(false);
    }

    async function run(task, success) {
        setProcessing(true);
        setMessage('');
        setError('');
        setErrors({});

        try {
            const response = await task();
            setMessage(response?.data?.message ?? success);
            await reload();
        } catch (caught) {
            setError(errorMessage(caught));
            setErrors(formErrors(caught));
        } finally {
            setProcessing(false);
        }
    }

    async function invite(event) {
        event.preventDefault();
        await run(
            () => window.axios.post(`/api/houses/${housePublicId}/invitations`, { invitee_public_id: inviteePublicId }),
            '邀請已送出。',
        );
        setInviteePublicId('');
    }

    async function addDevice(event) {
        event.preventDefault();
        await run(
            () => window.axios.post(`/api/houses/${housePublicId}/devices`, deviceForm),
            '設備已加入。',
        );
        setDeviceForm(emptyDeviceForm);
    }

    async function leaveHouse() {
        if (!window.confirm('確定要退出房屋？')) {
            return;
        }

        await run(() => window.axios.post(`/api/houses/${housePublicId}/leave`), '已退出房屋。');
        router.visit('/houses');
    }

    async function deleteHouse() {
        if (!window.confirm('確定要刪除房屋？')) {
            return;
        }

        await run(() => window.axios.delete(`/api/houses/${housePublicId}`), '房屋已刪除。');
        router.visit('/houses');
    }

    const ownerCount = useMemo(
        () => house?.members?.filter((member) => member.role === 'owner').length ?? 0,
        [house],
    );

    return (
        <>
            <Head title={house ? house.name : '房屋詳情'} />
            <AppLayout>
                {loading && <p className="muted">載入中...</p>}
                {!loading && house && (
                    <>
                        <section className="page-header">
                            <p className="eyebrow">{isOwner ? 'Owner' : 'Resident'}</p>
                            <h1>{house.name}</h1>
                            <div className="actions-row">
                                <button type="button" className="button-ghost" onClick={leaveHouse} disabled={processing}>退出</button>
                                {isOwner && <button type="button" className="button-danger" onClick={deleteHouse} disabled={processing}>刪除</button>}
                            </div>
                        </section>

                        {message && <div className="notice success">{message}</div>}
                        {error && <div className="notice error">{error}</div>}

                        <section className="grid-2">
                            <section className="panel">
                                <div className="panel-heading">
                                    <h2>成員</h2>
                                    <span className="status-pill">{house.members.length} 人</span>
                                </div>
                                <div className="item-list">
                                    {house.members.map((member) => (
                                        <article className="list-item" key={member.public_id}>
                                            <div>
                                                <strong>{member.display_name}</strong>
                                                <span>{member.role === 'owner' ? '屋主' : '住戶'} · {member.public_id}</span>
                                            </div>
                                            {isOwner && (
                                                <div className="compact-actions">
                                                    {member.role === 'resident' && (
                                                        <button type="button" onClick={() => run(() => window.axios.patch(`/api/houses/${housePublicId}/members/${member.public_id}/role`, { role: 'owner' }), '身份已更新。')}>升為屋主</button>
                                                    )}
                                                    {member.role === 'owner' && ownerCount > 1 && (
                                                        <button type="button" className="button-ghost" onClick={() => run(() => window.axios.patch(`/api/houses/${housePublicId}/members/${member.public_id}/role`, { role: 'resident' }), '身份已更新。')}>改為住戶</button>
                                                    )}
                                                    <button type="button" className="button-danger" onClick={() => run(() => window.axios.delete(`/api/houses/${housePublicId}/members/${member.public_id}`), '成員已移除。')}>移除</button>
                                                </div>
                                            )}
                                        </article>
                                    ))}
                                </div>
                            </section>

                            <section className="panel stack">
                                <h2>邀請</h2>
                                {isOwner && (
                                    <form className="inline-form" onSubmit={invite}>
                                        <label>
                                            使用者 ID
                                            <input value={inviteePublicId} onChange={(event) => setInviteePublicId(event.target.value)} required />
                                            {errors.invitee_public_id?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                        </label>
                                        <button type="submit" disabled={processing}>送出</button>
                                    </form>
                                )}
                                <MiniList
                                    items={invitations}
                                    empty="沒有邀請"
                                    renderText={(item) => `${item.invitee.display_name} · ${item.status}`}
                                    renderActions={(item) => isOwner && item.status === 'pending' && (
                                        <button type="button" className="button-ghost" onClick={() => run(() => window.axios.post(`/api/house-invitations/${item.id}/cancel`), '邀請已取消。')}>取消</button>
                                    )}
                                />
                            </section>

                            <section className="panel">
                                <h2>加入申請</h2>
                                <MiniList
                                    items={joinRequests}
                                    empty="沒有加入申請"
                                    renderText={(item) => `${item.requester.display_name} · ${item.status}`}
                                    renderActions={(item) => isOwner && item.status === 'pending' && (
                                        <>
                                            <button type="button" onClick={() => run(() => window.axios.post(`/api/house-join-requests/${item.id}/accept`), '申請已接受。')}>接受</button>
                                            <button type="button" className="button-ghost" onClick={() => run(() => window.axios.post(`/api/house-join-requests/${item.id}/ignore`), '申請已忽略。')}>忽略</button>
                                        </>
                                    )}
                                />
                            </section>

                            <section className="panel stack">
                                <h2>加入設備</h2>
                                {isOwner ? (
                                    <form className="stack" onSubmit={addDevice}>
                                        <label>
                                            序號
                                            <input value={deviceForm.serial_number} onChange={(event) => setDeviceForm({ ...deviceForm, serial_number: event.target.value })} required />
                                            {errors.serial_number?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                        </label>
                                        <label>
                                            隱碼
                                            <input type="password" value={deviceForm.secret} onChange={(event) => setDeviceForm({ ...deviceForm, secret: event.target.value })} required />
                                            {errors.secret?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                        </label>
                                        <label>
                                            名稱
                                            <input value={deviceForm.name} onChange={(event) => setDeviceForm({ ...deviceForm, name: event.target.value })} maxLength={100} />
                                            {errors.name?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                        </label>
                                        <label className="check-row">
                                            <input type="checkbox" checked={deviceForm.lock} onChange={(event) => setDeviceForm({ ...deviceForm, lock: event.target.checked })} />
                                            加入後上鎖
                                        </label>
                                        <button type="submit" disabled={processing}>加入設備</button>
                                    </form>
                                ) : <p className="muted">僅屋主可加入設備</p>}
                            </section>
                        </section>

                        <section className="panel">
                            <div className="panel-heading">
                                <h2>設備</h2>
                                <span className="status-pill">{devices.length} 部</span>
                            </div>
                            {devices.length === 0 && <p className="muted">沒有設備</p>}
                            <div className="device-grid">
                                {devices.map((device) => (
                                    <article className="device-card" key={device.id}>
                                        <div>
                                            <strong>{device.name || device.serial_number}</strong>
                                            <span>{device.serial_number} · {device.is_locked ? '已上鎖' : '未上鎖'}</span>
                                        </div>
                                        {isOwner && (
                                            <>
                                                <div className="inline-form">
                                                    <input value={deviceNames[device.id] ?? ''} onChange={(event) => setDeviceNames({ ...deviceNames, [device.id]: event.target.value })} />
                                                    <button type="button" onClick={() => run(() => window.axios.patch(`/api/houses/${housePublicId}/devices/${device.id}`, { name: deviceNames[device.id] ?? '' }), '設備已更新。')}>命名</button>
                                                </div>
                                                <div className="compact-actions">
                                                    {device.is_locked ? (
                                                        <button type="button" className="button-ghost" onClick={() => run(() => window.axios.post(`/api/houses/${housePublicId}/devices/${device.id}/unlock`), '設備已解鎖。')}>解鎖</button>
                                                    ) : (
                                                        <button type="button" onClick={() => run(() => window.axios.post(`/api/houses/${housePublicId}/devices/${device.id}/lock`), '設備已上鎖。')}>上鎖</button>
                                                    )}
                                                    <button type="button" className="button-danger" disabled={device.is_locked} onClick={() => run(() => window.axios.delete(`/api/houses/${housePublicId}/devices/${device.id}`), '設備已移除。')}>移除</button>
                                                </div>
                                            </>
                                        )}
                                    </article>
                                ))}
                            </div>
                        </section>
                    </>
                )}
            </AppLayout>
        </>
    );
}

function MiniList({ items, empty, renderText, renderActions }) {
    if (items.length === 0) {
        return <p className="muted">{empty}</p>;
    }

    return (
        <div className="item-list">
            {items.map((item) => (
                <article className="list-item" key={item.id}>
                    <span>{renderText(item)}</span>
                    <div className="compact-actions">{renderActions?.(item)}</div>
                </article>
            ))}
        </div>
    );
}
