import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ManageLayout from '../../../layouts/ManageLayout';
import { errorMessage } from '../../../lib/http';

const jobStatuses = ['queued', 'running', 'succeeded', 'failed', 'device_offline', 'timed_out', 'unauthorized', 'canceled'];

export default function ManageDeviceRuntimeIndex() {
    const permissions = usePage().props.auth?.manage_permissions ?? [];
    const canManageRuntime = permissions.includes('manage.device_runtime.manage');
    const canCancelJobs = permissions.includes('manage.button_jobs.cancel');
    const [deviceFilters, setDeviceFilters] = useState({ search: '', runner_status: '', runtime_disabled: '' });
    const [jobFilters, setJobFilters] = useState({ search: '', status: '', device_serial_number: '' });
    const [devicesPayload, setDevicesPayload] = useState(null);
    const [jobsPayload, setJobsPayload] = useState(null);
    const [devicesLoading, setDevicesLoading] = useState(true);
    const [jobsLoading, setJobsLoading] = useState(true);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        loadDevices(1);
        loadJobs(1);
    }, []);

    async function loadDevices(page = 1) {
        setDevicesLoading(true);
        setError('');
        try {
            const response = await window.axios.get('/manage/api/device-runtime', {
                params: {
                    ...deviceFilters,
                    runtime_disabled: deviceFilters.runtime_disabled === '' ? undefined : deviceFilters.runtime_disabled,
                    page,
                },
            });
            setDevicesPayload(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '設備執行狀態載入失敗。'));
        } finally {
            setDevicesLoading(false);
        }
    }

    async function loadJobs(page = 1) {
        setJobsLoading(true);
        setError('');
        try {
            const response = await window.axios.get('/manage/api/button-jobs', {
                params: { ...jobFilters, page },
            });
            setJobsPayload(response.data.data);
        } catch (caught) {
            setError(errorMessage(caught, '按鈕任務載入失敗。'));
        } finally {
            setJobsLoading(false);
        }
    }

    async function runtimeAction(device, action) {
        setMessage('');
        setError('');
        const labels = {
            disable: '停用 runtime',
            enable: '啟用 runtime',
            'revoke-tokens': '撤銷 JWT',
        };

        if (!window.confirm(`確定要${labels[action]}：${device.serial_number}？`)) return;

        try {
            const response = await window.axios.post(`/manage/api/device-runtime/devices/${encodeURIComponent(device.serial_number)}/${action}`);
            setMessage(response.data.message);
            await loadDevices(devicesPayload?.pagination?.current_page ?? 1);
        } catch (caught) {
            setError(errorMessage(caught));
        }
    }

    async function cancelJob(job) {
        setMessage('');
        setError('');
        if (!window.confirm(`確定要取消任務 ${job.public_id}？`)) return;

        try {
            const response = await window.axios.post(`/manage/api/button-jobs/${encodeURIComponent(job.public_id)}/cancel`);
            setMessage(response.data.message);
            await Promise.all([
                loadJobs(jobsPayload?.pagination?.current_page ?? 1),
                loadDevices(devicesPayload?.pagination?.current_page ?? 1),
            ]);
        } catch (caught) {
            setError(errorMessage(caught));
        }
    }

    const devices = devicesPayload?.items ?? [];
    const jobs = jobsPayload?.items ?? [];

    return (
        <>
            <Head title="設備執行" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Device Runtime</p>
                    <h1>設備執行</h1>
                </section>

                {message && <div className="notice success">{message}</div>}
                {error && <div className="notice error">{error}</div>}

                <section className="panel">
                    <div className="panel-heading">
                        <h2>設備 runtime</h2>
                        {devicesPayload && <span className="status-pill">{devicesPayload.pagination.total} 台</span>}
                    </div>
                    <form className="manage-audit-filters" onSubmit={(event) => { event.preventDefault(); loadDevices(1); }}>
                        <label>搜尋 <input value={deviceFilters.search} onChange={(event) => setDeviceFilters({ ...deviceFilters, search: event.target.value })} placeholder="序號、名稱、產品或房間" /></label>
                        <label>狀態 <input value={deviceFilters.runner_status} onChange={(event) => setDeviceFilters({ ...deviceFilters, runner_status: event.target.value })} placeholder="idle / running / disabled" /></label>
                        <label>
                            Runtime
                            <select value={deviceFilters.runtime_disabled} onChange={(event) => setDeviceFilters({ ...deviceFilters, runtime_disabled: event.target.value })}>
                                <option value="">全部</option>
                                <option value="0">可用</option>
                                <option value="1">停用</option>
                            </select>
                        </label>
                        <button type="submit" disabled={devicesLoading}>查詢</button>
                    </form>

                    {devicesLoading && <p className="muted">載入中...</p>}
                    {!devicesLoading && devices.length === 0 && <p className="muted">沒有符合條件的設備</p>}
                    {devices.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>設備</th><th>產品</th><th>房間</th><th>Runtime</th><th>最後活動</th><th>Token</th><th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {devices.map((device) => (
                                        <tr key={device.serial_number}>
                                            <td><Link className="inline-link account-code" href={`/manage/devices/${encodeURIComponent(device.serial_number)}`}>{device.serial_number}</Link><br />{device.name ?? '-'}</td>
                                            <td>{device.product ? `${device.product.model_number} · ${device.product.name}` : '-'}</td>
                                            <td>{device.room ? `${device.room.name} (${device.room.public_id})` : '-'}</td>
                                            <td><StatusPill value={device.runtime.runner_disabled_at ? 'disabled' : device.runtime.runner_status} /></td>
                                            <td>{formatDate(device.runtime.runner_last_seen_at)}</td>
                                            <td>{device.tokens.active_token_count} active<br />access 到期：{formatDate(device.tokens.current_access_expires_at)}</td>
                                            <td>
                                                {canManageRuntime ? (
                                                    <div className="table-actions">
                                                        {device.runtime.runner_disabled_at
                                                            ? <button type="button" className="button-ghost" onClick={() => runtimeAction(device, 'enable')}>啟用</button>
                                                            : <button type="button" className="button-danger" onClick={() => runtimeAction(device, 'disable')}>停用</button>}
                                                        <button type="button" className="button-ghost" onClick={() => runtimeAction(device, 'revoke-tokens')}>撤銷 JWT</button>
                                                    </div>
                                                ) : '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination pagination={devicesPayload?.pagination} loading={devicesLoading} onPage={loadDevices} />
                </section>

                <section className="panel">
                    <div className="panel-heading">
                        <h2>按鈕任務</h2>
                        {jobsPayload && <span className="status-pill">{jobsPayload.pagination.total} 筆</span>}
                    </div>
                    <form className="manage-audit-filters" onSubmit={(event) => { event.preventDefault(); loadJobs(1); }}>
                        <label>搜尋 <input value={jobFilters.search} onChange={(event) => setJobFilters({ ...jobFilters, search: event.target.value })} placeholder="任務、request、設備或使用者" /></label>
                        <label>設備序號 <input value={jobFilters.device_serial_number} onChange={(event) => setJobFilters({ ...jobFilters, device_serial_number: event.target.value })} /></label>
                        <label>
                            狀態
                            <select value={jobFilters.status} onChange={(event) => setJobFilters({ ...jobFilters, status: event.target.value })}>
                                <option value="">全部</option>
                                {jobStatuses.map((status) => <option value={status} key={status}>{status}</option>)}
                            </select>
                        </label>
                        <button type="submit" disabled={jobsLoading}>查詢</button>
                    </form>

                    {jobsLoading && <p className="muted">載入中...</p>}
                    {!jobsLoading && jobs.length === 0 && <p className="muted">沒有符合條件的任務</p>}
                    {jobs.length > 0 && (
                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>任務</th><th>狀態</th><th>設備</th><th>功能</th><th>使用者</th><th>時間</th><th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {jobs.map((job) => (
                                        <tr key={job.public_id}>
                                            <td className="account-code">{job.public_id}</td>
                                            <td><StatusPill value={job.status} /> {job.progress}%</td>
                                            <td>{job.device.name ?? job.device.serial_number}<br /><span className="account-code">{job.device.serial_number}</span></td>
                                            <td>{job.function.description}<br /><span className="account-code">{job.function.code}</span></td>
                                            <td>{job.user.display_name}<br /><span className="account-code">{job.user.public_id}</span></td>
                                            <td>建立：{formatDate(job.created_at)}<br />完成：{formatDate(job.finished_at)}</td>
                                            <td>
                                                {canCancelJobs && ['queued', 'running'].includes(job.status)
                                                    ? <button type="button" className="button-danger" onClick={() => cancelJob(job)}>取消</button>
                                                    : '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination pagination={jobsPayload?.pagination} loading={jobsLoading} onPage={loadJobs} />
                </section>
            </ManageLayout>
        </>
    );
}

function StatusPill({ value }) {
    return <span className="status-pill">{value ?? '-'}</span>;
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
