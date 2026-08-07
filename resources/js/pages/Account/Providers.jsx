import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';

export default function Providers() {
    const [profile, setProfile] = useState(null);
    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState({});
    const [bindingLine, setBindingLine] = useState(false);

    async function loadProfile() {
        const response = await window.axios.get('/api/account/profile');
        setProfile(response.data.data);
    }

    useEffect(() => {
        loadProfile();

        if (new URLSearchParams(window.location.search).get('bind') === 'line') {
            bindLine();
        }
    }, []);

    async function bindLine() {
        setBindingLine(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.get('/api/account/reauth');

            if (!response.data.data.is_valid) {
                const back = encodeURIComponent('/account/providers');
                const next = encodeURIComponent('/account/providers?bind=line');
                router.visit(`/reauth?back=${back}&next=${next}`);
                return;
            }

            window.location.assign('/api/account/providers/line/bind');
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '無法開始 LINE 綁定。'] });
        } finally {
            setBindingLine(false);
        }
    }

    async function unbindLine() {
        if (!window.confirm('解除 LINE 綁定後，將無法再用此 LINE 登入。確定解除？')) {
            return;
        }

        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.delete('/api/account/providers/line');
            setMessage(response.data.message);
            await loadProfile();
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '解除綁定失敗。'] });
        }
    }

    const isBound = profile?.providers.line;

    return (
        <>
            <Head title="登入方式管理" />
            <AppLayout contentClassName="auth-layout-main">
                <section className="auth-card">
                    <p className="eyebrow">Login Providers</p>
                    <h1>登入方式</h1>
                    <p className="lede">LINE 可作為獨立登入方式。綁定或解除前需先完成重新驗證。</p>

                    {message && <div className="notice success">{message}</div>}
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}
                    {errors.reauth?.map((error) => (
                        <div className="notice error" key={error}>
                            {error} <Link className="inline-link" href="/reauth?back=/account/providers">前往重新驗證</Link>
                        </div>
                    ))}
                    {errors.line?.map((error) => <div className="notice error" key={error}>{error}</div>)}

                    <div className="provider-card">
                        <div>
                            <strong>LINE</strong>
                            <p>{isBound ? '已綁定' : '未綁定'}</p>
                        </div>
                        {isBound ? (
                            <button type="button" onClick={unbindLine}>解除綁定</button>
                        ) : (
                            <button className="button-link secondary" type="button" onClick={bindLine} disabled={bindingLine}>
                                {bindingLine ? '準備 LINE 綁定...' : '綁定 LINE'}
                            </button>
                        )}
                    </div>

                    <Link className="text-link" href="/account/profile">返回帳號資料</Link>
                </section>
            </AppLayout>
        </>
    );
}
