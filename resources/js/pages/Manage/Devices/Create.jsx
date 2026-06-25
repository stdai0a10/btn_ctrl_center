import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { formErrors } from '../../../lib/http';

export default function ManageDeviceCreate() {
    const [form, setForm] = useState({ product_public_id: '', serial_number: '', secret: '', secret_confirmation: '' });
    const [productQuery, setProductQuery] = useState('');
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [suggestions, setSuggestions] = useState([]);
    const [suggestionLoading, setSuggestionLoading] = useState(false);
    const [suggestionTouched, setSuggestionTouched] = useState(false);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (productQuery.trim() === '' || selectedProduct?.model_number === productQuery) {
            setSuggestions([]);
            setSuggestionLoading(false);
            return undefined;
        }

        const timer = window.setTimeout(async () => {
            setSuggestionLoading(true);
            try {
                const response = await window.axios.get('/manage/api/products', {
                    params: {
                        suggest: true,
                        search: productQuery,
                        per_page: 10,
                    },
                });
                setSuggestions(response.data.data.items ?? []);
            } catch {
                setSuggestions([]);
            } finally {
                setSuggestionLoading(false);
            }
        }, 300);

        return () => window.clearTimeout(timer);
    }, [productQuery, selectedProduct]);

    function changeProductQuery(value) {
        setProductQuery(value);
        setSuggestionTouched(true);

        if (selectedProduct && value !== selectedProduct.model_number) {
            setSelectedProduct(null);
            setForm((current) => ({ ...current, product_public_id: '' }));
        }
    }

    function chooseProduct(product) {
        setSelectedProduct(product);
        setProductQuery(product.model_number);
        setForm((current) => ({ ...current, product_public_id: product.public_id }));
        setSuggestions([]);
        setSuggestionTouched(false);
        setErrors((current) => ({ ...current, product_public_id: undefined }));
    }

    async function submit(event) {
        event.preventDefault();
        if (!form.product_public_id) {
            setErrors({ product_public_id: ['請從建議清單選擇產品。'] });
            return;
        }

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
                            產品型號
                            <input
                                value={productQuery}
                                onChange={(event) => changeProductQuery(event.target.value)}
                                placeholder="輸入產品型號，例如 BTN-001"
                                maxLength={100}
                                autoComplete="off"
                                required
                            />
                            {selectedProduct && <small>已選擇：{selectedProduct.model_number} · {selectedProduct.name}</small>}
                            {suggestionLoading && <small>查詢產品中...</small>}
                            {!suggestionLoading && suggestionTouched && productQuery.trim() !== '' && suggestions.length === 0 && !selectedProduct && (
                                <small className="field-error">找不到相近產品</small>
                            )}
                            {suggestions.length > 0 && (
                                <div className="suggestion-list" role="listbox">
                                    {suggestions.map((product) => (
                                        <button
                                            type="button"
                                            className="suggestion-item"
                                            key={product.public_id}
                                            onClick={() => chooseProduct(product)}
                                        >
                                            <span className="account-code">{product.model_number}</span>
                                            <small>{product.name}</small>
                                        </button>
                                    ))}
                                </div>
                            )}
                            {errors.product_public_id?.map((error) => <small className="field-error" key={error}>{error}</small>)}
                        </label>
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
