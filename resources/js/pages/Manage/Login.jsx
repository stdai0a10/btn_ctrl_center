import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ManageLoginLayout from '../../layouts/ManageLoginLayout';
import { formErrors } from '../../lib/http';

export default function ManageLogin() {
    const [form, setForm] = useState({ email: '', password: '' });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await window.axios.post('/manage/api/login', form);
            router.visit(response.data.data.redirect_to ?? '/manage');
        } catch (error) {
            setErrors(formErrors(error, '管理後台登入失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="管理後台登入" />
            <ManageLoginLayout>
                <section className="auth-card manage-login-card">
                    <p className="eyebrow">Management</p>
                    <h1>管理後台登入</h1>
                    <p className="lede">請使用具管理權限的帳號與密碼登入。</p>

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
                            {processing ? '登入中...' : '登入管理後台'}
                        </button>
                    </form>
                </section>
            </ManageLoginLayout>
        </>
    );
}
