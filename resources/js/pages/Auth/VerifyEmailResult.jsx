import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function VerifyEmailResult() {
    const [state, setState] = useState({ status: 'loading', message: '正在驗證 EMAIL...' });

    useEffect(() => {
        const token = new URLSearchParams(window.location.search).get('token');

        if (!token) {
            setState({ status: 'error', message: '缺少驗證 token。' });
            return;
        }

        window.axios.get('/api/auth/email/verify', { params: { token } })
            .then((response) => setState({ status: 'success', message: response.data.message }))
            .catch((error) => setState({
                status: 'error',
                message: error.response?.data?.data?.token?.[0] ?? error.response?.data?.message ?? '驗證失敗，請重新申請驗證信。',
            }));
    }, []);

    return (
        <>
            <Head title="EMAIL 驗證結果" />
            <main className="auth-shell">
                <section className="auth-card">
                    <p className="eyebrow">Email Verification</p>
                    <h1>驗證結果</h1>
                    <div className={`notice ${state.status === 'success' ? 'success' : state.status === 'error' ? 'error' : ''}`}>
                        {state.message}
                    </div>
                    <div className="actions-row">
                        <a className="text-link" href="/login">前往登入</a>
                        <a className="text-link" href="/register">重新註冊</a>
                    </div>
                </section>
            </main>
        </>
    );
}
