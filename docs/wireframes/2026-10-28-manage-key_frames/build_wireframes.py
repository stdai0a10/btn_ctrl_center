"""Regenerate the static documentation frames; standard library only."""
from pathlib import Path
from html import escape as esc
import json

ROOT = Path(__file__).resolve().parent
REPO = ROOT.parents[2]
FRAMES = []
SERIAL = 'DEMO-DEVICE-001'
DATE = '2026/10/28 10:30'
ROUTES = {'Manage/Login': '/manage/login',
 'Manage/Index': '/manage',
 'Manage/Users/Index': '/manage/users',
 'Manage/Users/Show': '/manage/users/{user_public_id}',
 'Manage/Rooms/Index': '/manage/rooms',
 'Manage/Rooms/Show': '/manage/rooms/{room_public_id}',
 'Manage/Products/Index': '/manage/products',
 'Manage/Products/Create': '/manage/products/create',
 'Manage/Products/Show': '/manage/products/{product_public_id}',
 'Manage/Devices/Index': '/manage/devices',
 'Manage/Devices/Create': '/manage/devices/create',
 'Manage/Devices/Show': '/manage/devices/{serial_number}',
 'Manage/DeviceRuntime/Index': '/manage/device-runtime',
 'Manage/ServiceManagers/Index': '/manage/service-managers',
 'Manage/ServiceManagers/Show': '/manage/service-managers/{user_public_id}',
 'Manage/Audit/Index': '/manage/audit',
 'Manage/Audit/LoginFailures': '/manage/audit/login-failures',
 'Manage/Audit/ManageActions': '/manage/audit/manage-actions'}


def filename(source, state='default'):
    return source.lower().replace('/', '-') + ('' if state == 'default' else '--' + state) + '.html'


def link(text, source, state='default', style='button', extra=''):
    return f'<a class="{style}" href="{filename(source, state)}" {extra}>{text}</a>'


def actions(*items):
    return '<div class="actions">' + ''.join(items) + '</div>'


def notice(text, error=False):
    return f'<div class="notice{" error" if error else ""}">{text}</div>'


def field(label, value='', kind='text', hint='', disabled=False):
    return f'<label>{label}<input type="{kind}" value="{esc(value, quote=True)}" {"disabled" if disabled else ""} autocomplete="off">{f"<small>{hint}</small>" if hint else ""}</label>'


def select(label, options):
    return '<label>' + label + '<select>' + ''.join(f'<option>{x}</option>' for x in options.split('|')) + '</select></label>'


def check(label, checked=False):
    return f'<label><input type="checkbox" {"checked" if checked else ""}> {label}</label>'


def form(body, submit='儲存', source=None, state='default', filters=False):
    next_attr = f' data-next="{filename(source, state)}"' if source else ''
    action = f' action="{filename(source, state)}"' if source else ''
    return f'<form class="{"filters" if filters else "stack"}"{next_attr}{action}>{body}<button class="primary" type="submit">{submit}</button></form>'


def panel(title, body, extra=''):
    return f'<section class="panel"><div class="panel-head"><h2>{title}</h2>{extra}</div>{body}</section>'


def details(items):
    return '<dl class="detail">' + ''.join(f'<div><dt>{k}</dt><dd>{v}</dd></div>' for k, v in items) + '</dl>'


def row(title, sub='', controls=''):
    return f'<article class="list-row"><div><div class="row-title">{title}</div><p class="muted">{sub}</p></div>{controls}</article>'


def table(headers, rows):
    return '<div class="table-wrap"><table><thead><tr>' + ''.join(f'<th scope="col">{h}</th>' for h in headers.split('|')) + '</tr></thead><tbody>' + ''.join('<tr>' + ''.join(f'<td>{c}</td>' for c in r) + '</tr>' for r in rows) + '</tbody></table></div>'


def paging():
    return '<div class="pagination"><button disabled>上一頁</button><span>1 / 1</span><button disabled>下一頁</button></div>'


def tabs(items, active):
    return '<nav class="tabs" aria-label="頁面區段">' + ''.join(link(label, src, state, '', 'aria-current="page"' if state == active else '') for label, src, state in items) + '</nav>'


def add(source, title, body, state='default', label='預設', note='', modal=None, layout=None):
    group = '管理後台' if source.startswith('Manage/') else '一般使用者'
    if source.startswith('Auth/'):
        group = '登入與驗證'
    if layout is None:
        layout = 'auth' if source.startswith('Auth/') or source == 'Manage/Login' else 'manage' if source.startswith('Manage/') else 'app'
    FRAMES.append(dict(source=source, title=title, body=body, state=state, label=label,
                       note=note, modal=modal, layout=layout, group=group,
                       route=ROUTES[source], file=filename(source, state)))


def dialog(title, body, back_source, back_state='default'):
    return dict(title=title, body=body, close=filename(back_source, back_state))


def empty(text):
    return f'<div class="empty"><p>{text}</p></div>'


