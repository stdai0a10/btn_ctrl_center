import { Head, router } from '@inertiajs/react';
import { EllipsisVertical, Info, Lock, LockOpen, Pencil, Power, Trash2 } from 'lucide-react';
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
    const [accessJoinRequest, setAccessJoinRequest] = useState(null);
    const [inviteePublicId, setInviteePublicId] = useState('');
    const [deviceForm, setDeviceForm] = useState(emptyDeviceForm);
    const [deviceNames, setDeviceNames] = useState({});
    const [editingDeviceId, setEditingDeviceId] = useState(null);
    const [renameErrors, setRenameErrors] = useState({});
    const [openDeviceMenu, setOpenDeviceMenu] = useState(null);
    const [addDeviceDialogOpen, setAddDeviceDialogOpen] = useState(false);
    const [addDeviceResult, setAddDeviceResult] = useState(null);
    const [memberPendingRemoval, setMemberPendingRemoval] = useState(null);
    const [removeMemberError, setRemoveMemberError] = useState('');
    const [devicePendingRemoval, setDevicePendingRemoval] = useState(null);
    const [removeDeviceError, setRemoveDeviceError] = useState('');
    const [deviceInfo, setDeviceInfo] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [errors, setErrors] = useState({});
    const [activeMemberTab, setActiveMemberTab] = useState('members');
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
        const roomResponse = await window.axios.get(`/api/rooms/${roomPublicId}`);
        const nextRoom = roomResponse.data.data;

        setRoom(nextRoom);
        setAccessJoinRequest(nextRoom.join_request ?? null);

        if (!nextRoom.can_access) {
            setDevices([]);
            setInvitations([]);
            setJoinRequests([]);
            setLoading(false);
            return;
        }

        const [deviceResponse, invitationResponse, joinRequestResponse] = await Promise.all([
            window.axios.get(`/api/rooms/${roomPublicId}/devices`),
            window.axios.get('/api/room-invitations'),
            window.axios.get('/api/room-join-requests'),
        ]);

        const nextDevices = deviceResponse.data.data;
        setDevices(nextDevices);
        setDeviceNames(Object.fromEntries(nextDevices.map((device) => [device.id, device.name ?? ''])));
        setInvitations(invitationResponse.data.data.sent.filter((item) => item.room.public_id === roomPublicId));
        setJoinRequests(joinRequestResponse.data.data.received.filter((item) => item.room.public_id === roomPublicId));
        setLoading(false);
    }

    async function requestRoomAccess() {
        await run(
            () => window.axios.post(`/api/rooms/${roomPublicId}/join-requests`),
            '加入申請已送出。',
        );
    }

    async function cancelRoomAccessRequest() {
        if (!accessJoinRequest) return;

        await run(
            () => window.axios.post(`/api/room-join-requests/${accessJoinRequest.id}/cancel`),
            '加入申請已取消。',
        );
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

    async function updateJoinRequestStatus(joinRequest, operation, status, success) {
        setProcessing(true);
        setMessage('');
        setError('');
        setErrors({});

        try {
            const response = await window.axios.post(`/api/room-join-requests/${joinRequest.id}/${operation}`);
            setJoinRequests((current) => current.map((item) => (
                item.id === joinRequest.id
                    ? {
                        ...item,
                        status,
                        ignored_at: status === 'ignored' ? new Date().toISOString() : null,
                    }
                    : item
            )));
            setMessage(response.data.message ?? success);
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
        const estimatedHeight = isOwner ? 236 : 54;
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

        if (action === 'info') {
            setDeviceInfo(device);
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

    async function removeMember() {
        if (!memberPendingRemoval) return;

        setProcessing(true);
        setMessage('');
        setError('');
        setRemoveMemberError('');

        try {
            const response = await window.axios.delete(
                `/api/rooms/${roomPublicId}/members/${memberPendingRemoval.public_id}`,
            );
            setRoom((current) => ({
                ...current,
                members: current.members.filter((member) => member.public_id !== memberPendingRemoval.public_id),
                members_count: Math.max(0, (current.members_count ?? current.members.length) - 1),
            }));
            setMessage(response.data.message ?? '成員已移除。');
            setMemberPendingRemoval(null);
        } catch (caught) {
            setRemoveMemberError(errorMessage(caught));
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
                            <p className="eyebrow">{room.can_access ? (isOwner ? 'Owner' : 'Resident') : 'Restricted'}</p>
                            <h1>{room.name}</h1>
                            {room.can_access && (
                                <div className="actions-row">
                                    <button type="button" className="button-ghost" onClick={leaveRoom} disabled={processing}>退出</button>
                                    {isOwner && <button type="button" className="button-danger" onClick={deleteRoom} disabled={processing}>刪除</button>}
                                </div>
                            )}
                        </section>

                        {message && <div className="notice success">{message}</div>}
                        {error && <div className="notice error">{error}</div>}

                        {!room.can_access ? (
                            <section className="panel room-access-panel">
                                <p className="eyebrow">Access Required</p>
                                <h2>你目前無法使用這個房間</h2>
                                <p className="lede">你不是這個房間的成員，因此無法查看成員、邀請、申請與設備內容。你可以送出加入申請，等待房主審核。</p>

                                {accessJoinRequest?.status === 'pending' ? (
                                    <div className="room-access-status">
                                        <div>
                                            <strong>加入申請審核中</strong>
                                            <span>房主接受後，你就能查看並使用這個房間。</span>
                                        </div>
                                        <button
                                            type="button"
                                            className="button-ghost"
                                            onClick={cancelRoomAccessRequest}
                                            disabled={processing}
                                        >
                                            {processing ? '處理中...' : '取消申請'}
                                        </button>
                                    </div>
                                ) : (
                                    <div className="room-access-actions">
                                        {accessJoinRequest?.status === 'cancelled' && (
                                            <p className="muted">先前的申請已取消，你可以再次提出申請。</p>
                                        )}
                                        <button type="button" onClick={requestRoomAccess} disabled={processing}>
                                            {processing ? '送出中...' : '申請加入房間'}
                                        </button>
                                    </div>
                                )}
                            </section>
                        ) : (
                            <>
                        <section className="panel member-tabs-panel">
                            <div className="panel-heading">
                                <h2>房間成員</h2>
                                <span className="status-pill">{room.members.length} 人</span>
                            </div>

                            <div className="section-tabs" role="tablist" aria-label="房間成員管理">
                                <button
                                    type="button"
                                    className={`section-tab ${activeMemberTab === 'members' ? 'is-active' : ''}`}
                                    role="tab"
                                    aria-selected={activeMemberTab === 'members'}
                                    aria-controls="room-members-panel"
                                    onClick={() => setActiveMemberTab('members')}
                                >
                                    成員
                                    <span>{room.members.length}</span>
                                </button>
                                <button
                                    type="button"
                                    className={`section-tab ${activeMemberTab === 'requests' ? 'is-active' : ''}`}
                                    role="tab"
                                    aria-selected={activeMemberTab === 'requests'}
                                    aria-controls="room-requests-panel"
                                    onClick={() => setActiveMemberTab('requests')}
                                >
                                    申請
                                    <span>{joinRequests.length}</span>
                                </button>
                                <button
                                    type="button"
                                    className={`section-tab ${activeMemberTab === 'invitations' ? 'is-active' : ''}`}
                                    role="tab"
                                    aria-selected={activeMemberTab === 'invitations'}
                                    aria-controls="room-invitations-panel"
                                    onClick={() => setActiveMemberTab('invitations')}
                                >
                                    邀請
                                    <span>{invitations.length}</span>
                                </button>
                            </div>

                            {activeMemberTab === 'members' && (
                                <div id="room-members-panel" className="section-tab-panel" role="tabpanel">
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
                                                        <button
                                                            type="button"
                                                            className="button-danger"
                                                            onClick={() => {
                                                                setRemoveMemberError('');
                                                                setMemberPendingRemoval(member);
                                                            }}
                                                        >
                                                            移除
                                                        </button>
                                                    </div>
                                                )}
                                            </article>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {activeMemberTab === 'requests' && (
                                <div id="room-requests-panel" className="section-tab-panel" role="tabpanel">
                                    <MiniList
                                        items={joinRequests}
                                        empty="沒有加入申請"
                                        renderText={(item) => `${item.requester.display_name} · ${item.status}`}
                                        renderActions={(item) => isOwner && item.status === 'pending' && (
                                            <>
                                                <button type="button" onClick={() => run(() => window.axios.post(`/api/room-join-requests/${item.id}/accept`), '申請已接受。')}>接受</button>
                                                <button
                                                    type="button"
                                                    className="button-ghost"
                                                    onClick={() => updateJoinRequestStatus(item, 'ignore', 'ignored', '申請已忽略。')}
                                                >
                                                    忽略
                                                </button>
                                            </>
                                        )}
                                        renderSecondaryActions={(item) => isOwner && item.status === 'ignored' && (
                                            <button
                                                type="button"
                                                className="button-ghost"
                                                onClick={() => updateJoinRequestStatus(
                                                    item,
                                                    'restore',
                                                    'pending',
                                                    '申請已恢復為待決定。',
                                                )}
                                            >
                                                恢復待決定
                                            </button>
                                        )}
                                    />
                                </div>
                            )}

                            {activeMemberTab === 'invitations' && (
                                <div id="room-invitations-panel" className="section-tab-panel" role="tabpanel">
                                    {isOwner && (
                                        <form className="inline-form member-invite-form" onSubmit={invite}>
                                            <label>
                                                使用者 ID
                                                <input value={inviteePublicId} onChange={(event) => setInviteePublicId(event.target.value)} required />
                                                {errors.invitee_public_id?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                            </label>
                                            <button type="submit" disabled={processing}>送出邀請</button>
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
                                </div>
                            )}
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
                                                </div>
                                            )}
                                            <span>{device.serial_number} · {device.is_locked ? '已上鎖' : '未上鎖'} · {device.is_enabled ? '已啟用' : '已停用'}</span>
                                            <span>{device.product ? `${device.product.model_number} · ${device.product.name}` : '未指定產品'}</span>
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
                                    <button type="button" role="menuitem" onClick={() => selectDeviceAction(openDeviceMenu.device, 'info')}>
                                        <Info size={17} />
                                        設備資訊
                                    </button>
                                    {isOwner && (
                                        <>
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
                                        </>
                                    )}
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
                                            type="text"
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

                        {deviceInfo && (
                            <Dialog title="設備資訊" onClose={() => setDeviceInfo(null)}>
                                <dl className="detail-list">
                                    <Detail label="設備名稱" value={deviceInfo.name || '-'} />
                                    <Detail label="設備序號" value={deviceInfo.serial_number} />
                                    <Detail label="產品" value={deviceInfo.product ? `${deviceInfo.product.model_number} · ${deviceInfo.product.name}` : '未指定產品'} />
                                    <Detail label="鎖定狀態" value={deviceInfo.is_locked ? '已上鎖' : '未上鎖'} />
                                    <Detail label="啟用狀態" value={deviceInfo.is_enabled ? '已啟用' : '已停用'} />
                                    <Detail label="加入時間" value={formatDate(deviceInfo.created_at)} />
                                    <Detail label="最近更新" value={formatDate(deviceInfo.updated_at)} />
                                </dl>
                                <div className="dialog-actions">
                                    <button type="button" onClick={() => setDeviceInfo(null)}>關閉</button>
                                </div>
                            </Dialog>
                        )}

                        {memberPendingRemoval && (
                            <Dialog
                                title="確認移除成員"
                                onClose={() => setMemberPendingRemoval(null)}
                                closeDisabled={processing}
                            >
                                <p className="dialog-description">
                                    確定要將「{memberPendingRemoval.display_name}」從房間移除嗎？
                                </p>
                                <p className="muted">移除後，該使用者將無法查看或操作這個房間的設備。</p>
                                {removeMemberError && <div className="notice error">{removeMemberError}</div>}
                                <div className="dialog-actions">
                                    <button
                                        type="button"
                                        className="button-ghost"
                                        onClick={() => setMemberPendingRemoval(null)}
                                        disabled={processing}
                                    >
                                        取消
                                    </button>
                                    <button type="button" className="button-danger" onClick={removeMember} disabled={processing}>
                                        {processing ? '移除中...' : '確認移除'}
                                    </button>
                                </div>
                            </Dialog>
                        )}
                            </>
                        )}
                    </>
                )}
            </AppLayout>
        </>
    );
}

function Detail({ label, value }) {
    return <div><dt>{label}</dt><dd>{value}</dd></div>;
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('zh-TW', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
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

function MiniList({ items, empty, renderText, renderActions, renderSecondaryActions }) {
    if (items.length === 0) {
        return <p className="muted">{empty}</p>;
    }

    return (
        <div className="item-list">
            {items.map((item) => (
                <article className="list-item" key={item.id}>
                    <span>{renderText(item)}</span>
                    <div className="compact-actions">
                        {renderActions?.(item)}
                        {renderSecondaryActions?.(item)}
                    </div>
                </article>
            ))}
        </div>
    );
}
