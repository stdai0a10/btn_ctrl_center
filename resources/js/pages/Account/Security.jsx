import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Security() {
    const [profile, setProfile] = useState(null);
    const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
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
            const response = await window.axios.put('/api/account/password', form);
            setMessage(response.data.message);
            setForm({ current_password: '', password: '', password_confirmation: '' });
            const profileResponse = await window.axios.get('/api/account/profile');
            setProfile(profileResponse.data.data);
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '密碼更新失敗。'] });
        }
    }

    const hasPassword = profile?.password.is_set;

    return (
        <>
            <Head title="安全設定" />
            <main className="auth-shell">
                <section className="auth-card">
                    <p className="eyebrow">Security</p>
                    <h1>安全設定</h1>
                    {profile && <p className="lede">密碼狀態：{hasPassword ? '已設定' : '未設定'}</p>}
                    {message && <div className="notice success">{message}</div>}
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}
                    {errors.email?.map((error) => <div className="notice error" key={error}>{error}</div>)}

                    <form onSubmit={submit} className="stack">
                        {hasPassword && (
                            <label>
                                目前密碼
                                <input
                                    type="password"
                                    value={form.current_password}
                                    onChange={(event) => setForm({ ...form, current_password: event.target.value })}
                                    autoComplete="current-password"
                                />
                                {errors.current_password?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                            </label>
                        )}

                        <label>
                            新密碼
                            <input
                                type="password"
                                value={form.password}
                                onChange={(event) => setForm({ ...form, password: event.target.value })}
                                autoComplete="new-password"
                                minLength={12}
                                required
                            />
                            {errors.password?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>

                        <label>
                            確認新密碼
                            <input
                                type="password"
                                value={form.password_confirmation}
                                onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
                                autoComplete="new-password"
                                minLength={12}
                                required
                            />
                        </label>

                        <button type="submit">更新密碼</button>
                    </form>

                    <Link className="text-link" href="/account/profile">返回帳號資料</Link>
                </section>
            </main>
        </>
    );
}