IS_MANAGE = True
CATALOG_TITLE = '管理後台'

login_fields = field('EMAIL', 'demo@example.test', 'email') + field('密碼', 'wireframe-only', 'password')
# Management lists use the exact filter fields and table columns from JSX.
manage_login = '<p>僅具管理權限的帳號可登入後台。</p>' + form(login_fields, '登入管理後台', 'Manage/Index')
add('Manage/Login', '管理後台登入', manage_login)
add('Manage/Login', '管理後台登入', notice('帳密錯誤或無權限。', True) + manage_login, 'error', '管理登入失敗')
stats = '<div class="stat-grid">' + ''.join(panel(label, f'<strong class="stat">{number}</strong>') for label, number in [('使用者總數', 128), ('房間總數', 24), ('今日新增使用者', 3), ('今日新增房間', 1)]) + '</div>'
admin_info = details([('公開 ID', 'ADMIN-DEMO-01'), ('顯示名稱', '示範管理員'), ('Email', 'admin@example.test'), ('管理角色', 'system_admin'), ('最近登入管理後台', DATE)]) + '<p><span class="badge">manage.access</span> <span class="badge">audit.access</span></p>'
quick = ''.join(row(link(label, source, style=''), text) for label, source, text in [('使用者一覽', 'Manage/Users/Index', '查看使用者與加入的房間'), ('房間一覽', 'Manage/Rooms/Index', '查看房間與成員'), ('設備執行', 'Manage/DeviceRuntime/Index', 'Runtime、JWT 與按鈕任務'), ('服務管理員', 'Manage/ServiceManagers/Index', '授予與撤銷管理身分'), ('審計資料', 'Manage/Audit/Index', '管理登入失敗與操作紀錄')])
dashboard = stats + '<div class="grid-2">' + panel('快速入口', quick) + panel('目前管理員', admin_info) + '</div>'
add('Manage/Index', '管理後台', dashboard)
add('Manage/Index', '管理後台', dashboard, 'service-manager', '服務管理員權限', '服務管理員不具授權服務管理員與審計權限，側欄與快速入口依權限隱藏。')
add('Manage/Index', '管理後台', dashboard, 'collapsed', '側欄收合')
search = field('搜尋', '', hint='公開 ID、Email、顯示名稱')
status_filter = select('狀態', '全部|正常|待啟用|停用|刪除')
user_headers = '使用者公開 ID|顯示名稱|Email|LINE|建立時間|最近登入|狀態'
user_row = [link('USER-DEMO-01', 'Manage/Users/Show', style=''), '示範使用者', 'demo@example.test', '已綁定', DATE, DATE, '正常']
add('Manage/Users/Index', '使用者一覽', panel('篩選', form(search + status_filter, '查詢', filters=True)) + panel('使用者', table(user_headers, [user_row]) + paging(), '<span class="badge">1 筆</span>'))
user_detail = '<div class="grid-2">' + panel('基本資料', details([('使用者公開 ID', 'USER-DEMO-01'), ('顯示名稱', '示範使用者'), ('Email', 'demo@example.test'), ('LINE 綁定', '已綁定'), ('建立時間', DATE), ('最近登入', DATE)])) + panel('權限', '<span class="badge">user</span><p>沒有管理權限</p>') + '</div>' + panel('已加入的房間', table('房間公開 ID|房間名稱|房間內身分|加入時間', [[link('ROOM-DEMO-01', 'Manage/Rooms/Show', style=''), '客廳', '房主', DATE]]))
add('Manage/Users/Show', '示範使用者', link('返回使用者一覽', 'Manage/Users/Index') + user_detail)
room_headers = '房間公開 ID|房間名稱|建立者|建立時間|成員數量|房主數量|狀態'
add('Manage/Rooms/Index', '房間一覽', panel('篩選', form(field('搜尋', '', hint='房間 ID、房間名稱、建立者') + select('狀態', '全部|正常|刪除'), '查詢', filters=True)) + panel('房間', table(room_headers, [[link('ROOM-DEMO-01', 'Manage/Rooms/Show', style=''), '客廳', '示範使用者', DATE, '2', '1', '正常']]) + paging()))
room_details = details([('房間公開 ID', 'ROOM-DEMO-01'), ('房間名稱', '客廳'), ('建立者', '示範使用者'), ('建立時間', DATE), ('成員數量', '2'), ('房主數量', '1')])
device_room_headers = '設備序號|產品|設備名稱|鎖定狀態|啟用狀態|建立時間'
device_room_row = [link(SERIAL, 'Manage/Devices/Show', style=''), 'BTN-DEMO', '客廳開關', '未上鎖', '已啟用', DATE]
room_manage = '<div class="grid-2">' + panel('基本資料', room_details) + panel('建立者', details([('使用者公開 ID', link('USER-DEMO-01', 'Manage/Users/Show', style='')), ('顯示名稱', '示範使用者'), ('Email', 'demo@example.test')])) + '</div>' + panel('房間內的使用者', table('使用者公開 ID|顯示名稱|Email|房間內身分|加入時間', [[link('USER-DEMO-01', 'Manage/Users/Show', style=''), '示範使用者', 'demo@example.test', '房主', DATE], ['USER-DEMO-02', '示範住戶', 'resident@example.test', '住戶', DATE]])) + panel('房間內的設備', table(device_room_headers, [device_room_row]))
add('Manage/Rooms/Show', '客廳', link('返回房間一覽', 'Manage/Rooms/Index') + room_manage)
product_headers = '產品 ID|產品型號|產品名稱|功能數|設備數|建立時間|更新時間'
product_row = [link('PRODUCT-DEMO-01', 'Manage/Products/Show', style=''), 'BTN-DEMO', '示範控制器', '2', '1', DATE, DATE]
add('Manage/Products/Index', '產品一覽', link('新增產品', 'Manage/Products/Create', style='button primary') + panel('篩選', form(field('搜尋', '', hint='產品 ID、型號或名稱'), '查詢', filters=True)) + panel('產品', table(product_headers, [product_row]) + paging()))
product_form = field('產品型號', 'BTN-DEMO') + field('產品名稱', '示範控制器')
add('Manage/Products/Create', '新增產品', link('返回產品一覽', 'Manage/Products/Index', style='') + panel('新增產品', form(product_form, '建立產品', 'Manage/Products/Show')))


