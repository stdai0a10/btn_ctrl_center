import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Profile() {
    const [profile, setProfile] = useState(null);
    const [name, setName] = useState('');
    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState({});

    useEffect(() => {
        window.axios.get('/api/account/profile').then((response) => {
            setProfile(response.data.data);
            setName(response.data.data.name ?? '');
        });
    }, []);

    async function submit(event) {
        event.preventDefault();
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.put('/api/account/profile', { name });
            setProfile(response.data.data);
            setMessage(response.data.message);
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '更新失敗。'] });
        }
    }

    return (
        <>
            <Head title="帳號資料" />
            <main className="auth-shell">
                <section className="auth-card">
                    <p className="eyebrow">Account Profile</p>
                    <h1>帳號資料</h1>
                    {profile && (
                        <div className="profile-grid">
                            <span>ID</span><strong>{profile.public_id}</strong>
                            <span>顯示名稱</span><strong>{profile.display_name}</strong>
                            <span>EMAIL</span><strong>{profile.email.current ?? '未設定'}</strong>
                            <span>密碼</span><strong>{profile.password.is_set ? '已設定' : '未設定'}</strong>
                            <span>LINE</span><strong>{profile.providers.line ? '已綁定' : '未綁定'}</strong>
                        </div>
                    )}

                    {message && <div className="notice success">{message}</div>}
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}

                    <form onSubmit={submit} className="stack">
                        <label>
                            名字
                            <input value={name} onChange={(event) => setName(event.target.value)} maxLength={100} />
                            {errors.name?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
                        <button type="submit">更新名字</button>
                    </form>

                    <div className="actions-row">
                        <Link className="text-link" href="/account/email">EMAIL 設定</Link>
                        <Link className="text-link" href="/account/security">安全設定</Link>
                        <Link className="text-link" href="/account/providers">登入方式</Link>
                    </div>
                </section>
            </main>
        </>
    );
}
