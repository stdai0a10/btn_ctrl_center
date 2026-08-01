import { Head, Link } from '@inertiajs/react';
import ManageLayout from '../../../layouts/ManageLayout';

export default function AuditIndex() {
    return (
        <>
            <Head title="審計資料" />
            <ManageLayout>
                <section className="page-header">
                    <p className="eyebrow">Management Audit</p>
                    <h1>審計資料</h1>
                </section>
                <section className="grid-2">
                    <Link className="panel manage-link-item" href="/manage/audit/login-failures">
                        <h2>登入失敗紀錄</h2>
                        <p className="muted">查詢帳號、IP、失敗原因與暫時鎖定紀錄。</p>
                    </Link>
                    <Link className="panel manage-link-item" href="/manage/audit/manage-actions">
                        <h2>管理後台操作紀錄</h2>
                        <p className="muted">查詢操作者、固定 action、目標與操作 metadata。</p>
                    </Link>
                </section>
            </ManageLayout>
        </>
    );
}
