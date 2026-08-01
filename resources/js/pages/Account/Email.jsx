import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';

export default function Email() {
    const [profile, setProfile] = useState(null);
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState({});

    useEffect(() => {
        window.axios.get('/api/account/profile').then((response) => setProfile(response.data.data));
    }, []);

    async function submit(event) {
        event.preventDefault();
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.post('/api/account/email/change-request', { email });
            setMessage(response.data.message);
            setEmail('');
            const profileResponse = await window.axios.get('/api/account/profile');
            setProfile(profileResponse.data.data);
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '申請失敗。'] });
        }
    }

    return (
        <>
            <Head title="EMAIL 設定" />
            <AppLayout contentClassName="auth-layout-main">
                <section className="auth-card">
                    <p className="eyebrow">Email Settings</p>
                    <h1>EMAIL</h1>
                    {profile && (
                        <div className="profile-grid">
                            <span>目前 EMAIL</span><strong>{profile.email.current ?? '未設定'}</strong>
                            <span>驗證狀態</span><strong>{profile.email.is_verified ? '已驗證' : '未驗證'}</strong>
                            <span>待驗證新 EMAIL</span><strong>{profile.email.pending ?? '無'}</strong>
                        </div>
                    )}

                    {message && <div className="notice success">{message}</div>}
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}
                    {errors.reauth?.map((error) => (
                        <div className="notice error" key={error}>
                            {error} <Link className="inline-link" href="/reauth?back=/account/email">前往重新驗證</Link>
                        </div>
                    ))}

                    <form onSubmit={submit} className="stack">
                        <label>
                            新 EMAIL
                            <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
                            {errors.email?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
                        <button type="submit">寄出變更驗證信</button>
                    </form>

                    <Link className="text-link" href="/account/profile">返回帳號資料</Link>
                </section>
            </AppLayout>
        </>
    );
}
