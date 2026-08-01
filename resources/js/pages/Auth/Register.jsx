import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../layouts/AppLayout';

export default function Register() {
    const [form, setForm] = useState({
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.post('/api/auth/register/email', form);
            setMessage(response.data.message);
            setForm({ email: '', password: '', password_confirmation: '' });
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '註冊失敗，請稍後再試。'] });
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="註冊" />
            <AppLayout contentClassName="auth-layout-main">
                <section className="auth-card">
                    <p className="eyebrow">Button Control Center</p>
                    <h1>建立帳號</h1>
                    <p className="lede">輸入 EMAIL 與密碼後，系統會寄出 24 小時內有效的驗證信。</p>

                    {message && <div className="notice success">{message}</div>}
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}

                    <form onSubmit={submit} className="stack">
                        <label>
                            EMAIL
                            <input
                                type="email"
                                value={form.email}
                                onChange={(event) => setForm({ ...form, email: event.target.value })}
                                autoComplete="email"
                                required
                            />
                            {errors.email?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>

                        <label>
                            密碼
                            <input
                                type="password"
                                value={form.password}
                                onChange={(event) => setForm({ ...form, password: event.target.value })}
                                autoComplete="new-password"
                                minLength={12}
                                required
                            />
                            <small>至少 12 碼。請避免常見弱密碼。</small>
                            {errors.password?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>

                        <label>
                            確認密碼
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
                            {processing ? '送出中...' : '寄出驗證信'}
                        </button>
                    </form>

                    <Link className="text-link" href="/login">已有帳號？前往登入</Link>
                </section>
            </AppLayout>
        </>
    );
}