def product_body(locked=False, menu=False):
    header = actions(link('返回產品一覽', 'Manage/Products/Index'), '' if locked else link('鎖定產品', 'Manage/Products/Show', 'lock-confirm'))
    info = details([('產品 ID', 'PRODUCT-DEMO-01'), ('產品型號', 'BTN-DEMO'), ('產品名稱', '示範控制器'), ('鎖定狀態', '已鎖定' if locked else '未鎖定'), ('功能數量', '2'), ('建立時間', DATE), ('更新時間', DATE)])
    edit = panel('產品已鎖定', '<p>此產品已鎖定，型號、名稱與產品功能不可再修改。</p>') if locked else panel('修改產品', form(product_form, '儲存產品'))
    function_rows = [['FUNC-DEMO-01', '切換電源', DATE] + ([] if locked else [link('⋮ 操作', 'Manage/Products/Show', 'function-menu')]), ['FUNC-DEMO-02', '關閉電源', DATE] + ([] if locked else [link('⋮ 操作', 'Manage/Products/Show', 'function-menu')])]
    functions = '<p>產品已鎖定，不能新增、修改或刪除產品功能。</p>' if locked else form(field('功能說明', '切換電源'), '新增功能')
    functions += table('功能代碼|功能說明|最近更新時間' + ('' if locked else '|操作'), function_rows)
    if menu:
        functions += '<nav class="popover" aria-label="產品功能操作">' + link('修改功能說明', 'Manage/Products/Show', 'edit-function', '') + link('刪除產品功能', 'Manage/Products/Show', 'delete-function', '') + '</nav>'
    return header + '<div class="grid-2">' + panel('產品資料', info) + edit + '</div>' + panel('產品功能', functions) + panel('關聯設備', table('設備序號|設備名稱|所在房間|鎖定|啟用|建立時間', [[link(SERIAL, 'Manage/Devices/Show', style=''), '客廳開關', link('客廳', 'Manage/Rooms/Show', style=''), '未上鎖', '已啟用', DATE]]) + paging())


add('Manage/Products/Show', 'BTN-DEMO', product_body())
add('Manage/Products/Show', 'BTN-DEMO', product_body(True), 'locked', '產品已鎖定')
add('Manage/Products/Show', 'BTN-DEMO', product_body(menu=True), 'function-menu', '產品功能操作選單')
for state, title, body in [
    ('edit-function', '修改功能說明', form(field('功能代碼', 'FUNC-DEMO-01', disabled=True) + field('功能說明', '切換電源'), '儲存', 'Manage/Products/Show')),
    ('delete-function', '刪除產品功能', '<p>確定要刪除產品功能「FUNC-DEMO-01」嗎？此操作會移除功能說明「切換電源」。</p>' + actions(link('取消', 'Manage/Products/Show'), link('確認刪除', 'Manage/Products/Show', style='button danger'))),
    ('lock-confirm', '鎖定產品', '<p>鎖定後將不能修改產品型號、名稱，也不能新增、修改或刪除產品功能。</p>' + actions(link('取消', 'Manage/Products/Show'), link('確認鎖定', 'Manage/Products/Show', 'locked', 'button danger'))),
]:
    add('Manage/Products/Show', 'BTN-DEMO', product_body(), state, title, modal=dialog(title, body, 'Manage/Products/Show'))
