import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { formErrors } from '../../../lib/http';

export default function ManageProductCreate() {
    const [form, setForm] = useState({ model_number: '', name: '' });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await window.axios.post('/manage/api/products', form);
            router.visit(`/manage/products/${response.data.data.public_id}`);
        } catch (caught) {
            setErrors(formErrors(caught, '產品建立失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    return (
        <>
            <Head title="新增產品" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Create Product</p>
                    <div className="manage-detail-header">
                        <h1>新增產品</h1>
                        <Link className="button-link manage-header-action" href="/manage/products">返回產品一覽</Link>
                    </div>
                </section>
                <section className="panel manage-form-panel">
                    {errors.form?.map((error) => <div className="notice error" key={error}>{error}</div>)}
                    <form className="stack" onSubmit={submit}>
                        <label>
                            產品型號
                            <input value={form.model_number} onChange={(event) => setForm({ ...form, model_number: event.target.value })} maxLength={100} required />
                            {errors.model_number?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
                        <label>
                            產品名稱
                            <input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} maxLength={255} required />
                            {errors.name?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
                        <button type="submit" disabled={processing}>{processing ? '建立中...' : '建立產品'}</button>
                    </form>
                </section>
            </ManageLayout>
        </>
    );
}
