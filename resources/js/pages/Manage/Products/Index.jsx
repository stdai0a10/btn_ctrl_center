import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

export default function ManageProductsIndex() {
    const permissions = usePage().props.auth?.manage_permissions ?? [];
    const [filters, setFilters] = useState({ search: '' });
    const [payload, setPayload] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadProducts(1);
    }, []);

    async function loadProducts(page = 1) {
        setLoading(true);
        setError('');

        try {
            const response = await window.axios.get('/manage/api/products', {
                params: { ...filters, page },
            });
            setPayload(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '產品資料載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    const products = payload?.items ?? [];
    const pagination = payload?.pagination;

    return (
        <>
            <Head title="產品一覽" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Products</p>
                    <div className="panel-heading">
                        <h1>產品一覽</h1>
                        {permissions.includes('manage.products.create') && (
                            <Link className="button-link manage-header-action" href="/manage/products/create">新增產品</Link>
                        )}
                    </div>
                </section>

                <section className="panel">
                    <form className="manage-audit-filters" onSubmit={(event) => { event.preventDefault(); loadProducts(1); }}>
                        <label>搜尋 <input value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="產品 ID、型號或名稱" /></label>
                        <button type="submit" disabled={loading}>查詢</button>
                    </form>
                </section>

                {error && <div className="notice error">{error}</div>}

                <section className="panel">
                    <div className="panel-heading">
                        <h2>產品</h2>
                        {pagination && <span className="status-pill">{pagination.total} 筆</span>}
                    </div>
                    {loading && <p className="muted">載入中...</p>}
                    {!loading && products.length === 0 && <p className="muted">沒有符合條件的產品</p>}
                    {products.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>產品 ID</th><th>產品型號</th><th>產品名稱</th><th>功能數</th><th>設備數</th><th>建立時間</th><th>更新時間</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.map((product) => (
                                        <tr key={product.public_id} onClick={() => router.visit(`/manage/products/${product.public_id}`)}>
                                            <td className="account-code">{product.public_id}</td>
                                            <td className="account-code">{product.model_number}</td>
                                            <td>{product.name}</td>
                                            <td>{product.function_count}</td>
                                            <td>{product.device_count}</td>
                                            <td>{formatDate(product.created_at)}</td>
                                            <td>{formatDate(product.updated_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination pagination={pagination} loading={loading} onPage={loadProducts} />
                </section>
            </ManageLayout>
        </>
    );
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
