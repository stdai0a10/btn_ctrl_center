import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';
import { formErrors } from '../../lib/http';

export default function RoomsIndex() {
    const [rooms, setRooms] = useState([]);
    const [name, setName] = useState('');
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        loadRooms();
    }, []);

    async function loadRooms() {
        setLoading(true);
        const response = await window.axios.get('/api/rooms');
        setRooms(response.data.data);
        setLoading(false);
    }

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.post('/api/rooms', { name });
            setName('');
            setMessage(response.data.message);
            setRooms([response.data.data, ...rooms]);
        } catch (error) {
            setErrors(formErrors(error, '建立房間失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="房間管理" />
            <AppLayout>
                <section className="page-header">
                    <p className="eyebrow">Rooms</p>
                    <h1>房間管理</h1>
                    <Link className="text-link" href="/room-invitations">查看邀請與申請</Link>
                </section>

                <section className="split-layout">
                    <form className="panel stack" onSubmit={submit}>
                        <h2>建立房間</h2>
                        {message && <div className="notice success">{message}</div>}
                        {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}
                        <label>
                            名稱
                            <input value={name} onChange={(event) => setName(event.target.value)} maxLength={100} required />
                            {errors.name?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
                        <button type="submit" disabled={processing}>{processing ? '建立中...' : '建立'}</button>
                    </form>

                    <section className="panel">
                        <div className="panel-heading">
                            <h2>我的房間</h2>
                            <button type="button" className="button-ghost" onClick={loadRooms} disabled={loading}>更新</button>
                        </div>
                        {loading && <p className="muted">載入中...</p>}
                        {!loading && rooms.length === 0 && <p className="muted">尚無房間</p>}
                        <div className="item-list">
                            {rooms.map((room) => (
                                <article className="list-item" key={room.public_id}>
                                    <div>
                                        <strong>{room.name}</strong>
                                        <span>{room.role === 'owner' ? '房主' : '住戶'} · {room.members_count} 人</span>
                                    </div>
                                    <button type="button" onClick={() => router.visit(`/rooms/${room.public_id}`)}>開啟</button>
                                </article>
                            ))}
                        </div>
                    </section>
                </section>
            </AppLayout>
        </>
    );
}