device_headers = '設備序號|產品|房間狀態|所在房間|設備名稱|鎖定|啟用|建立時間|更新時間'
device_filters = field('搜尋', '', hint='序號、設備名稱、產品或房間') + field('房間公開 ID') + select('房間狀態', '全部|已加入房間|未加入房間') + select('鎖定', '全部|已上鎖|未上鎖') + select('啟用', '全部|已啟用|已停用')
add('Manage/Devices/Index', '設備一覽', link('新增設備', 'Manage/Devices/Create', style='button primary') + panel('篩選', form(device_filters, '查詢', filters=True)) + panel('設備', table(device_headers, [[link(SERIAL, 'Manage/Devices/Show', style=''), 'BTN-DEMO', '已加入房間', '客廳', '客廳開關', '未上鎖', '已啟用', DATE, DATE]]) + paging()))
device_fields = field('產品型號', 'BTN-DEMO', hint='已選擇：BTN-DEMO · 示範控制器') + field('設備序號', SERIAL) + field('隱碼', 'DEMO-SECRET') + field('確認隱碼', 'DEMO-SECRET')
device_create = link('返回設備一覽', 'Manage/Devices/Index', style='') + panel('新增設備', form(device_fields, '建立設備', 'Manage/Devices/Show'))
add('Manage/Devices/Create', '新增設備', device_create)
suggestions = '<div class="popover">' + link('BTN-DEMO · 示範控制器', 'Manage/Devices/Create', style='') + link('BTN-DEMO-2 · 示範雙路控制器', 'Manage/Devices/Create', style='') + '</div>'
add('Manage/Devices/Create', '新增設備', device_create.replace('<small>已選擇：BTN-DEMO · 示範控制器</small>', suggestions), 'suggestions', '產品型號建議清單')
add('Manage/Devices/Create', '新增設備', device_create.replace('已選擇：BTN-DEMO · 示範控制器', '找不到相近產品；請從建議清單選擇產品。'), 'no-product', '產品搜尋無結果')
add('Manage/Devices/Create', '新增設備', notice('確認隱碼與隱碼不相符，請重新輸入。', True) + device_create, 'validation', '建立設備驗證失敗')


def device_detail(assigned=True):
    return link('返回設備一覽', 'Manage/Devices/Index') + '<div class="grid-2">' + panel('設備資料', details([('設備序號', SERIAL), ('設備名稱', '客廳開關'), ('產品', link('BTN-DEMO · 示範控制器', 'Manage/Products/Show', style='')), ('鎖定狀態', '未上鎖'), ('啟用狀態', '已啟用'), ('建立時間', DATE), ('更新時間', DATE)]), '<span class="badge">' + ('已加入房間' if assigned else '未加入房間') + '</span>') + panel('目前房間', details([('房間公開 ID', link('ROOM-DEMO-01', 'Manage/Rooms/Show', style='')), ('房間名稱', '客廳'), ('房間狀態', '正常')]) if assigned else '<p>設備尚未加入房間</p>') + '</div>' + panel('產品功能', table('功能代碼|功能說明', [['FUNC-DEMO-01', '切換電源'], ['FUNC-DEMO-02', '關閉電源']])) + panel('設備移轉紀錄', table('時間|原房間|目標房間|操作者', [[DATE, '工作室', '客廳', 'USER-DEMO-01']]) + paging())


add('Manage/Devices/Show', SERIAL, device_detail())
add('Manage/Devices/Show', SERIAL, device_detail(False), 'unassigned', '設備未加入房間')

# Runtime combines two independently filtered and paginated sections.
def runtime_body(disabled=False, canceled=False, readonly=False):
    device_actions = '—' if readonly else actions(link('啟用' if disabled else '停用', 'Manage/DeviceRuntime/Index', 'default' if disabled else 'disable-confirm'), link('撤銷 JWT', 'Manage/DeviceRuntime/Index', 'revoke-confirm'))
    device_rows = [[link(SERIAL, 'Manage/Devices/Show', style='') + '<br>客廳開關', 'BTN-DEMO', '客廳', 'disabled' if disabled else 'idle', DATE, '0 active' if disabled else '1 active<br>access 到期：2026/10/28 11:30', device_actions]]
    job_rows = [['JOB-DEMO-01', 'canceled' if canceled else 'running · 45%', SERIAL, '切換電源<br>FUNC-DEMO-01', '示範使用者<br>USER-DEMO-01', '建立：' + DATE + '<br>完成：' + (DATE if canceled else '—'), '—' if canceled or readonly else link('取消', 'Manage/DeviceRuntime/Index', 'cancel-confirm')]]
    return panel('設備 runtime', form(field('搜尋', '', hint='序號、名稱、產品或房間') + field('狀態', '', hint='idle / running / disabled') + select('Runtime', '全部|可用|停用'), '查詢', filters=True) + table('設備|產品|房間|Runtime|最後活動|Token|操作', device_rows) + paging()) + panel('按鈕任務', form(field('搜尋', '', hint='任務、request、設備或使用者') + field('設備序號') + select('狀態', '全部|queued|running|succeeded|failed|device_offline|timed_out|unauthorized|canceled'), '查詢', filters=True) + table('任務|狀態|設備|功能|使用者|時間|操作', job_rows) + paging())


