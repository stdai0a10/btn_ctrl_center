import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';

export default function Profile() {
    const [profile, setProfile] = useState(null);
    const [name, setName] = useState('');
    const [editingName, setEditingName] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState({});

    useEffect(() => {
        window.axios.get('/api/account/profile').then((response) => {
            setProfile(response.data.data);
            setName(response.data.data.name ?? '');
        });
    }, []);

    function startEditingName() {
        setName(profile?.name ?? '');
        setEditingName(true);
        setMessage('');
        setErrors({});
    }

    function cancelEditingName() {
        setName(profile?.name ?? '');
        setEditingName(false);
        setErrors({});
    }

    async function submitName(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.put('/api/account/profile', { name });
            setProfile(response.data.data);
            setEditingName(false);
            setMessage(response.data.message);
        } catch (error) {
            setErrors(error.response?.data?.data ?? { form: [error.response?.data?.message ?? '更新失敗。'] });
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="帳號資料" />
            <AppLayout contentClassName="auth-layout-main">
                <section className="auth-card">
                    <p className="eyebrow">Account Profile</p>
                    <h1>帳號資料</h1>
                    {profile && (
                        <div className="account-summary">
                            <div className="account-row">
                                <span className="account-label">ID</span>
                                <strong className="account-value account-code">{profile.public_id}</strong>
                            </div>

                            <div className="account-row">
                                <span className="account-label">顯示名稱</span>
                                <div className="account-value">
                                    {editingName ? (
                                        <form className="profile-inline-edit" onSubmit={submitName}>
                                            <input
                                                value={name}
                                                onChange={(event) => setName(event.target.value)}
                                                maxLength={100}
                                                autoFocus
                                            />
                                            <div className="compact-actions">
                                                <button type="submit" disabled={processing}>{processing ? '套用中...' : '套用'}</button>
                                                <button type="button" className="button-ghost" onClick={cancelEditingName} disabled={processing}>取消</button>
                                            </div>
                                            {errors.name?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                                        </form>
                                    ) : (
                                        <div className="profile-display-row">
                                            <strong>{profile.display_name}</strong>
                                            <button type="button" className="button-ghost" onClick={startEditingName}>更新名字</button>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="account-row">
                                <span className="account-label">EMAIL</span>
                                <strong className="account-value">{profile.email.current ?? '未設定'}</strong>
                            </div>

                            <div className="account-row">
                                <span className="account-label">密碼</span>
                                <strong className="account-value">{profile.password.is_set ? '已設定' : '未設定'}</strong>
                            </div>

                            <div className="account-row">
                                <span className="account-label">LINE</span>
                                <strong className="account-value">{profile.providers.line ? '已綁定' : '未綁定'}</strong>
                            </div>
                        </div>
                    )}

                    {message && <div className="notice success">{message}</div>}
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}

                    <div className="actions-row">
                        <Link className="text-link" href="/account/email">EMAIL 設定</Link>
                        <Link className="text-link" href="/account/security">安全設定</Link>
                        <Link className="text-link" href="/account/providers">登入方式</Link>
                    </div>
                </section>
            </AppLayout>
        </>
    );
}
