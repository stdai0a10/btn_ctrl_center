import { Head } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronDown, Circle, Info, Menu, Plus, Power, Save, Square, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';
import { errorMessage } from '../../lib/http';

const iconMap = { power: Power, square: Square, circle: Circle };
const runningStatuses = ['queued', 'running'];
const jobStatusLabels = {
    queued: '等待設備取得任務',
    running: '設備執行中',
    succeeded: '執行成功',
    failed: '執行失敗',
    device_offline: '設備已離線',
    timed_out: '任務已逾時',
    unauthorized: '無權限',
    canceled: '已取消',
};
const emptyButton = {
    device_serial_number: '',
    product_function_code: '',
    position: 0,
    shape: 'rounded_square',
    background_color: '#2563EB',
    content_type: 'icon',
    icon_key: 'power',
    label: '',
    foreground_color: '#FFFFFF',
};

export default function ButtonsIndex() {
    const tabListRef = useRef(null);
    const [pages, setPages] = useState([]);
    const [activePageId, setActivePageId] = useState(null);
    const [targets, setTargets] = useState([]);
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(null);
    const [newPageName, setNewPageName] = useState('');
    const [newButton, setNewButton] = useState(emptyButton);
    const [job, setJob] = useState(null);
    const [frontEndTimedOutJobId, setFrontEndTimedOutJobId] = useState(null);
    const [infoButton, setInfoButton] = useState(null);
    const [pagePickerOpen, setPagePickerOpen] = useState(false);
    const [pageMenuOpen, setPageMenuOpen] = useState(false);
    const [createPageOpen, setCreatePageOpen] = useState(false);
    const [orderingPageIds, setOrderingPageIds] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);

    const activePage = pages.find((page) => page.public_id === activePageId) ?? pages[0] ?? null;
    const visiblePage = editing && draft ? draft : activePage;
    const jobIsRunning = job && runningStatuses.includes(job.status);
    const jobTimedOut = jobIsRunning && frontEndTimedOutJobId === job.public_id;
    const actionLocked = jobIsRunning && !jobTimedOut;

    useEffect(() => {
        reload();
        loadCurrentJob();
    }, []);

    useEffect(() => {
        if (!jobIsRunning) return undefined;

        const timer = window.setInterval(loadCurrentJob, 2000);
        return () => window.clearInterval(timer);
    }, [job?.public_id, job?.status, jobTimedOut]);

    useEffect(() => {
        if (!jobIsRunning) {
            setFrontEndTimedOutJobId(null);
            return undefined;
        }

        if (!job.expires_at) return undefined;

        const expiresAt = Date.parse(job.expires_at);
        if (!Number.isFinite(expiresAt)) return undefined;

        if (Date.now() >= expiresAt) {
            setFrontEndTimedOutJobId(job.public_id);
            return undefined;
        }

        setFrontEndTimedOutJobId(null);
        const timer = window.setTimeout(() => setFrontEndTimedOutJobId(job.public_id), expiresAt - Date.now());
        return () => window.clearTimeout(timer);
    }, [job?.public_id, job?.status, job?.expires_at]);

    useEffect(() => {
        if (!activePageId) return undefined;

        const frame = window.requestAnimationFrame(() => {
            const activeTab = [...(tabListRef.current?.children ?? [])]
                .find((element) => element.dataset.pageId === activePageId);
            activeTab?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        });

        return () => window.cancelAnimationFrame(frame);
    }, [activePageId, pages.length]);

    async function reload() {
        setLoading(true);
        const [pagesResponse, targetsResponse] = await Promise.all([
            window.axios.get('/api/button-pages'),
            window.axios.get('/api/buttons/selectable-targets'),
        ]);
        const nextPages = pagesResponse.data.data;
        setPages(nextPages);
        setTargets(targetsResponse.data.data);
        setActivePageId((current) => current ?? nextPages[0]?.public_id ?? null);
        setLoading(false);
    }

    async function loadCurrentJob() {
        const response = await window.axios.get('/api/button-actions/current');
        const current = response.data.data?.job ?? null;
        if (current) {
            setJob(current);
            return;
        }

        if (job?.public_id && ['queued', 'running'].includes(job.status)) {
            const jobResponse = await window.axios.get(`/api/button-actions/${job.public_id}`);
            setJob(jobResponse.data.data.job);
        }
    }

    async function run(task, success) {
        setProcessing(true);
        setMessage('');
        setError('');
        try {
            const response = await task();
            setMessage(response?.data?.message ?? success);
            return response;
        } catch (caught) {
            setError(errorMessage(caught));
            throw caught;
        } finally {
            setProcessing(false);
        }
    }

    async function createPage(event) {
        event.preventDefault();
        const response = await run(
            () => window.axios.post('/api/button-pages', { name: newPageName, layout_columns: 3 }),
            '按鈕分頁已建立。',
        );
        setNewPageName('');
        await reload();
        setActivePageId(response.data.data.public_id);
        setCreatePageOpen(false);
        setPageMenuOpen(false);
    }

    async function deletePage(page) {
        if (!window.confirm(`確定要刪除「${page.name}」？此操作無法復原。`)) return;

        await run(() => window.axios.delete(`/api/button-pages/${page.public_id}`), '按鈕分頁已刪除。');
        setEditing(false);
        setDraft(null);
        await reload();
    }

    async function movePage(page, direction) {
        const index = pages.findIndex((item) => item.public_id === page.public_id);
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= pages.length) return;

        const ids = pages.map((item) => item.public_id);
        [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
        await run(() => window.axios.put('/api/button-pages/order', { button_page_public_ids: ids }), '按鈕分頁順序已更新。');
        await reload();
    }

    function startOrdering() {
        if (pages.length === 0) return;
        setOrderingPageIds(pages.map((page) => page.public_id));
        setPageMenuOpen(false);
        setPagePickerOpen(false);
    }

    function moveOrderingPage(pageId, direction) {
        setOrderingPageIds((current) => {
            if (!current) return current;

            const index = current.indexOf(pageId);
            const nextIndex = index + direction;
            if (index < 0 || nextIndex < 0 || nextIndex >= current.length) return current;

            const next = [...current];
            [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
            return next;
        });
    }

    async function savePageOrder() {
        if (!orderingPageIds) return;

        await run(() => window.axios.put('/api/button-pages/order', { button_page_public_ids: orderingPageIds }), '按鈕分頁順序已更新。');
        setOrderingPageIds(null);
        await reload();
    }

    function startEditing() {
        if (!activePage) return;
        setDraft(JSON.parse(JSON.stringify(activePage)));
        setEditing(true);
        setPageMenuOpen(false);
        setPagePickerOpen(false);
        setCreatePageOpen(false);
        setMessage('');
        setError('');
    }

    function cancelEditing() {
        setDraft(null);
        setEditing(false);
    }

    async function saveDraft() {
        const payload = {
            name: draft.name,
            layout_columns: draft.layout_columns,
            buttons: draft.buttons.map((button) => ({
                public_id: button.public_id,
                device_serial_number: button.device.serial_number,
                product_function_code: button.function.code,
                position: button.position,
                shape: button.shape,
                background_color: button.background_color,
                content_type: button.content_type,
                icon_key: button.icon_key,
                label: button.label,
                foreground_color: button.foreground_color,
            })),
        };

        await run(() => window.axios.put(`/api/button-pages/${draft.public_id}/layout`, payload), '按鈕分頁已儲存。');
        setEditing(false);
        setDraft(null);
        await reload();
    }

    function changeColumns(columns) {
        const sorted = [...draft.buttons].sort((a, b) => a.position - b.position);
        setDraft({
            ...draft,
            layout_columns: columns,
            buttons: sorted.map((button, index) => ({ ...button, position: index })),
        });
    }

    function addButton(event) {
        event.preventDefault();
        const target = targets.find((item) => item.device.serial_number === newButton.device_serial_number);
        const productFunction = target?.functions.find((item) => item.code === newButton.product_function_code);
        if (!target || !productFunction) {
            setError('請選擇設備與功能。');
            return;
        }

        const nextPosition = draft.buttons.length === 0
            ? 0
            : Math.max(...draft.buttons.map((button) => button.position)) + 1;

        setDraft({
            ...draft,
            buttons: [
                ...draft.buttons,
                {
                    ...newButton,
                    position: nextPosition,
                    public_id: null,
                    device: target.device,
                    function: productFunction,
                    availability: { available: true, reason: null, message: null },
                },
            ],
        });
        setNewButton({ ...emptyButton, position: nextPosition + 1 });
    }

    function updateDraftButton(index, updates) {
        setDraft({
            ...draft,
            buttons: draft.buttons.map((button, current) => current === index ? { ...button, ...updates } : button),
        });
    }

    function removeDraftButton(index) {
        setDraft({
            ...draft,
            buttons: draft.buttons.filter((_, current) => current !== index),
        });
    }

    async function trigger(button) {
        if (actionLocked) {
            setError('任務執行中，請等待完成或逾時後再操作其他按鈕。');
            return;
        }

        const requestId = window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;
        const response = await run(
            () => window.axios.post('/api/button-actions', {
                button_public_id: button.public_id,
                request_id: requestId,
            }),
            '按鈕任務已建立。',
        );
        setJob(response.data.data.job);
    }

    const groupedFunctions = useMemo(() => {
        const target = targets.find((item) => item.device.serial_number === newButton.device_serial_number);
        return target?.functions ?? [];
    }, [targets, newButton.device_serial_number]);

    const orderedPages = orderingPageIds
        ? orderingPageIds.map((id) => pages.find((page) => page.public_id === id)).filter(Boolean)
        : [];
    const visibleTabCount = Math.min(Math.max(pages.length, 1), 4);

    return (
        <>
            <Head title="按鈕" />
            <AppLayout contentClassName="buttons-main">
                {message && <div className="notice success">{message}</div>}
                {error && <div className="notice error">{error}</div>}
                {job && <JobPanel job={job} timedOut={jobTimedOut} onClose={() => setJob(null)} />}
                {infoButton && <ButtonInfoDialog button={infoButton} onClose={() => setInfoButton(null)} />}

                {loading && <p className="muted">載入中...</p>}
                {!loading && pages.length === 0 && (
                    <section className="panel empty-buttons-panel">
                        <h2>目前沒有分頁</h2>
                        <p className="muted">輸入分頁名稱後新增第一個按鈕分頁。</p>
                        <form className="button-page-create" onSubmit={createPage}>
                            <input
                                value={newPageName}
                                onChange={(event) => setNewPageName(event.target.value)}
                                maxLength={100}
                                placeholder="新增分頁名稱"
                                required
                            />
                            <button type="submit" disabled={processing || pages.length >= 50}><Plus size={17} />新增分頁</button>
                        </form>
                    </section>
                )}

                {visiblePage && (
                    <section className="button-page-shell">
                        <div className="button-page-controlbar">
                            <div className="button-page-picker-wrap">
                                <button
                                    type="button"
                                    className="button-page-icon-control"
                                    aria-label="選擇分頁"
                                    aria-expanded={pagePickerOpen}
                                    disabled={editing}
                                    onClick={() => {
                                        setPagePickerOpen((open) => !open);
                                        setPageMenuOpen(false);
                                        setCreatePageOpen(false);
                                    }}
                                >
                                    <ChevronDown size={22} />
                                </button>
                                {pagePickerOpen && (
                                    <div className="button-page-popover button-page-picker" role="menu">
                                        {pages.map((page) => (
                                            <button
                                                type="button"
                                                role="menuitem"
                                                className={activePage?.public_id === page.public_id ? 'is-active' : ''}
                                                onClick={() => {
                                                    setActivePageId(page.public_id);
                                                    setPagePickerOpen(false);
                                                }}
                                                key={page.public_id}
                                            >
                                                {page.name}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="button-page-control-actions">
                                <div className="button-page-picker-wrap">
                                    <button
                                        type="button"
                                        className="button-page-icon-control"
                                        aria-label="分頁選單"
                                        aria-expanded={pageMenuOpen}
                                        disabled={editing}
                                        onClick={() => {
                                            setPageMenuOpen((open) => !open);
                                            setPagePickerOpen(false);
                                            setCreatePageOpen(false);
                                        }}
                                    >
                                        <Menu size={25} />
                                    </button>
                                    {pageMenuOpen && (
                                        <div className="button-page-popover button-page-menu" role="menu">
                                            <button
                                                type="button"
                                                role="menuitem"
                                                onClick={() => {
                                                    setCreatePageOpen(true);
                                                    setPageMenuOpen(false);
                                                }}
                                            >
                                                新增分頁
                                            </button>
                                            <button type="button" role="menuitem" disabled={pages.length < 2} onClick={startOrdering}>排序分頁</button>
                                            <button type="button" role="menuitem" disabled={!activePage} onClick={startEditing}>編輯分頁</button>
                                            <button type="button" role="menuitem" className="is-danger" disabled={!activePage} onClick={() => deletePage(activePage)}>刪除分頁</button>
                                        </div>
                                    )}
                                    {createPageOpen && (
                                        <form className="button-page-popover button-page-create-popover" onSubmit={createPage}>
                                            <input
                                                value={newPageName}
                                                onChange={(event) => setNewPageName(event.target.value)}
                                                maxLength={100}
                                                placeholder="新增分頁名稱"
                                                required
                                            />
                                            <button type="submit" disabled={processing || pages.length >= 50}><Plus size={17} />新增</button>
                                        </form>
                                    )}
                                </div>
                            </div>
                        </div>

                        {pages.length > 0 && (
                            <div
                                className="button-page-tabs"
                                role="tablist"
                                aria-label="按鈕分頁"
                                ref={tabListRef}
                                style={{ '--button-visible-tabs': visibleTabCount }}
                            >
                                {pages.map((page) => (
                                    <button
                                        type="button"
                                        role="tab"
                                        data-page-id={page.public_id}
                                        aria-selected={activePage?.public_id === page.public_id}
                                        className={activePage?.public_id === page.public_id ? 'is-active' : ''}
                                        onClick={() => {
                                            if (!editing) setActivePageId(page.public_id);
                                        }}
                                        key={page.public_id}
                                    >
                                        {page.name}
                                    </button>
                                ))}
                            </div>
                        )}

                        {editing && (
                            <div className="button-edit-panel">
                                <div className="button-edit-toolbar">
                                    <label>
                                        分頁名稱
                                        <input value={draft.name} maxLength={100} onChange={(event) => setDraft({ ...draft, name: event.target.value })} />
                                    </label>
                                    <label>
                                        欄數
                                        <select value={draft.layout_columns} onChange={(event) => changeColumns(Number(event.target.value))}>
                                            {[2, 3, 4, 5].map((columns) => <option value={columns} key={columns}>{columns} 欄</option>)}
                                        </select>
                                    </label>
                                    <div className="button-edit-actions">
                                        <button type="button" className="button-ghost" onClick={cancelEditing}><X size={17} />取消</button>
                                        <button type="button" onClick={saveDraft} disabled={processing}><Save size={17} />儲存</button>
                                    </div>
                                </div>
                                <form className="button-builder" onSubmit={addButton}>
                                    <select value={newButton.device_serial_number} onChange={(event) => setNewButton({ ...newButton, device_serial_number: event.target.value, product_function_code: '' })} required>
                                        <option value="">選擇設備</option>
                                        {targets.map((target) => (
                                            <option value={target.device.serial_number} key={target.device.serial_number}>
                                                {target.device.name || target.device.serial_number} · {target.device.product?.model_number}
                                            </option>
                                        ))}
                                    </select>
                                    <select value={newButton.product_function_code} onChange={(event) => setNewButton({ ...newButton, product_function_code: event.target.value })} required>
                                        <option value="">選擇功能</option>
                                        {groupedFunctions.map((item) => <option value={item.code} key={item.code}>{item.description}</option>)}
                                    </select>
                                    <select value={newButton.shape} onChange={(event) => setNewButton({ ...newButton, shape: event.target.value })}>
                                        <option value="rounded_square">方形</option>
                                        <option value="circle">圓形</option>
                                    </select>
                                    <input type="color" value={newButton.background_color} onChange={(event) => setNewButton({ ...newButton, background_color: event.target.value })} title="背景色" />
                                    <input value={newButton.label} onChange={(event) => setNewButton({ ...newButton, label: event.target.value, content_type: event.target.value ? 'text' : 'icon' })} maxLength={100} placeholder="文字" />
                                    <button type="submit"><Plus size={17} />加入按鈕</button>
                                </form>
                            </div>
                        )}

                        <div className="button-grid" style={{ '--button-columns': visiblePage.layout_columns }}>
                            {[...visiblePage.buttons].sort((a, b) => a.position - b.position).map((button, index) => (
                                <ButtonTile
                                    button={button}
                                    editing={editing}
                                    actionLocked={actionLocked}
                                    processing={processing}
                                    onTrigger={() => trigger(button)}
                                    onInfo={() => setInfoButton(button)}
                                    onUpdate={(updates) => updateDraftButton(index, updates)}
                                    onRemove={() => removeDraftButton(index)}
                                    key={button.public_id ?? `${button.device.serial_number}-${button.position}-${index}`}
                                />
                            ))}
                        </div>
                        {visiblePage.buttons.length === 0 && <p className="muted">這個分頁還沒有按鈕。</p>}
                        {orderingPageIds && (
                            <div className="button-page-order-backdrop" role="presentation">
                                <section className="button-page-order-panel" role="dialog" aria-modal="true" aria-labelledby="button-page-order-title">
                                    <div className="button-page-order-heading">
                                        <button type="button" className="button-ghost" onClick={() => setOrderingPageIds(null)}>取消</button>
                                        <h2 id="button-page-order-title">排序分頁</h2>
                                        <button type="button" onClick={savePageOrder} disabled={processing}>儲存</button>
                                    </div>
                                    <div className="button-page-order-list">
                                        {orderedPages.map((page, index) => (
                                            <div className="button-page-order-row" key={page.public_id}>
                                                <span>{page.name}</span>
                                                <div>
                                                    <button type="button" className="icon-button" aria-label="上移" disabled={index === 0} onClick={() => moveOrderingPage(page.public_id, -1)}>
                                                        <ArrowUp size={20} />
                                                    </button>
                                                    <button type="button" className="icon-button" aria-label="下移" disabled={index === orderedPages.length - 1} onClick={() => moveOrderingPage(page.public_id, 1)}>
                                                        <ArrowDown size={20} />
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            </div>
                        )}
                    </section>
                )}
            </AppLayout>
        </>
    );
}

function ButtonTile({ button, editing, actionLocked, processing, onTrigger, onInfo, onUpdate, onRemove }) {
    const Icon = iconMap[button.icon_key] ?? Power;
    const unavailable = !button.availability?.available;
    const disabledMessage = actionLocked
        ? '任務執行中，請等待完成或逾時。'
        : button.availability?.message;
    const disabled = !editing && (unavailable || actionLocked || processing);

    return (
        <article className={`control-button-tile is-${button.shape}`}>
            <button
                type="button"
                className="control-button-face"
                style={{ backgroundColor: button.background_color, color: button.foreground_color }}
                disabled={disabled}
                onClick={() => !editing && onTrigger()}
                title={disabledMessage ?? button.function?.description}
            >
                {button.content_type === 'text' && button.label ? <span>{button.label}</span> : <Icon size={28} />}
            </button>
            <div className="control-button-meta">
                <strong>{button.label || button.function?.description || button.icon_key}</strong>
                <span>{button.device?.name || button.device?.serial_number}</span>
                {disabledMessage && <small>{disabledMessage}</small>}
                {!editing && (
                    <button type="button" className="icon-button" title="按鈕資訊" onClick={onInfo}>
                        <Info size={16} />
                    </button>
                )}
            </div>
            {editing && (
                <div className="button-edit-fields">
                    <input type="number" min="0" value={button.position} onChange={(event) => onUpdate({ position: Number(event.target.value) })} />
                    <input type="color" value={button.background_color} onChange={(event) => onUpdate({ background_color: event.target.value })} />
                    <input type="color" value={button.foreground_color} onChange={(event) => onUpdate({ foreground_color: event.target.value })} />
                    <button type="button" className="button-danger" onClick={onRemove}><Trash2 size={16} />刪除</button>
                </div>
            )}
        </article>
    );
}

function JobPanel({ job, timedOut, onClose }) {
    const isRunning = runningStatuses.includes(job.status);
    const canClose = !isRunning || timedOut;
    const label = timedOut ? '前端等待逾時' : jobStatusLabels[job.status] ?? job.status;
    const panelType = timedOut ? 'error' : isRunning ? 'info' : job.status === 'succeeded' ? 'success' : 'error';

    return (
        <section className={`notice ${panelType} job-panel`}>
            <Info size={18} />
            <div>
                <strong>任務狀態：{label}</strong>
                <span>{job.progress ?? 0}% {job.progress_message ?? ''}</span>
                {timedOut && <span>等待已解除，設備仍可稍後回報結果。</span>}
                {job.error_message && <span>{job.error_message}</span>}
            </div>
            {canClose && <button type="button" className="button-ghost" onClick={onClose}>關閉</button>}
        </section>
    );
}

function ButtonInfoDialog({ button, onClose }) {
    return (
        <div className="app-dialog-backdrop" role="presentation" onMouseDown={onClose}>
            <section className="app-dialog button-info-dialog" role="dialog" aria-modal="true" aria-labelledby="button-info-title" onMouseDown={(event) => event.stopPropagation()}>
                <div className="app-dialog-heading">
                    <h2 id="button-info-title">{button.label || button.function?.description || '按鈕資訊'}</h2>
                    <button type="button" className="app-dialog-close" onClick={onClose} aria-label="關閉"><X size={18} /></button>
                </div>
                <dl className="button-info-list">
                    <div>
                        <dt>設備</dt>
                        <dd>{button.device?.name || button.device?.serial_number}</dd>
                    </div>
                    <div>
                        <dt>序號</dt>
                        <dd>{button.device?.serial_number}</dd>
                    </div>
                    <div>
                        <dt>房間</dt>
                        <dd>{button.device?.room?.name ?? '無'}</dd>
                    </div>
                    <div>
                        <dt>產品</dt>
                        <dd>{button.device?.product?.name ?? button.device?.product?.model_number ?? '無'}</dd>
                    </div>
                    <div>
                        <dt>功能</dt>
                        <dd>{button.function?.description} ({button.function?.code})</dd>
                    </div>
                    <div>
                        <dt>狀態</dt>
                        <dd>{button.availability?.available ? '可操作' : button.availability?.message}</dd>
                    </div>
                </dl>
            </section>
        </div>
    );
}
