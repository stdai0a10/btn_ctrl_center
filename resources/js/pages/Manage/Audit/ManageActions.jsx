import { Head, Link } from '@inertiajs/react';
import { Fragment, useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

export default function ManageActions() {
    const [filters, setFilters] = useState({ actor: '', action: '', target_type: '', target_public_id: '', ip: '', from: '', to: '' });
    const [payload, setPayload] = useState(null);
    const [expanded, setExpanded] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadLogs(1);
    }, []);

    async function loadLogs(page = 1) {
        setLoading(true);
        setError('');
        try {
            const response = await window.axios.get('/manage/api/audit/manage-actions', { params: { ...filters, page } });
            setPayload(response.data.data);
            setExpanded(null);
        } catch (caught) {
            setError(errorMessage(caught, '管理後台操作紀錄載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    const logs = payload?.items ?? [];
    const pagination = payload?.pagination;

    return (
        <>
            <Head title="管理後台操作紀錄" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Audit</p>
                    <h1>管理後台操作紀錄</h1>
                    <Link className="text-link" href="/manage/audit">返回審計資料</Link>
                </section>

                <section className="panel">
                    <form className="manage-audit-filters" onSubmit={(event) => { event.preventDefault(); loadLogs(1); }}>
                        <label>操作者 <input value={filters.actor} onChange={(event) => setFilters({ ...filters, actor: event.target.value })} /></label>
                        <label>Action <input value={filters.action} onChange={(event) => setFilters({ ...filters, action: event.target.value })} /></label>
                        <label>Target Type <input value={filters.target_type} onChange={(event) => setFilters({ ...filters, target_type: event.target.value })} /></label>
                        <label>Target Public ID <input value={filters.target_public_id} onChange={(event) => setFilters({ ...filters, target_public_id: event.target.value })} /></label>
                        <label>IP <input value={filters.ip} onChange={(event) => setFilters({ ...filters, ip: event.target.value })} /></label>
                        <label>開始日期 <input type="date" value={filters.from} onChange={(event) => setFilters({ ...filters, from: event.target.value })} /></label>
                        <label>結束日期 <input type="date" value={filters.to} onChange={(event) => setFilters({ ...filters, to: event.target.value })} /></label>
                        <button type="submit" disabled={loading}>查詢</button>
                    </form>
                </section>

                {error && <div className="notice error">{error}</div>}

                <section className="panel">
                    <div className="panel-heading"><h2>操作紀錄</h2>{pagination && <span className="status-pill">{pagination.total} 筆</span>}</div>
                    {loading && <p className="muted">載入中...</p>}
                    {!loading && logs.length === 0 && <p className="muted">沒有操作紀錄</p>}
                    {logs.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead><tr><th>時間</th><th>操作者</th><th>Action</th><th>Target Type</th><th>Target Public ID</th><th>IP</th></tr></thead>
                                <tbody>
                                    {logs.map((log) => (
                                        <Fragment key={log.id}>
                                            <tr onClick={() => setExpanded(expanded === log.id ? null : log.id)}>
                                                <td>{formatDate(log.created_at)}</td>
                                                <td>{log.actor_public_id ?? log.actor_type}</td>
                                                <td>{log.action}</td>
                                                <td>{log.target_type ?? '-'}</td>
                                                <td>{log.target_public_id ?? '-'}</td>
                                                <td>{log.ip_address ?? '-'}</td>
                                            </tr>
                                            {expanded === log.id && (
                                                <tr className="audit-detail-row">
                                                    <td colSpan="6">
                                                        <dl className="detail-list">
                                                            <div><dt>User-Agent</dt><dd>{log.user_agent ?? '-'}</dd></div>
                                                            <div><dt>Metadata</dt><dd><pre className="audit-json">{JSON.stringify(log.metadata ?? {}, null, 2)}</pre></dd></div>
                                                        </dl>
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {pagination && pagination.last_page > 1 && (
                        <div className="pagination-row">
                            <button type="button" className="button-ghost" disabled={pagination.current_page <= 1 || loading} onClick={() => loadLogs(pagination.current_page - 1)}>上一頁</button>
                            <span>{pagination.current_page} / {pagination.last_page}</span>
                            <button type="button" className="button-ghost" disabled={pagination.current_page >= pagination.last_page || loading} onClick={() => loadLogs(pagination.current_page + 1)}>下一頁</button>
                        </div>
                    )}
                </section>
            </ManageLayout>
        </>
    );
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('zh-TW', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
