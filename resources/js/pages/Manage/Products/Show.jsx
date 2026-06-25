import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage, formErrors } from '../../../lib/http';

export default function ManageProductShow({ productPublicId }) {
    const permissions = usePage().props.auth?.manage_permissions ?? [];
    const canUpdateProduct = permissions.includes('manage.products.update');
    const canCreateFunction = permissions.includes('manage.product_functions.create');
    const canUpdateFunction = permissions.includes('manage.product_functions.update');
    const [product, setProduct] = useState(null);
    const [editForm, setEditForm] = useState({ model_number: '', name: '' });
    const [functionForm, setFunctionForm] = useState({ description: '' });
    const [functionEdits, setFunctionEdits] = useState({});
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        loadProduct(1);
    }, [productPublicId]);

    async function loadProduct(page = 1) {
        setLoading(true);
        setError('');

        try {
            const response = await window.axios.get(`/manage/api/products/${productPublicId}`, {
                params: { devices_page: page },
            });
            const nextProduct = response.data.data;
            setProduct(nextProduct);
            setEditForm({ model_number: nextProduct.model_number, name: nextProduct.name });
            setFunctionEdits(Object.fromEntries((nextProduct.functions ?? []).map((item) => [item.code, item.description])));
        } catch (caught) {
            setError(errorMessage(caught, '產品詳細資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    async function updateProduct(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.patch(`/manage/api/products/${productPublicId}`, editForm);
            setProduct({ ...product, ...response.data.data });
            setMessage(response.data.message ?? '產品已更新。');
        } catch (caught) {
            setErrors(formErrors(caught, '產品更新失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    async function createFunction(event) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.post(`/manage/api/products/${productPublicId}/functions`, functionForm);
            setProduct({ ...product, functions: [...(product.functions ?? []), response.data.data], function_count: (product.function_count ?? 0) + 1 });
            setFunctionEdits({ ...functionEdits, [response.data.data.code]: response.data.data.description });
            setFunctionForm({ description: '' });
            setMessage(response.data.message ?? '產品功能已建立。');
        } catch (caught) {
            setErrors(formErrors(caught, '產品功能建立失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    async function updateFunction(code) {
        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.patch(`/manage/api/product-functions/${code}`, {
                description: functionEdits[code] ?? '',
            });
            setProduct({
                ...product,
                functions: product.functions.map((item) => item.code === code ? response.data.data : item),
            });
            setMessage(response.data.message ?? '產品功能已更新。');
        } catch (caught) {
            setErrors(formErrors(caught, '產品功能更新失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    const devices = product?.devices;

    return (
        <>
            <Head title={product ? product.model_number : '產品詳細'} />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Product Detail</p>
                    <div className="manage-detail-header">
                        <h1>{product?.model_number ?? productPublicId}</h1>
                        <Link className="button-link manage-header-action" href="/manage/products">返回產品一覽</Link>
                    </div>
                </section>

                {loading && <p className="muted">載入中...</p>}
                {error && <div className="notice error">{error}</div>}
                {message && <div className="notice success">{message}</div>}
                {errors.form?.map((item) => <div className="notice error" key={item}>{item}</div>)}

                {product && (
                    <section className="grid-2">
                        <section className="panel">
                            <div className="panel-heading"><h2>產品資料</h2><span className="status-pill">{product.device_count} 部設備</span></div>
                            <dl className="detail-list">
                                <Detail label="產品 ID" value={product.public_id} />
                                <Detail label="產品型號" value={product.model_number} />
                                <Detail label="產品名稱" value={product.name} />
                                <Detail label="功能數量" value={product.function_count} />
                                <Detail label="建立時間" value={formatDate(product.created_at)} />
                                <Detail label="更新時間" value={formatDate(product.updated_at)} />
                            </dl>
                        </section>

                        {canUpdateProduct && (
                            <section className="panel">
                                <h2>修改產品</h2>
                                <form className="stack" onSubmit={updateProduct}>
                                    <label>
                                        產品型號
                                        <input value={editForm.model_number} onChange={(event) => setEditForm({ ...editForm, model_number: event.target.value })} maxLength={100} required />
                                        {errors.model_number?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                    </label>
                                    <label>
                                        產品名稱
                                        <input value={editForm.name} onChange={(event) => setEditForm({ ...editForm, name: event.target.value })} maxLength={255} required />
                                        {errors.name?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                    </label>
                                    <button type="submit" disabled={processing}>儲存產品</button>
                                </form>
                            </section>
                        )}

                        <section className="panel manage-wide-panel">
                            <div className="panel-heading">
                                <h2>產品功能</h2>
                                <span className="status-pill">{product.functions.length} 項</span>
                            </div>
                            {canCreateFunction && (
                                <form className="inline-form" onSubmit={createFunction}>
                                    <label>
                                        新增功能說明
                                        <input value={functionForm.description} onChange={(event) => setFunctionForm({ description: event.target.value })} maxLength={255} required />
                                        {errors.description?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                                    </label>
                                    <button type="submit" disabled={processing}>新增功能</button>
                                </form>
                            )}
                            {product.functions.length === 0 && <p className="muted">尚未建立產品功能</p>}
                            {product.functions.length > 0 && (
                                <div className="table-wrap">
                                    <table className="data-table">
                                        <thead><tr><th>功能代碼</th><th>功能說明</th><th>建立時間</th><th>更新時間</th>{canUpdateFunction && <th>操作</th>}</tr></thead>
                                        <tbody>{product.functions.map((item) => (
                                            <tr key={item.code}>
                                                <td className="account-code">{item.code}</td>
                                                <td>
                                                    {canUpdateFunction ? (
                                                        <input value={functionEdits[item.code] ?? ''} onChange={(event) => setFunctionEdits({ ...functionEdits, [item.code]: event.target.value })} maxLength={255} />
                                                    ) : item.description}
                                                </td>
                                                <td>{formatDate(item.created_at)}</td>
                                                <td>{formatDate(item.updated_at)}</td>
                                                {canUpdateFunction && <td><button type="button" className="button-ghost" disabled={processing} onClick={() => updateFunction(item.code)}>儲存</button></td>}
                                            </tr>
                                        ))}</tbody>
                                    </table>
                                </div>
                            )}
                        </section>

                        <section className="panel manage-wide-panel">
                            <div className="panel-heading"><h2>關聯設備</h2>{devices && <span className="status-pill">{devices.pagination.total} 部</span>}</div>
                            {devices?.items.length === 0 && <p className="muted">尚無設備關聯此產品</p>}
                            {devices?.items.length > 0 && (
                                <div className="table-wrap">
                                    <table className="data-table">
                                        <thead><tr><th>設備序號</th><th>設備名稱</th><th>所在房間</th><th>鎖定</th><th>啟用</th><th>建立時間</th></tr></thead>
                                        <tbody>{devices.items.map((device) => (
                                            <tr key={device.serial_number}>
                                                <td><Link className="inline-link account-code" href={`/manage/devices/${encodeURIComponent(device.serial_number)}`}>{device.serial_number}</Link></td>
                                                <td>{device.name ?? '-'}</td>
                                                <td>{device.room ? `${device.room.name} (${device.room.public_id})` : '-'}</td>
                                                <td>{device.is_locked ? '已上鎖' : '未上鎖'}</td>
                                                <td>{device.is_enabled ? '已啟用' : '已停用'}</td>
                                                <td>{formatDate(device.created_at)}</td>
                                            </tr>
                                        ))}</tbody>
                                    </table>
                                </div>
                            )}
                            <Pagination pagination={devices?.pagination} loading={loading} onPage={loadProduct} />
                        </section>
                    </section>
                )}
            </ManageLayout>
        </>
    );
}

function Detail({ label, value }) {
    return <div><dt>{label}</dt><dd>{value}</dd></div>;
}

function Pagination({ pagination, loading, onPage }) {
    if (!pagination || pagination.last_page <= 1) return null;
    return (
        <div className="pagination-row">
            <button type="button" className="button-ghost" disabled={pagination.current_page <= 1 || loading} onClick={() => onPage(pagination.current_page - 1)}>上一頁</button>
            <span>{pagination.current_page} / {pagination.last_page}</span>
            <button type="button" className="button-ghost" disabled={pagination.current_page >= pagination.last_page || loading} onClick={() => onPage(pagination.current_page + 1)}>下一頁</button>
        </div>
    );
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('zh-TW', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
