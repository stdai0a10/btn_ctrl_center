import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function ResetPassword() {
    const token = new URLSearchParams(window.location.search).get('token') ?? '';
    const [form, setForm] = useState({ password: '', password_confirmation: '' });
    const [status, setStatus] = useState({ state: 'loading', message: '正在檢查重設連結...' });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (!token) {
            setStatus({ state: 'error', message: '缺少重設 token。' });
            return;
        }

        window.axios.get('/api/auth/reset-password', { params: { token } })
            .then((response) => setStatus({ state: 'ready', message: response.data.message }))
            .catch((error) => setStatus({
                state: 'error',
                message: error.response?.data?.data?.token?.[0] ?? error.response?.data?.message ?? '密碼重設連結已失效。',
            }));
    }, [token]);

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await window.axios.post('/api/auth/reset-password', { ...form, token });
            setStatus({ state: 'success', message: response.data.message });
            setForm({ password: '', password_confirmation: '' });
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '密碼變更失敗。'] });
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="重設密碼" />
            <main className="auth-shell">
                <section className="auth-card">
                    <p className="eyebrow">Password Reset</p>
                    <h1>重設密碼</h1>
                    <div className={`notice ${status.state === 'success' || status.state === 'ready' ? 'success' : status.state === 'error' ? 'error' : ''}`}>
                        {status.message}
                    </div>
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}

                    {status.state === 'ready' && (
                        <form onSubmit={submit} className="stack">
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

                            <button type="submit" disabled={processing}>
                                {processing ? '變更中...' : '變更密碼'}
                            </button>
                        </form>
                    )}

                    <Link className="text-link" href="/login">返回登入</Link>
                </section>
            </main>
        </>
    );
}
