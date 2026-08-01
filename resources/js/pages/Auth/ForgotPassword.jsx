import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.post('/api/auth/forgot-password', { email });
            setMessage(response.data.message);
            setEmail('');
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '請求失敗，請稍後再試。'] });
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="忘記密碼" />
            <main className="auth-shell">
                <section className="auth-card">
                    <p className="eyebrow">Password Recovery</p>
                    <h1>忘記密碼</h1>
                    <p className="lede">輸入已驗證 EMAIL。若資料正確，系統會寄出 1 小時內有效的密碼變更信。</p>

                    {message && <div className="notice success">{message}</div>}
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}

                    <form onSubmit={submit} className="stack">
                        <label>
                            EMAIL
                            <input
                                type="email"
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                autoComplete="email"
                                required
                            />
                            {errors.email?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                            {errors.rate_limit?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>

                        <button type="submit" disabled={processing}>
                            {processing ? '送出中...' : '寄出密碼變更信'}
                        </button>
                    </form>

                    <Link className="text-link" href="/login">返回登入</Link>
                </section>
            </main>
        </>
    );
}
