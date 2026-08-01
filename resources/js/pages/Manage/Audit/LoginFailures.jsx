import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

export default function ManageLoginFailures() {
    const [filters, setFilters] = useState({
        email: '',
        ip: '',
        user_public_id: '',
        failure_reason: '',
        from: '',
        to: '',
        locked_only: false,
    });
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
            const response = await window.axios.get('/manage/api/audit/login-failures', {
                params: { ...filters, locked_only: filters.locked_only ? 1 : 0, page },
            });
            setPayload(response.data.data);
            setExpanded(null);
        } catch (caught) {
            setError(errorMessage(caught, '登入失敗紀錄載入失敗。'));
        } finally {
            setLoading(false);
        }
    }

    const logs = payload?.items ?? [];
    const pagination = payload?.pagination;

    return (
        <>
            <Head title="登入失敗紀錄" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Audit</p>
                    <h1>登入失敗紀錄</h1>
                    <Link className="text-link" href="/manage/audit">返回審計資料</Link>
                </section>

                <section className="panel">
                    <form className="manage-audit-filters" onSubmit={(event) => { event.preventDefault(); loadLogs(1); }}>
                        <label> Email <input value={filters.email} onChange={(event) => setFilters({ ...filters, email: event.target.value })} /></label>
                        <label> IP <input value={filters.ip} onChange={(event) => setFilters({ ...filters, ip: event.target.value })} /></label>
                        <label>使用者公開 ID <input value={filters.user_public_id} onChange={(event) => setFilters({ ...filters, user_public_id: event.target.value })} /></label>
                        <label>
                            失敗原因
                            <select value={filters.failure_reason} onChange={(event) => setFilters({ ...filters, failure_reason: event.target.value })}>
                                <option value="">全部</option>
                                <option value="invalid_credentials_or_permission">帳密錯誤或無權限</option>
                                <option value="rate_limited">登入限制</option>
                            </select>
                        </label>
                        <label>開始日期 <input type="date" value={filters.from} onChange={(event) => setFilters({ ...filters, from: event.target.value })} /></label>
                        <label>結束日期 <input type="date" value={filters.to} onChange={(event) => setFilters({ ...filters, to: event.target.value })} /></label>
                        <label className="checkbox-label"><input type="checkbox" checked={filters.locked_only} onChange={(event) => setFilters({ ...filters, locked_only: event.target.checked })} />只顯示觸發鎖定</label>
                        <button type="submit" disabled={loading}>查詢</button>
                    </form>
                </section>

                {error && <div className="notice error">{error}</div>}

                <section className="panel">
                    <div className="panel-heading">
                        <h2>管理後台登入失敗</h2>
                        {pagination && <span className="status-pill">{pagination.total} 筆</span>}
                    </div>

                    {loading && <p className="muted">載入中...</p>}
                    {!loading && logs.length === 0 && <p className="muted">沒有登入失敗紀錄</p>}

                    {logs.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr><th>時間</th><th>Email</th><th>使用者公開 ID</th><th>IP</th><th>失敗原因</th><th>鎖定至</th><th>成功</th></tr>
                                </thead>
                                <tbody>
                                    {logs.map((log) => (
                                        <AuditRows
                                            key={log.id}
                                            expanded={expanded === log.id}
                                            onToggle={() => setExpanded(expanded === log.id ? null : log.id)}
                                            cells={[
                                                formatDate(log.created_at),
                                                log.email,
                                                log.user_public_id ?? '-',
                                                log.ip_address ?? '-',
                                                reasonLabel(log.failure_reason),
                                                formatDate(log.locked_until),
                                                log.success ? '是' : '否',
                                            ]}
                                        >
                                            <Detail label="User-Agent" value={log.user_agent} />
                                            <Detail label="失敗原因代碼" value={log.failure_reason} />
                                        </AuditRows>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination pagination={pagination} loading={loading} onPage={loadLogs} />
                </section>
            </ManageLayout>
        </>
    );
}

function AuditRows({ cells, expanded, onToggle, children }) {
    return (
        <>
            <tr onClick={onToggle}>{cells.map((cell, index) => <td key={index}>{cell}</td>)}</tr>
            {expanded && <tr className="audit-detail-row"><td colSpan={cells.length}><dl className="detail-list">{children}</dl></td></tr>}
        </>
    );
}

function Detail({ label, value }) {
    return <div><dt>{label}</dt><dd>{value ?? '-'}</dd></div>;
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

function reasonLabel(reason) {
    return { invalid_credentials_or_permission: '帳密錯誤或無管理權限', rate_limited: '登入限制' }[reason] ?? reason ?? '-';
}
