import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { formErrors } from '../../lib/http';

export default function HousesIndex() {
    const [houses, setHouses] = useState([]);
    const [name, setName] = useState('');
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        loadHouses();
    }, []);

    async function loadHouses() {
        setLoading(true);
        const response = await window.axios.get('/api/houses');
        setHouses(response.data.data);
        setLoading(false);
    }

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.post('/api/houses', { name });
            setName('');
            setMessage(response.data.message);
            setHouses([response.data.data, ...houses]);
        } catch (error) {
            setErrors(formErrors(error, '建立房屋失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="房屋管理" />
            <main className="app-shell">
                <nav className="topbar">
                    <Link href="/" className="brand">Button Control Center</Link>
                    <div className="topbar-links">
                        <Link href="/house-invitations">邀請</Link>
                        <Link href="/account/profile">帳號</Link>
                    </div>
                </nav>

                <section className="page-header">
                    <p className="eyebrow">Houses</p>
                    <h1>房屋管理</h1>
                </section>

                <section className="split-layout">
                    <form className="panel stack" onSubmit={submit}>
                        <h2>建立房屋</h2>
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
                            <h2>我的房屋</h2>
                            <button type="button" className="button-ghost" onClick={loadHouses} disabled={loading}>更新</button>
                        </div>
                        {loading && <p className="muted">載入中...</p>}
                        {!loading && houses.length === 0 && <p className="muted">尚無房屋</p>}
                        <div className="item-list">
                            {houses.map((house) => (
                                <article className="list-item" key={house.public_id}>
                                    <div>
                                        <strong>{house.name}</strong>
                                        <span>{house.role === 'owner' ? '屋主' : '住戶'} · {house.members_count} 人</span>
                                    </div>
                                    <button type="button" onClick={() => router.visit(`/houses/${house.public_id}`)}>開啟</button>
                                </article>
                            ))}
                        </div>
                    </section>
                </section>
            </main>
        </>
    );
}
