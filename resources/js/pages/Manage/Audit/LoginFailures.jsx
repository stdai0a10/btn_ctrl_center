import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { errorMessage } from '../../../lib/http';

export default function ManageLoginFailures() {
    const [payload, setPayload] = useState(null);
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
                params: { page },
            });
            setPayload(response.data.data);
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
            <AppLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Audit</p>
                    <h1>登入失敗紀錄</h1>
                    <Link className="text-link" href="/manage">返回管理後台</Link>
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
                                    <tr>
                                        <th>時間</th>
                                        <th>Email</th>
                                        <th>使用者公開 ID</th>
                                        <th>IP</th>
                                        <th>失敗原因</th>
                                        <th>鎖定至</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.map((log) => (
                                        <tr key={log.id}>
                                            <td>{formatDate(log.created_at)}</td>
                                            <td>{log.email}</td>
                                            <td>{log.user_public_id ?? '-'}</td>
                                            <td>{log.ip_address ?? '-'}</td>
                                            <td>{reasonLabel(log.failure_reason)}</td>
                                            <td>{formatDate(log.locked_until)}</td>
                                        </tr>
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
            </AppLayout>
        </>
    );
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('zh-TW', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function reasonLabel(reason) {
    return {
        invalid_credentials_or_permission: '帳密錯誤或無管理權限',
        rate_limited: '登入限制',
    }[reason] ?? reason ?? '-';
}