add('Manage/DeviceRuntime/Index', '設備執行', runtime_body())
add('Manage/DeviceRuntime/Index', '設備執行', runtime_body(True), 'disabled', 'Runtime 已停用')
add('Manage/DeviceRuntime/Index', '設備執行', notice('設備 JWT 已撤銷。') + runtime_body().replace('1 active<br>access 到期：2026/10/28 11:30', '0 active<br>access 到期：—'), 'revoked', 'JWT 已撤銷')
add('Manage/DeviceRuntime/Index', '設備執行', notice('任務已取消。') + runtime_body(canceled=True), 'canceled', '任務已取消')
add('Manage/DeviceRuntime/Index', '設備執行', runtime_body(readonly=True), 'readonly', '有查閱權限、無操作權限')
for state, title, text, target in [('disable-confirm', '停用設備 runtime', f'確定要停用：{SERIAL}？', 'disabled'), ('revoke-confirm', '撤銷設備 JWT', f'確定要撤銷 JWT：{SERIAL}？', 'revoked'), ('cancel-confirm', '取消按鈕任務', '確定要取消任務 JOB-DEMO-01？', 'canceled')]:
    add('Manage/DeviceRuntime/Index', '設備執行', runtime_body(), state, title, '原始碼使用 window.confirm；此框表示原生確認視窗。', dialog(title, '<p>' + text + '</p>' + actions(link('取消', 'Manage/DeviceRuntime/Index'), link('確定', 'Manage/DeviceRuntime/Index', target, 'button danger')), 'Manage/DeviceRuntime/Index'))

# Service manager roles and audit pages.
manager_filters = panel('篩選', form(search + select('角色', '服務管理員|全部|非服務管理員') + status_filter, '查詢', filters=True))
manager_headers = '選取|公開 ID|顯示名稱|Email|狀態|服務管理員|建立時間|最近登入|操作'


def managers_body(selected=False, menu=False):
    batch = actions(link('批次授權', 'Manage/ServiceManagers/Index', 'granted'), link('批次撤銷', 'Manage/ServiceManagers/Index', 'revoked')) if selected else '<div class="actions"><button disabled>批次授權</button><button disabled>批次撤銷</button></div>'
    rows = [[check('選取示範管理員', selected), link('USER-DEMO-01', 'Manage/ServiceManagers/Show', style=''), '示範管理員', 'manager@example.test', '正常', '是', DATE, DATE, link('⋮ 操作', 'Manage/ServiceManagers/Index', 'row-menu')]]
    menu_html = '<nav class="popover" aria-label="使用者操作">' + link('查看詳細', 'Manage/ServiceManagers/Show', style='') + link('撤銷服務管理員', 'Manage/ServiceManagers/Index', 'revoked', '') + '</nav>' if menu else ''
    return manager_filters + panel('使用者', batch + table(manager_headers, rows) + menu_html + paging())


add('Manage/ServiceManagers/Index', '服務管理員', managers_body())
add('Manage/ServiceManagers/Index', '服務管理員', managers_body(True), 'selected', '選取後批次操作', '透過相關狀態連結切換至已選取畫面；勾選框本身不持久化。')
add('Manage/ServiceManagers/Index', '服務管理員', managers_body(menu=True), 'row-menu', '使用者操作選單')
add('Manage/ServiceManagers/Index', '服務管理員', notice('服務管理員授權已完成。') + managers_body(), 'granted', '授權完成')
add('Manage/ServiceManagers/Index', '服務管理員', notice('服務管理員撤銷已完成。') + managers_body().replace('<td>是</td>', '<td>否</td>'), 'revoked', '撤銷完成')


def manager_detail(granted=True):
    return link('返回服務管理員一覽', 'Manage/ServiceManagers/Index') + '<div class="grid-2">' + panel('使用者資料', details([('公開 ID', 'USER-DEMO-01'), ('顯示名稱', '示範管理員'), ('Email', 'manager@example.test'), ('帳號狀態', 'active'), ('建立時間', DATE), ('最近登入', DATE)]), link('撤銷服務管理員' if granted else '授予服務管理員', 'Manage/ServiceManagers/Show', 'ungranted' if granted else 'default')) + panel('角色與權限', '<h3>Roles</h3><span class="badge">' + ('service_manager' if granted else 'user') + '</span><h3>Permissions</h3><p>' + ('manage.access · manage.users.view · manage.rooms.view · …' if granted else '沒有管理權限') + '</p>') + panel('最近管理後台登入', row('登入成功', DATE + ' · 192.0.2.1')) + panel('最近身分異動', row('service_manager.grant' if granted else 'service_manager.revoke', DATE + ' · 操作者 ADMIN-DEMO-01')) + '</div>' + panel('最近管理後台操作', row('manage.devices.view', DATE + ' · 目標 ' + SERIAL))


