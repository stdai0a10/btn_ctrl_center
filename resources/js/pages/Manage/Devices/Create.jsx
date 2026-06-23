import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { formErrors } from '../../../lib/http';

export default function ManageDeviceCreate() {
    const [form, setForm] = useState({ serial_number: '', secret: '', secret_confirmation: '' });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await window.axios.post('/manage/api/devices', form);
            router.visit(`/manage/devices/${encodeURIComponent(response.data.data.serial_number)}`);
        } catch (caught) {
            setErrors(formErrors(caught, '設備建立失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="新增設備" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Create Device</p>
                    <h1>新增設備</h1>
                    <Link className="text-link" href="/manage/devices">返回設備一覽</Link>
                </section>
                <section className="panel manage-form-panel">
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}
                    <form className="stack" onSubmit={submit}>
                        <label>
                            設備序號
                            <input value={form.serial_number} onChange={(event) => setForm({ ...form, serial_number: event.target.value })} maxLength={100} required />
                            {errors.serial_number?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
                        <label>
                            隱碼
                            <input type="password" value={form.secret} onChange={(event) => setForm({ ...form, secret: event.target.value })} maxLength={255} autoComplete="new-password" required />
                            {errors.secret?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
                        <label>
                            確認隱碼
                            <input type="password" value={form.secret_confirmation} onChange={(event) => setForm({ ...form, secret_confirmation: event.target.value })} maxLength={255} autoComplete="new-password" required />
                        </label>
                        <button type="submit" disabled={processing}>{processing ? '建立中...' : '建立設備'}</button>
                    </form>
                </section>
            </ManageLayout>
        </>
    );
}
