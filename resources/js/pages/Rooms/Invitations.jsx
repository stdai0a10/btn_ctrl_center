import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';
import { errorMessage } from '../../lib/http';

export default function Invitations() {
    const [invitations, setInvitations] = useState({ received: [], sent: [] });
    const [joinRequests, setJoinRequests] = useState({ received: [], sent: [] });
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        reload();
    }, []);

    async function reload() {
        setLoading(true);
        const [invitationResponse, joinRequestResponse] = await Promise.all([
            window.axios.get('/api/room-invitations'),
            window.axios.get('/api/room-join-requests'),
        ]);
        setInvitations(invitationResponse.data.data);
        setJoinRequests(joinRequestResponse.data.data);
        setLoading(false);
    }

    async function action(url, success) {
        setMessage('');
        setError('');

        try {
            const response = await window.axios.post(url);
            setMessage(response.data.message ?? success);
            await reload();
        } catch (caught) {
            setError(errorMessage(caught));
        }
    }

    return (
        <>
            <Head title="我的邀請" />
            <AppLayout>
                <section className="page-header">
                    <p className="eyebrow">Invitations</p>
                    <h1>我的邀請</h1>
                    <Link className="text-link" href="/rooms">返回設備管理</Link>
                </section>

                {message && <div className="notice success">{message}</div>}
                {error && <div className="notice error">{error}</div>}
                {loading && <p className="muted">載入中...</p>}

                <section className="grid-2">
                    <RequestPanel
                        title="收到邀請"
                        items={invitations.received}
                        empty="沒有待處理邀請"
                        renderActions={(item) => item.status === 'pending' && (
                            <>
                                <button type="button" onClick={() => action(`/api/room-invitations/${item.id}/accept`, '邀請已接受。')}>接受</button>
                                <button type="button" className="button-ghost" onClick={() => action(`/api/room-invitations/${item.id}/ignore`, '邀請已忽略。')}>忽略</button>
                            </>
                        )}
                    />
                    <RequestPanel
                        title="我的申請"
                        items={joinRequests.sent}
                        empty="沒有加入申請"
                        renderActions={(item) => item.status === 'pending' && (
                            <button type="button" className="button-ghost" onClick={() => action(`/api/room-join-requests/${item.id}/cancel`, '申請已取消。')}>取消</button>
                        )}
                    />
                    <RequestPanel title="已送出邀請" items={invitations.sent} empty="沒有送出邀請" />
                    <RequestPanel title="收到申請" items={joinRequests.received} empty="沒有收到申請" />
                </section>
            </AppLayout>
        </>
    );
}

function RequestPanel({ title, items, empty, renderActions }) {
    return (
        <section className="panel">
            <h2>{title}</h2>
            {items.length === 0 && <p className="muted">{empty}</p>}
            <div className="item-list">
                {items.map((item) => (
                    <article className="list-item" key={`${title}-${item.id}`}>
                        <div>
                            <strong>{item.room.name}</strong>
                            <span>{item.status}</span>
                        </div>
                        <div className="compact-actions">
                            {renderActions?.(item)}
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}