add('Manage/ServiceManagers/Show', '示範管理員', manager_detail())
add('Manage/ServiceManagers/Show', '示範管理員', manager_detail(False), 'ungranted', '尚未授權／已撤銷')
add('Manage/Audit/Index', '審計資料', '<div class="grid-2">' + panel('登入失敗紀錄', '<p>查看管理後台登入失敗與觸發鎖定的紀錄。</p>' + link('查看登入失敗紀錄', 'Manage/Audit/LoginFailures')) + panel('管理後台操作紀錄', '<p>查看管理操作、目標與 Metadata。</p>' + link('查看管理後台操作紀錄', 'Manage/Audit/ManageActions')) + '</div>')
audit_login_filters = field('Email') + field('IP') + field('使用者公開 ID') + select('失敗原因', '全部|帳密錯誤或無權限|登入限制') + field('開始日期', '', 'date') + field('結束日期', '', 'date') + check('只顯示觸發鎖定')
audit_action_filters = ''.join(field(label) for label in ['操作者', 'Action', 'Target Type', 'Target Public ID', 'IP']) + field('開始日期', '', 'date') + field('結束日期', '', 'date')
for source, title, filters, headers, cells, expanded in [
    ('Manage/Audit/LoginFailures', '登入失敗紀錄', audit_login_filters, '時間|Email|使用者公開 ID|IP|失敗原因|鎖定至|成功', [DATE, 'demo@example.test', 'USER-DEMO-01', '192.0.2.1', '帳密錯誤或無權限', '—', '否'], details([('User-Agent', 'Wireframe Browser / Demo'), ('失敗原因代碼', 'invalid_credentials_or_permission')])),
    ('Manage/Audit/ManageActions', '管理後台操作紀錄', audit_action_filters, '時間|操作者|Action|Target Type|Target Public ID|IP', [DATE, 'ADMIN-DEMO-01', 'service_manager.grant', 'User', 'USER-DEMO-01', '192.0.2.1'], details([('User-Agent', 'Wireframe Browser / Demo'), ('Metadata', '<pre>{\n  "role": "service_manager",\n  "operation": "grant"\n}</pre>')])),
]:
    base = link('返回審計資料', 'Manage/Audit/Index', style='') + panel('篩選', form(filters, '查詢', filters=True))
    record = table(headers, [cells]) + link('展開紀錄', source, 'expanded', '')
    add(source, title, base + panel('管理後台登入失敗' if 'LoginFailures' in source else '操作紀錄', record + paging()))
    add(source, title, base + panel('紀錄詳細', table(headers, [cells]) + expanded + link('收合紀錄', source, style='') + paging()), 'expanded', '展開紀錄詳細')

# Shared asynchronous states, shown once per representative shell, and empty lists.
for source, title in [('Manage/Users/Index', '使用者一覽')]:
    add(source, title, notice('載入中…') + '<div class="panel" aria-busy="true"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>', 'loading', '資料載入中', '此畫面代表同版型的共通非同步載入狀態；灰條用於標示待載入區域。')
    add(source, title, notice('資料載入失敗，請重新整理後再試。', True), 'load-error', '資料載入失敗')
for source, title, filters in [('Manage/Users/Index', '使用者一覽', search + status_filter), ('Manage/Rooms/Index', '房間一覽', field('搜尋') + select('狀態', '全部|正常|刪除')), ('Manage/Products/Index', '產品一覽', field('搜尋')), ('Manage/Devices/Index', '設備一覽', device_filters), ('Manage/ServiceManagers/Index', '服務管理員', search + status_filter)]:
    add(source, title, panel('篩選', form(filters, '查詢', filters=True)) + panel(title, empty('沒有符合條件的資料')), 'empty', '篩選無結果')

def manage_shell(body, frame):
    limited = frame['state'] == 'service-manager' and frame['source'] == 'Manage/Index'
    nav_items = [('管理首頁', 'Manage/Index'), ('使用者一覽', 'Manage/Users/Index'), ('房間一覽', 'Manage/Rooms/Index'), ('產品一覽', 'Manage/Products/Index'), ('設備一覽', 'Manage/Devices/Index'), ('設備執行', 'Manage/DeviceRuntime/Index'), ('服務管理員', 'Manage/ServiceManagers/Index'), ('審計資料', 'Manage/Audit/Index')]
    if limited:
        nav_items = nav_items[:-2]
        body = body.replace(row(link('服務管理員', 'Manage/ServiceManagers/Index', style=''), '授予與撤銷管理身分'), '').replace(row(link('審計資料', 'Manage/Audit/Index', style=''), '管理登入失敗與操作紀錄'), '').replace('system_admin', 'service_manager').replace(' <span class="badge">audit.access</span>', '')
    nav = ''.join(link('<span aria-hidden="true">□</span> <span class="nav-label">' + label + '</span>', source, style='', extra=('aria-current="page" ' if frame['source'].split('/')[1] == source.split('/')[1] else '') + 'aria-label="' + label + '"') for label, source in nav_items)
    collapsed = frame['state'] == 'collapsed'
    return f'<div class="manage-frame{" collapsed" if collapsed else ""}"><aside class="sidebar">' + link('Button Control Center<br>管理後台', 'Manage/Index', style='brand') + f'<button data-sidebar aria-label="收合或展開側欄" aria-expanded="{str(not collapsed).lower()}">☰</button><nav aria-label="後台導覽">' + nav + '</nav><div class="sidebar-logout">' + link('<span aria-hidden="true">↪</span> <span class="nav-label">登出</span>', 'Manage/Login', style='', extra='aria-label="登出管理後台"') + '</div></aside><div class="manage-content">' + body + '</div></div>'


