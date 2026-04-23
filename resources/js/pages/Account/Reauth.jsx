import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Reauth() {
    const [password, setPassword] = useState('');
    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const back = new URLSearchParams(window.location.search).get('back') ?? '/account/profile';

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.post('/api/account/reauth', { password });
            setMessage(response.data.message);
            window.setTimeout(() => router.visit(back), 350);
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '重新驗證失敗。'] });
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="重新驗證" />
            <main className="auth-shell">
                <section className="auth-card">
                    <p className="eyebrow">Re-authentication</p>
                    <h1>重新驗證</h1>
                    <p className="lede">敏感操作前需重新輸入密碼。通過後 10 分鐘內有效。</p>

                    {message && <div className="notice success">{message}</div>}
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}

                    <form onSubmit={submit} className="stack">
                        <label>
                            密碼
                            <input
                                type="password"
                                value={password}
                                onChange={(event) => setPassword(event.target.value)}
                                autoComplete="current-password"
                                required
                            />
                            {errors.password?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
                        <button type="submit" disabled={processing}>
                            {processing ? '驗證中...' : '完成重新驗證'}
                        </button>
                    </form>

                    <Link className="text-link" href={back}>取消</Link>
                </section>
            </main>
        </>
    );
}
