import { Head, Link, usePage } from '@inertiajs/react';
import { EllipsisVertical, Pencil, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage, formErrors } from '../../../lib/http';

export default function ManageProductShow({ productPublicId }) {
    const permissions = usePage().props.auth?.manage_permissions ?? [];
    const canUpdateProduct = permissions.includes('manage.products.update');
    const canCreateFunction = permissions.includes('manage.product_functions.create');
    const canUpdateFunction = permissions.includes('manage.product_functions.update');
    const canDeleteFunction = permissions.includes('manage.product_functions.delete');
    const [product, setProduct] = useState(null);
    const [editForm, setEditForm] = useState({ model_number: '', name: '' });
    const [functionForm, setFunctionForm] = useState({ description: '' });
    const [functionActionMenu, setFunctionActionMenu] = useState(null);
    const [editingFunction, setEditingFunction] = useState(null);
    const [editFunctionForm, setEditFunctionForm] = useState({ description: '' });
    const [deletingFunction, setDeletingFunction] = useState(null);
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const functionMenuRef = useRef(null);
    const canManageFunctions = canUpdateFunction || canDeleteFunction;

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
            setFunctionForm({ description: '' });
            setMessage(response.data.message ?? '產品功能已建立。');
        } catch (caught) {
            setErrors(formErrors(caught, '產品功能建立失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    useEffect(() => {
        if (!functionActionMenu) return undefined;

        function handlePointerDown(event) {
            if (functionMenuRef.current?.contains(event.target)) return;

            const trigger = event.target.closest?.('[data-function-action-trigger]');
            if (trigger?.dataset.functionActionTrigger === functionActionMenu.function.code) return;

            setFunctionActionMenu(null);
        }

        function handleKeyDown(event) {
            if (event.key === 'Escape') setFunctionActionMenu(null);
        }

        document.addEventListener('pointerdown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [functionActionMenu]);

    function toggleFunctionMenu(event, item) {
        if (functionActionMenu?.function.code === item.code) {
            setFunctionActionMenu(null);
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const menuWidth = 184;
        const left = Math.min(Math.max(12, rect.right - menuWidth), window.innerWidth - menuWidth - 12);
        const shouldOpenAbove = rect.bottom + 112 > window.innerHeight;

        setFunctionActionMenu({
            function: item,
            position: {
                left,
                top: shouldOpenAbove ? rect.top - 8 : rect.bottom + 8,
                placement: shouldOpenAbove ? 'top' : 'bottom',
            },
        });
    }

    function openEditFunctionDialog(item) {
        setFunctionActionMenu(null);
        setErrors({});
        setMessage('');
        setEditingFunction(item);
        setEditFunctionForm({ description: item.description ?? '' });
    }

    function openDeleteFunctionDialog(item) {
        setFunctionActionMenu(null);
        setErrors({});
        setMessage('');
        setDeletingFunction(item);
    }

    async function updateFunction(event) {
        event.preventDefault();
        if (!editingFunction) return;

        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.patch(`/manage/api/product-functions/${editingFunction.code}`, editFunctionForm);
            setProduct({
                ...product,
                functions: product.functions.map((item) => item.code === editingFunction.code ? response.data.data : item),
            });
            setEditingFunction(null);
            setMessage(response.data.message ?? '產品功能已更新。');
        } catch (caught) {
            setErrors(formErrors(caught, '產品功能更新失敗。'));
        } finally {
            setProcessing(false);
        }
    }

    async function deleteFunction() {
        if (!deletingFunction) return;

        setProcessing(true);
        setErrors({});
        setMessage('');

        try {
            const response = await window.axios.delete(`/manage/api/product-functions/${deletingFunction.code}`);
            setProduct({
                ...product,
                functions: product.functions.filter((item) => item.code !== deletingFunction.code),
                function_count: Math.max((product.function_count ?? 1) - 1, 0),
            });
            setDeletingFunction(null);
            setMessage(response.data.message ?? '產品功能已刪除。');
        } catch (caught) {
            setErrors(formErrors(caught, '產品功能刪除失敗。'));
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
                                <form className="inline-form product-function-create-form" onSubmit={createFunction}>
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
                                        <thead><tr><th>功能代碼</th><th>功能說明</th><th>最近更新時間</th>{canManageFunctions && <th>操作</th>}</tr></thead>
                                        <tbody>{product.functions.map((item) => (
                                            <tr key={item.code}>
                                                <td className="account-code">{item.code}</td>
                                                <td>{item.description}</td>
                                                <td>{formatDate(latestTimestamp(item.created_at, item.updated_at))}</td>
                                                {canManageFunctions && (
                                                    <td>
                                                        <button
                                                            type="button"
                                                            className="table-action-trigger"
                                                            data-function-action-trigger={item.code}
                                                            aria-label={`${item.code} 操作`}
                                                            aria-expanded={functionActionMenu?.function.code === item.code}
                                                            aria-haspopup="menu"
                                                            disabled={processing}
                                                            onClick={(event) => toggleFunctionMenu(event, item)}
                                                        >
                                                            <EllipsisVertical size={19} strokeWidth={2.4} />
                                                        </button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}</tbody>
                                    </table>
                                </div>
                            )}
                            {functionActionMenu && createPortal(
                                <div
                                    ref={functionMenuRef}
                                    className={`table-action-menu-list is-${functionActionMenu.position.placement}`}
                                    role="menu"
                                    style={{
                                        left: functionActionMenu.position.left,
                                        top: functionActionMenu.position.top,
                                    }}
                                >
                                    {canUpdateFunction && (
                                        <button type="button" role="menuitem" onClick={() => openEditFunctionDialog(functionActionMenu.function)}>
                                            <Pencil size={17} />
                                            修改功能說明
                                        </button>
                                    )}
                                    {canDeleteFunction && (
                                        <button type="button" role="menuitem" className="is-danger" onClick={() => openDeleteFunctionDialog(functionActionMenu.function)}>
                                            <Trash2 size={17} />
                                            刪除產品功能
                                        </button>
                                    )}
                                </div>,
                                document.body,
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
                {editingFunction && (
                    <Dialog title="修改功能說明" onClose={() => setEditingFunction(null)} closeDisabled={processing}>
                        {errors.form?.map((item) => <div className="notice error" key={item}>{item}</div>)}
                        <form className="dialog-form stack" onSubmit={updateFunction}>
                            <label>
                                功能代碼
                                <input value={editingFunction.code} disabled />
                            </label>
                            <label>
                                功能說明
                                <input value={editFunctionForm.description} onChange={(event) => setEditFunctionForm({ description: event.target.value })} maxLength={255} required autoFocus />
                                {errors.description?.map((item) => <small className="field-error" key={item}>{item}</small>)}
                            </label>
                            <div className="dialog-actions">
                                <button type="button" className="button-ghost" disabled={processing} onClick={() => setEditingFunction(null)}>取消</button>
                                <button type="submit" disabled={processing}>{processing ? '儲存中...' : '儲存'}</button>
                            </div>
                        </form>
                    </Dialog>
                )}
                {deletingFunction && (
                    <Dialog title="刪除產品功能" onClose={() => setDeletingFunction(null)} closeDisabled={processing}>
                        {errors.form?.map((item) => <div className="notice error" key={item}>{item}</div>)}
                        <p className="dialog-description">
                            確定要刪除產品功能「{deletingFunction.code}」嗎？此操作會移除功能說明「{deletingFunction.description}」。
                        </p>
                        <div className="dialog-actions">
                            <button type="button" className="button-ghost" disabled={processing} onClick={() => setDeletingFunction(null)}>取消</button>
                            <button type="button" className="button-danger" disabled={processing} onClick={deleteFunction}>{processing ? '刪除中...' : '確認刪除'}</button>
                        </div>
                    </Dialog>
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

function Dialog({ title, children, onClose, closeDisabled = false }) {
    useEffect(() => {
        function handleKeyDown(event) {
            if (event.key === 'Escape' && !closeDisabled) onClose();
        }

        document.addEventListener('keydown', handleKeyDown);

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [closeDisabled, onClose]);

    return createPortal(
        <div className="app-dialog-backdrop" role="presentation" onMouseDown={(event) => {
            if (event.target === event.currentTarget && !closeDisabled) onClose();
        }}>
            <section className="app-dialog" role="dialog" aria-modal="true" aria-labelledby="product-function-dialog-title">
                <div className="app-dialog-heading">
                    <h2 id="product-function-dialog-title">{title}</h2>
                    <button type="button" className="app-dialog-close" aria-label="關閉" disabled={closeDisabled} onClick={onClose}>×</button>
                </div>
                {children}
            </section>
        </div>,
        document.body,
    );
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('zh-TW', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function latestTimestamp(createdAt, updatedAt) {
    if (!createdAt) return updatedAt;
    if (!updatedAt) return createdAt;

    return new Date(updatedAt) > new Date(createdAt) ? updatedAt : createdAt;
}