def html_doc(title, body):
    prefix = ''
    return '<!doctype html>\n<html lang="zh-Hant">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>' + esc(title) + ' · Wireframe</title>\n<link rel="stylesheet" href="' + prefix + 'wireframe.css">\n<script defer src="' + prefix + 'wireframe.js"></script>\n</head>\n<body>\n' + body + '\n</body>\n</html>\n'


def render(frame, index):
    related = [f for f in FRAMES if f['source'] == frame['source']]
    frame_id = f'KF-{index+1:03}'
    source_path = 'resources/js/pages/' + frame['source'] + '.jsx'
    title = frame['title'] + ' / ' + frame['label']
    toolbar = '<div class="review-bar"><div><span class="review-label">WIREFRAME / ' + frame_id + '</span><span class="review-title">' + title + '</span></div><nav class="review-actions" aria-label="Wireframe 導覽"><a class="button" href="index.html">畫面索引</a>'
    for offset, label in [(-1, '上一張'), (1, '下一張')]:
        other = FRAMES[(index + offset) % len(FRAMES)]
        toolbar += f'<a class="button adjacent" href="{other["file"]}">{label}</a>'
    toolbar += '</nav></div>'
    notes = '<footer class="frame-notes"><p><strong>' + frame_id + '</strong> · ' + esc(frame['route']) + ' · ' + frame['label'] + '</p><p>對照來源：<a href="../../..' + '/' + source_path + '">' + source_path + '</a></p><p>灰階結構示意；所有人物、識別碼、時間與資料皆為虛構。' + frame['note'] + '</p><nav class="state-links" aria-label="相關狀態">' + ''.join(link(f['label'], f['source'], f['state'], '', 'aria-current="page"' if f['file'] == frame['file'] else '') for f in related) + '</nav></footer>'
    # Auth titles are inside the card; application pages have a page heading.
    heading = '<div class="page-heading"><div><p class="eyebrow">' + frame['source'] + '</p><h1>' + frame['title'] + '</h1></div></div>'
    preview = '<div id="preview-notice" class="notice" role="status" hidden></div>'
    if frame['layout'] == 'auth':
        main = '<main class="auth-shell"><section class="panel">' + heading + preview + frame['body'] + '</section>' + notes + '</main>'
    else:
        main = '<main class="workspace' + (' button-workspace' if frame['source'] == 'Buttons/Index' else ' account' if frame['source'].startswith('Account/') else '') + '">' + heading + preview + frame['body'] + notes + '</main>'
        main = manage_shell(main, frame)
    main = '<div data-underlay>' + main + '</div>'
    if frame['modal']:
        d = frame['modal']
        main += '<div class="overlay"><section class="dialog" role="dialog" aria-modal="true" aria-labelledby="dialog-title" tabindex="-1" data-close="' + d['close'] + '"><div class="panel-head"><h2 id="dialog-title">' + d['title'] + '</h2><a class="button" href="' + d['close'] + '" aria-label="關閉對話框">×</a></div><div class="dialog-body">' + d['body'] + '</div></section></div>'
    return html_doc(title, toolbar + main)


