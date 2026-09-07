import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../layouts/AppLayout';

export default function Login() {
    const [form, setForm] = useState({ email: '', password: '' });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const redirectTo = new URLSearchParams(window.location.search).get('redirect') ?? '';

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await window.axios.post('/api/auth/login/email', {
                ...form,
                redirect: redirectTo,
            });
            router.visit(response.data.data.redirect_to ?? '/');
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '登入失敗，請稍後再試。'] });
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="登入" />
            <AppLayout contentClassName="auth-layout-main">
                <section className="auth-card">
                    <p className="eyebrow">Secure Sign In</p>
                    <h1>登入</h1>
                    <p className="lede">請使用已驗證 EMAIL 與密碼登入。</p>

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
                                autoComplete="current-password"
                                required
                            />
                            {errors.password?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>

                        <button type="submit" disabled={processing}>
                            {processing ? '登入中...' : '登入'}
                        </button>
                    </form>

                    <a className="button-link secondary" href="/api/auth/line/redirect">使用 LINE 登入</a>

                    <div className="actions-row">
                        <a className="text-link" href="/register">建立帳號</a>
                        <a className="text-link" href="/forgot-password">忘記密碼</a>
                    </div>
                </section>
            </AppLayout>
        </>
    );
}
