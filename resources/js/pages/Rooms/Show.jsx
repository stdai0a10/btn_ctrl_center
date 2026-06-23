import { Head, router } from '@inertiajs/react';
import { EllipsisVertical, Lock, LockOpen, Pencil, Power, Trash2 } from 'lucide-react';
import { createPortal } from 'react-dom';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';
import { errorMessage, formErrors } from '../../lib/http';

const emptyDeviceForm = { serial_number: '', secret: '', name: '', lock: false };

export default function RoomShow({ roomPublicId }) {
    const [room, setRoom] = useState(null);
    const [devices, setDevices] = useState([]);
    const [invitations, setInvitations] = useState([]);
    const [joinRequests, setJoinRequests] = useState([]);
    const [inviteePublicId, setInviteePublicId] = useState('');
    const [deviceForm, setDeviceForm] = useState(emptyDeviceForm);
    const [deviceNames, setDeviceNames] = useState({});
    const [editingDeviceId, setEditingDeviceId] = useState(null);
    const [renameErrors, setRenameErrors] = useState({});
    const [openDeviceMenu, setOpenDeviceMenu] = useState(null);
    const [addDeviceDialogOpen, setAddDeviceDialogOpen] = useState(false);
    const [addDeviceResult, setAddDeviceResult] = useState(null);
    const [devicePendingRemoval, setDevicePendingRemoval] = useState(null);
    const [removeDeviceError, setRemoveDeviceError] = useState('');
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const deviceMenuRef = useRef(null);

    const isOwner = room?.role === 'owner';

    useEffect(() => {
        reload();
    }, [roomPublicId]);

    useEffect(() => {
        if (openDeviceMenu === null) return undefined;

        function closeMenu(event) {
            if (deviceMenuRef.current?.contains(event.target)) return;
            if (event.target.closest?.(`[data-device-action-trigger="${openDeviceMenu.device.id}"]`)) return;
            setOpenDeviceMenu(null);
        }

        function closeOnViewportChange() {
            setOpenDeviceMenu(null);
        }

        function closeOnEscape(event) {
            if (event.key === 'Escape') setOpenDeviceMenu(null);
        }

        document.addEventListener('pointerdown', closeMenu);
        document.addEventListener('keydown', closeOnEscape);
        window.addEventListener('resize', closeOnViewportChange);
        window.addEventListener('scroll', closeOnViewportChange, true);

        return () => {
            document.removeEventListener('pointerdown', closeMenu);
            document.removeEventListener('keydown', closeOnEscape);
            window.removeEventListener('resize', closeOnViewportChange);
            window.removeEventListener('scroll', closeOnViewportChange, true);
        };
    }, [openDeviceMenu]);

    async function reload() {
        setLoading(true);
        const [roomResponse, deviceResponse, invitationResponse, joinRequestResponse] = await Promise.all([
            window.axios.get(`/api/rooms/${roomPublicId}`),
            window.axios.get(`/api/rooms/${roomPublicId}/devices`),
            window.axios.get('/api/room-invitations'),
            window.axios.get('/api/room-join-requests'),
        ]);

        const nextRoom = roomResponse.data.data;
        const nextDevices = deviceResponse.data.data;
        setRoom(nextRoom);
        setDevices(nextDevices);
        setDeviceNames(Object.fromEntries(nextDevices.map((device) => [device.id, device.name ?? ''])));
        setInvitations(invitationResponse.data.data.sent.filter((item) => item.room.public_id === roomPublicId));
        setJoinRequests(joinRequestResponse.data.data.received.filter((item) => item.room.public_id === roomPublicId));
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
            () => window.axios.post(`/api/rooms/${roomPublicId}/invitations`, { invitee_public_id: inviteePublicId }),
            '邀請已送出。',
        );
        setInviteePublicId('');
    }

    async function addDevice(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await window.axios.post(`/api/rooms/${roomPublicId}/devices`, deviceForm);
            const addedDevice = response.data.data;
            setDevices((current) => [addedDevice, ...current]);
            setDeviceNames((current) => ({ ...current, [addedDevice.id]: addedDevice.name ?? '' }));
            setDeviceForm(emptyDeviceForm);
            setAddDeviceDialogOpen(false);
            setAddDeviceResult({
                type: 'success',
                title: '設備加入成功',
                message: response.data.message ?? '設備已加入房間。',
            });
        } catch (caught) {
            setErrors(formErrors(caught));
            setAddDeviceDialogOpen(false);
            setAddDeviceResult({
                type: 'error',
                title: '設備加入失敗',
                message: errorMessage(caught),
            });
        } finally {
            setProcessing(false);
        }
    }

    function startEditingDeviceName(device) {
        setDeviceNames((current) => ({ ...current, [device.id]: device.name ?? '' }));
        setEditingDeviceId(device.id);
        setRenameErrors({});
        setMessage('');
        setError('');
    }

    function toggleDeviceMenu(event, device) {
        const rect = event.currentTarget.getBoundingClientRect();
        const menuWidth = 184;
        const estimatedHeight = 188;
        const gap = 6;
        const left = Math.min(
            window.innerWidth - menuWidth - 12,
            Math.max(12, rect.right - menuWidth),
        );
        const openAbove = rect.bottom + gap + estimatedHeight > window.innerHeight;

        setOpenDeviceMenu((current) => current?.device.id === device.id
            ? null
            : {
                device,
                position: {
                    left,
                    top: openAbove ? rect.top - gap : rect.bottom + gap,
                    placement: openAbove ? 'top' : 'bottom',
                },
            });
    }

    function updateDeviceState(updatedDevice) {
        setDevices((current) => current.map((device) => (
            device.id === updatedDevice.id ? { ...device, ...updatedDevice } : device
        )));
        setDeviceNames((current) => ({
            ...current,
            [updatedDevice.id]: updatedDevice.name ?? '',
        }));
    }

    async function runDeviceUpdate(task, success) {
        setProcessing(true);
        setMessage('');
        setError('');
        setErrors({});

        try {
            const response = await task();
            updateDeviceState(response.data.data);
            setMessage(response.data.message ?? success);
        } catch (caught) {
            setError(errorMessage(caught));
            setErrors(formErrors(caught));
        } finally {
            setProcessing(false);
        }
    }

    async function selectDeviceAction(device, action) {
        setOpenDeviceMenu(null);

        if (action === 'rename') {
            startEditingDeviceName(device);
            return;
        }

        if (action === 'remove') {
            setRemoveDeviceError('');
            setDevicePendingRemoval(device);
            return;
        }

        if (action === 'toggle-enabled') {
            const operation = device.is_enabled ? 'disable' : 'enable';
            await runDeviceUpdate(
                () => window.axios.post(`/api/rooms/${roomPublicId}/devices/${device.id}/${operation}`),
                device.is_enabled ? '設備已停用。' : '設備已啟用。',
            );
            return;
        }

        const operation = device.is_locked ? 'unlock' : 'lock';
        await runDeviceUpdate(
            () => window.axios.post(`/api/rooms/${roomPublicId}/devices/${device.id}/${operation}`),
            device.is_locked ? '設備已解鎖。' : '設備已上鎖。',
        );
    }

    async function removeDevice() {
        if (!devicePendingRemoval) return;

        setProcessing(true);
        setMessage('');
        setError('');
        setRemoveDeviceError('');

        try {
            const response = await window.axios.delete(
                `/api/rooms/${roomPublicId}/devices/${devicePendingRemoval.id}`,
            );
            setDevices((current) => current.filter((device) => device.id !== devicePendingRemoval.id));
            setDeviceNames((current) => {
                const next = { ...current };
                delete next[devicePendingRemoval.id];
                return next;
            });
            setMessage(response.data.message ?? '設備已移除。');
            setDevicePendingRemoval(null);
        } catch (caught) {
            setRemoveDeviceError(errorMessage(caught));
        } finally {
            setProcessing(false);
        }
    }

    function cancelEditingDeviceName(device) {
        setDeviceNames((current) => ({ ...current, [device.id]: device.name ?? '' }));
        setEditingDeviceId(null);
        setRenameErrors({});
    }

    async function submitDeviceName(event, device) {
        event.preventDefault();
        setProcessing(true);
        setMessage('');
        setError('');
        setRenameErrors({});

        try {
            const response = await window.axios.patch(`/api/rooms/${roomPublicId}/devices/${device.id}`, {
                name: deviceNames[device.id] ?? '',
            });
            updateDeviceState(response.data.data);
            setMessage(response.data.message ?? '設備已更新。');
            setEditingDeviceId(null);
        } catch (caught) {
            setError(errorMessage(caught));
            setRenameErrors(formErrors(caught));
        } finally {
            setProcessing(false);
        }
    }

    async function leaveRoom() {
        if (!window.confirm('確定要退出房間？')) {
            return;
        }

        await run(() => window.axios.post(`/api/rooms/${roomPublicId}/leave`), '已退出房間。');
        router.visit('/rooms');
    }

    async function deleteRoom() {
        if (!window.confirm('確定要刪除房間？')) {
            return;
        }

        await run(() => window.axios.delete(`/api/rooms/${roomPublicId}`), '房間已刪除。');
        router.visit('/rooms');
    }

    const ownerCount = useMemo(
        () => room?.members?.filter((member) => member.role === 'owner').length ?? 0,
        [room],
    );

    return (
        <>
            <Head title={room ? room.name : '房間詳情'} />
            <AppLayout>
                {loading && <p className="muted">載入中...</p>}
                {!loading && room && (
                    <>
                        <section className="page-header">
                            <p className="eyebrow">{isOwner ? 'Owner' : 'Resident'}</p>
                            <h1>{room.name}</h1>
                            <div className="actions-row">
                                <button type="button" className="button-ghost" onClick={leaveRoom} disabled={processing}>退出</button>
                                {isOwner && <button type="button" className="button-danger" onClick={deleteRoom} disabled={processing}>刪除</button>}
                            </div>
                        </section>

                        {message && <div className="notice success">{message}</div>}
                        {error && <div className="notice error">{error}</div>}

                        <section className="grid-2">
                            <section className="panel">
                                <div className="panel-heading">
                                    <h2>成員</h2>
                                    <span className="status-pill">{room.members.length} 人</span>
                                </div>
                                <div className="item-list">
                                    {room.members.map((member) => (
                                        <article className="list-item" key={member.public_id}>
                                            <div>
                                                <strong>{member.display_name}</strong>
                                                <span>{member.role === 'owner' ? '房主' : '住戶'} · {member.public_id}</span>
                                            </div>
                                            {isOwner && (
                                                <div className="compact-actions">
                                                    {member.role === 'resident' && (
                                                        <button type="button" onClick={() => run(() => window.axios.patch(`/api/rooms/${roomPublicId}/members/${member.public_id}/role`, { role: 'owner' }), '身份已更新。')}>升為房主</button>
                                                    )}
                                                    {member.role === 'owner' && ownerCount > 1 && (
                                                        <button type="button" className="button-ghost" onClick={() => run(() => window.axios.patch(`/api/rooms/${roomPublicId}/members/${member.public_id}/role`, { role: 'resident' }), '身份已更新。')}>改為住戶</button>
                                                    )}
                                                    <button type="button" className="button-danger" onClick={() => run(() => window.axios.delete(`/api/rooms/${roomPublicId}/members/${member.public_id}`), '成員已移除。')}>移除</button>
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
                                        <button type="button" className="button-ghost" onClick={() => run(() => window.axios.post(`/api/room-invitations/${item.id}/cancel`), '邀請已取消。')}>取消</button>
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
                                            <button type="button" onClick={() => run(() => window.axios.post(`/api/room-join-requests/${item.id}/accept`), '申請已接受。')}>接受</button>
                                            <button type="button" className="button-ghost" onClick={() => run(() => window.axios.post(`/api/room-join-requests/${item.id}/ignore`), '申請已忽略。')}>忽略</button>
                                        </>
                                    )}
                                />
                            </section>

                            <section className="panel stack">
                                <h2>加入設備</h2>
                                {isOwner ? (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setErrors({});
                                            setAddDeviceDialogOpen(true);
                                        }}
                                        disabled={processing}
                                    >
                                        加入設備
                                    </button>
                                ) : <p className="muted">僅房主可加入設備</p>}
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
                                            {isOwner && editingDeviceId === device.id ? (
                                                <form className="profile-inline-edit" onSubmit={(event) => submitDeviceName(event, device)}>
                                                    <input
                                                        value={deviceNames[device.id] ?? ''}
                                                        onChange={(event) => setDeviceNames({ ...deviceNames, [device.id]: event.target.value })}
                                                        maxLength={100}
                                                        autoFocus
                                                    />
                                                    <div className="compact-actions">
                                                        <button type="submit" disabled={processing}>{processing ? '套用中...' : '套用'}</button>
                                                        <button type="button" className="button-ghost" onClick={() => cancelEditingDeviceName(device)} disabled={processing}>取消</button>
                                                    </div>
                                                    {renameErrors.name?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                                </form>
                                            ) : (
                                                <div className="profile-display-row">
                                                    <strong>{device.name || device.serial_number}</strong>
                                                    {isOwner && (
                                                        <button
                                                            type="button"
                                                            className="table-action-trigger device-action-trigger"
                                                            data-device-action-trigger={device.id}
                                                            aria-label={`${device.name || device.serial_number} 操作`}
                                                            aria-expanded={openDeviceMenu?.device.id === device.id}
                                                            aria-haspopup="menu"
                                                            title="操作"
                                                            disabled={processing}
                                                            onClick={(event) => toggleDeviceMenu(event, device)}
                                                        >
                                                            <EllipsisVertical size={19} strokeWidth={2.4} />
                                                        </button>
                                                    )}
                                                </div>
                                            )}
                                            <span>{device.serial_number} · {device.is_locked ? '已上鎖' : '未上鎖'} · {device.is_enabled ? '已啟用' : '已停用'}</span>
                                        </div>
                                    </article>
                                ))}
                            </div>
                            {openDeviceMenu && createPortal(
                                <div
                                    ref={deviceMenuRef}
                                    className={`table-action-menu-list is-${openDeviceMenu.position.placement}`}
                                    role="menu"
                                    style={{
                                        left: openDeviceMenu.position.left,
                                        top: openDeviceMenu.position.top,
                                    }}
                                >
                                    <button type="button" role="menuitem" onClick={() => selectDeviceAction(openDeviceMenu.device, 'rename')}>
                                        <Pencil size={17} />
                                        改名
                                    </button>
                                    <button type="button" role="menuitem" onClick={() => selectDeviceAction(openDeviceMenu.device, 'toggle-enabled')}>
                                        <Power size={17} />
                                        {openDeviceMenu.device.is_enabled ? '停用' : '啟用'}
                                    </button>
                                    <button type="button" role="menuitem" onClick={() => selectDeviceAction(openDeviceMenu.device, 'toggle-locked')}>
                                        {openDeviceMenu.device.is_locked ? <LockOpen size={17} /> : <Lock size={17} />}
                                        {openDeviceMenu.device.is_locked ? '解鎖' : '上鎖'}
                                    </button>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        className="is-danger"
                                        disabled={openDeviceMenu.device.is_locked}
                                        onClick={() => selectDeviceAction(openDeviceMenu.device, 'remove')}
                                    >
                                        <Trash2 size={17} />
                                        移除
                                    </button>
                                </div>,
                                document.body,
                            )}
                        </section>

                        {addDeviceDialogOpen && (
                            <Dialog
                                title="加入設備"
                                onClose={() => setAddDeviceDialogOpen(false)}
                                closeDisabled={processing}
                            >
                                <form className="stack dialog-form" onSubmit={addDevice}>
                                    <label>
                                        序號
                                        <input
                                            value={deviceForm.serial_number}
                                            onChange={(event) => setDeviceForm({ ...deviceForm, serial_number: event.target.value })}
                                            autoFocus
                                            required
                                        />
                                        {errors.serial_number?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                    </label>
                                    <label>
                                        隱碼
                                        <input
                                            type="password"
                                            value={deviceForm.secret}
                                            onChange={(event) => setDeviceForm({ ...deviceForm, secret: event.target.value })}
                                            required
                                        />
                                        {errors.secret?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                    </label>
                                    <label>
                                        名稱
                                        <input
                                            value={deviceForm.name}
                                            onChange={(event) => setDeviceForm({ ...deviceForm, name: event.target.value })}
                                            maxLength={100}
                                        />
                                        {errors.name?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                    </label>
                                    <label className="check-row">
                                        <input
                                            type="checkbox"
                                            checked={deviceForm.lock}
                                            onChange={(event) => setDeviceForm({ ...deviceForm, lock: event.target.checked })}
                                        />
                                        加入後上鎖
                                    </label>
                                    <div className="dialog-actions">
                                        <button type="button" className="button-ghost" onClick={() => setAddDeviceDialogOpen(false)} disabled={processing}>取消</button>
                                        <button type="submit" disabled={processing}>{processing ? '確認中...' : '確認加入'}</button>
                                    </div>
                                </form>
                            </Dialog>
                        )}

                        {addDeviceResult && (
                            <Dialog title={addDeviceResult.title} onClose={() => setAddDeviceResult(null)}>
                                <div className={`notice ${addDeviceResult.type}`}>{addDeviceResult.message}</div>
                                <div className="dialog-actions">
                                    {addDeviceResult.type === 'error' && (
                                        <button
                                            type="button"
                                            className="button-ghost"
                                            onClick={() => {
                                                setAddDeviceResult(null);
                                                setAddDeviceDialogOpen(true);
                                            }}
                                        >
                                            返回修改
                                        </button>
                                    )}
                                    <button type="button" onClick={() => setAddDeviceResult(null)}>關閉</button>
                                </div>
                            </Dialog>
                        )}

                        {devicePendingRemoval && (
                            <Dialog
                                title="確認移除設備"
                                onClose={() => setDevicePendingRemoval(null)}
                                closeDisabled={processing}
                            >
                                <p className="dialog-description">
                                    確定要從房間移除「{devicePendingRemoval.name || devicePendingRemoval.serial_number}」嗎？
                                </p>
                                {removeDeviceError && <div className="notice error">{removeDeviceError}</div>}
                                <div className="dialog-actions">
                                    <button type="button" className="button-ghost" onClick={() => setDevicePendingRemoval(null)} disabled={processing}>取消</button>
                                    <button type="button" className="button-danger" onClick={removeDevice} disabled={processing}>
                                        {processing ? '移除中...' : '確認移除'}
                                    </button>
                                </div>
                            </Dialog>
                        )}
                    </>
                )}
            </AppLayout>
        </>
    );
}

function Dialog({ title, children, onClose, closeDisabled = false }) {
    useEffect(() => {
        function closeOnEscape(event) {
            if (event.key === 'Escape' && !closeDisabled) onClose();
        }

        document.addEventListener('keydown', closeOnEscape);
        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [closeDisabled, onClose]);

    return createPortal(
        <div
            className="app-dialog-backdrop"
            role="presentation"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget && !closeDisabled) onClose();
            }}
        >
            <section className="app-dialog" role="dialog" aria-modal="true" aria-labelledby="app-dialog-title">
                <div className="app-dialog-heading">
                    <h2 id="app-dialog-title">{title}</h2>
                    <button type="button" className="app-dialog-close" aria-label="關閉" onClick={onClose} disabled={closeDisabled}>×</button>
                </div>
                {children}
            </section>
        </div>,
        document.body,
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