def main():
    all_sources = {str(p.relative_to(REPO / 'resources/js/pages').with_suffix('')).replace('\\', '/') for p in (REPO / 'resources/js/pages').rglob('*.jsx')}
    actual_sources = {s for s in all_sources if s.startswith('Manage/') == IS_MANAGE}
    assert actual_sources == set(ROUTES), f'Page inventory drift: {actual_sources ^ set(ROUTES)}'
    assert len({f['file'] for f in FRAMES}) == len(FRAMES), 'Duplicate frame filenames'
    assert {f['source'] for f in FRAMES if f['state'] == 'default'} == actual_sources, 'Missing default frame'
    for i, frame in enumerate(FRAMES):
        frame['id'] = f'KF-{i+1:03}'
        (ROOT / frame['file']).write_text(render(frame, i), encoding='utf-8')
    groups = ''
    for group in dict.fromkeys(f['group'] for f in FRAMES):
        cards = ''.join('<a class="frame-card" href="' + f['file'] + '"><span class="review-label">' + f['id'] + '</span><h3>' + f['title'] + '</h3><span class="badge">' + f['label'] + '</span><code>' + esc(f['route']) + '</code><code>' + f['source'] + '</code></a>' for f in FRAMES if f['group'] == group)
        groups += '<section class="catalog-group"><h2>' + group + '</h2><div class="catalog-list">' + cards + '</div></section>'
    intro = f'<main class="catalog"><header class="catalog-head"><p class="eyebrow">Button Control Center / 2026.10.28</p><h1>{CATALOG_TITLE} Key Frames</h1><p>涵蓋 {len(actual_sources)} 個 React 頁面來源、{len(FRAMES)} 張頁面與關鍵狀態。</p><p class="muted">以現有前端為準的灰階結構文件，可沿流程瀏覽，不會發送 API 請求。</p><div class="actions"><span class="badge">{len(FRAMES)} 個 key frames</span><span class="badge">{len(actual_sources)} 個頁面來源</span><a href="README.md">範圍與來源對照表</a></div></header><label>搜尋畫面、狀態或路由<input type="search" data-search placeholder="輸入畫面名稱、狀態或路由"></label>'
    (ROOT / 'index.html').write_text(html_doc(CATALOG_TITLE + '索引', intro + groups + '</main>'), encoding='utf-8')
    manifest = [{k: f[k] for k in ['id', 'file', 'source', 'route', 'group', 'state', 'label', 'title', 'note']} for f in FRAMES]
    (ROOT / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    readme = f"""# {CATALOG_TITLE} key frames — 2026-10-28

直接以瀏覽器開啟 [index.html](index.html)。本目錄包含 **{len(actual_sources)} 個 React 頁面來源、{len(FRAMES)} 張 HTML key frames**，每張畫面均可獨立開啟。

## 範圍與讀法

- 包含管理登入、儀表板、使用者、房間、產品、設備、runtime、服務管理員與審計。
- 預設為 system_admin 權限，另有 service_manager 導覽與 runtime 只讀狀態。實際介面依權限決定。

- 依現有路由、React 頁面與 layout 盤點，保留既有關鍵狀態；此為結構文件。
- 每個頁面至少有一張預設畫面。影響主要內容的分頁、權限、選單、編輯、確認框與任務狀態另有 HTML；共通載入、失敗與空資料使用代表畫面，沒有窮舉資料及權限的所有排列組合。
- 每張頁尾提供來源、路由與相關狀態；上方工具列與頁尾為文件導覽，上一張／下一張只在此目錄內循環。
- `window.confirm` 以灰階確認框表示，並在頁尾註明；外部服務的交接說明不代表產品內有該對話框。
- 姓名、EMAIL、識別碼、隱碼、數量與時間皆為虛構。按鈕轉成灰階，表單保留色碼文字；未串接 API、真實設備或資料儲存。
- 表單只導向示範結果或顯示預覽提示；列表篩選不查詢資料，分頁為單頁示意。以相關狀態查看無結果、已選取等分支。
- 桌面與手機共用響應式 HTML；行動選單、側欄收合、索引搜尋與對話框鍵盤操作由本目錄的 JavaScript 提供。寬表格可水平捲動。
- 本目錄的索引、樣式、腳本、manifest 與產生器皆獨立，不依賴另一套 wireframes 或共用總索引。

## 檔案與重新產生

- `index.html`：此目錄專用索引。
- 其他 `*.html`：每個 key frame 一個檔案；`--狀態` 後綴代表同一頁面的不同狀態。
- `wireframe.css`、`wireframe.js`：本目錄使用的樣式與本機互動，無 CDN、外部字型或套件依賴。
- `manifest.json`：本目錄逐張來源、路由與狀態對照。
- `build_wireframes.py`：以 Python 標準函式庫產生此目錄的 HTML、manifest 與說明。

在此目錄執行 `python build_wireframes.py` 可重新產生。新增畫面或變更狀態時先修改產生器。移除狀態時另人工移除對應舊 HTML，產生器不會刪除檔案。

## 驗證範圍

已檢查頁面來源覆蓋、分類歸屬、HTML 主標題與 ID、相對連結、樣式／腳本及表單／對話框導頁目標，並通過 JavaScript 語法檢查。瀏覽器工具安全政策拒絕開啟本機 `file://`；瀏覽器視覺、響應式排版與互動仍待人工確認。

## 頁面與 key frame 對照

| 原始頁面 | 路由 | 對應畫面 |
| --- | --- | --- |
"""
    for source in ROUTES:
        entries = [f for f in FRAMES if f['source'] == source]
        readme += '| `resources/js/pages/' + source + '.jsx` | `' + ROUTES[source] + '` | ' + '、'.join(f'[{f["label"]}]({f["file"]})' for f in entries) + ' |\n'
    (ROOT / 'README.md').write_text(readme, encoding='utf-8')
    print(f'{CATALOG_TITLE}: generated {len(FRAMES)} frames + own index; covered {len(actual_sources)}/{len(actual_sources)} React pages.')


if __name__ == '__main__':
    main()
