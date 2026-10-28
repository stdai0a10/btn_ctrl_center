"""Regenerate the static documentation frames; standard library only."""
from pathlib import Path
from html import escape as esc
import json

ROOT = Path(__file__).resolve().parent
REPO = ROOT.parents[2]
FRAMES = []
SERIAL = 'DEMO-DEVICE-001'
DATE = '2026/10/28 10:30'
ROUTES = {'Home': '/',
 'Auth/Login': '/login',
 'Auth/Register': '/register',
 'Auth/ForgotPassword': '/forgot-password',
 'Auth/ResetPassword': '/reset-password?token=…',
 'Auth/VerifyEmailResult': '/verify-email/result?token=…',
 'Account/Profile': '/account/profile',
 'Account/Email': '/account/email',
 'Account/Security': '/account/security',
 'Account/Providers': '/account/providers',
 'Account/Reauth': '/reauth?back=…',
 'Rooms/Index': '/rooms',
 'Rooms/Show': '/rooms/{room}',
 'Rooms/Invitations': '/room-invitations',
 'Buttons/Index': '/buttons'}


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


IS_MANAGE = False
CATALOG_TITLE = '一般用戶'

# Authentication: no application navigation in these source pages.
login_fields = field('EMAIL', 'demo@example.test', 'email') + field('密碼', 'wireframe-only', 'password')
login_links = actions(link('建立帳號', 'Auth/Register', style=''), link('忘記密碼', 'Auth/ForgotPassword', style=''))
login = '<p class="muted">請使用已驗證 EMAIL 與密碼登入。</p>' + form(login_fields, '登入', 'Buttons/Index') + '<hr>' + link('使用 LINE 登入', 'Auth/Login', 'line-redirect') + login_links
add('Auth/Login', '登入', login)
add('Auth/Login', '登入', notice('EMAIL 或密碼錯誤，請確認後再試。', True) + login, 'error', '登入錯誤')
add('Auth/Login', '登入', notice('登入中…') + field('EMAIL', 'demo@example.test', disabled=True) + field('密碼', 'wireframe-only', 'password', disabled=True) + '<button disabled>登入中…</button>', 'processing', '登入中')
add('Auth/Login', '登入', login, 'line-redirect', 'LINE 外部流程', '「使用 LINE 登入」會導向 /api/auth/line/redirect，LINE 授權畫面屬外部服務；此文件只標示交接點。', modal=dialog('離開本站進行 LINE 授權', '<p>目前實作交接至 LINE OAuth，登入完成後回到本站。此為流程註記，並非產品內的確認視窗。</p>' + link('返回登入示意', 'Auth/Login'), 'Auth/Login'))
register = '<p class="muted">系統會寄出 24 小時內有效的驗證信。</p>' + form(field('EMAIL', 'demo@example.test', 'email') + field('密碼', '', 'password', '至少 12 碼，避免常見弱密碼。') + field('確認密碼', '', 'password'), '寄出驗證信', 'Auth/Register', 'sent') + link('已有帳號？前往登入', 'Auth/Login', style='')
add('Auth/Register', '建立帳號', register)
add('Auth/Register', '建立帳號', notice('驗證信已寄出，請檢查信箱。') + register, 'sent', '驗證信寄出')
add('Auth/Register', '建立帳號', notice('請檢查欄位。', True) + register.replace('至少 12 碼，避免常見弱密碼。', '密碼至少需要 12 碼。'), 'validation', '欄位驗證失敗')
forgot = '<p class="muted">輸入已驗證 EMAIL。系統會寄出 1 小時內有效的密碼變更信。</p>' + form(field('EMAIL', 'demo@example.test', 'email'), '寄出密碼變更信', 'Auth/ForgotPassword', 'sent') + link('返回登入', 'Auth/Login', style='')
add('Auth/ForgotPassword', '忘記密碼', forgot)
add('Auth/ForgotPassword', '忘記密碼', notice('若 EMAIL 符合條件，將收到密碼變更信。') + forgot, 'sent', '寄送結果')
reset = notice('重設連結有效。') + form(field('新密碼', '', 'password') + field('確認新密碼', '', 'password'), '變更密碼', 'Auth/ResetPassword', 'success') + link('返回登入', 'Auth/Login', style='')
add('Auth/ResetPassword', '重設密碼', reset, note='有效 token 下顯示表單。')
for state, label, text in [('loading', '檢查連結中', '正在檢查重設連結…'), ('invalid', '連結失效', '密碼重設連結已失效，或缺少重設 token。'), ('success', '變更成功', '密碼已變更，請使用新密碼登入。')]:
    add('Auth/ResetPassword', '重設密碼', notice(text, state == 'invalid') + link('返回登入', 'Auth/Login', style=''), state, label)
for state, label, text in [('default', '驗證成功', 'EMAIL 驗證成功。'), ('loading', '驗證中', '正在驗證 EMAIL…'), ('invalid', '驗證失敗', '驗證連結已失效，或缺少驗證 token。')]:
    add('Auth/VerifyEmailResult', '驗證結果', notice(text, state == 'invalid') + actions(link('前往登入', 'Auth/Login', style=''), link('重新註冊', 'Auth/Register', style='')), state, label)

# Account pages and inline name editing.
add('Home', 'Button Control Center', panel('Button Control Center', '<p>Laravel 12 + Inertia + React</p>'), note='首頁目前只有專案標題與技術文字，保留現況。')
account_links = actions(*(link(title, 'Account/' + name, style='') for title, name in [('EMAIL 設定', 'Email'), ('安全設定', 'Security'), ('登入方式', 'Providers')]))


def profile(edit=False):
    name = form(field('顯示名稱', '示範使用者'), '套用', 'Account/Profile') + link('取消', 'Account/Profile', style='') if edit else row('示範使用者', '顯示名稱', link('更新名字', 'Account/Profile', 'editing'))
    return panel('帳號資料', details([('ID', 'USER-DEMO-01')]) + name + details([('EMAIL', 'demo@example.test'), ('密碼', '已設定'), ('LINE', '已綁定')])) + account_links


add('Account/Profile', '帳號資料', profile())
add('Account/Profile', '帳號資料', profile(True), 'editing', '更新名字')
email = panel('EMAIL', details([('目前 EMAIL', 'demo@example.test'), ('驗證狀態', '已驗證'), ('待驗證新 EMAIL', '無')]) + '<hr>' + form(field('新 EMAIL', 'new@example.test', 'email'), '寄出變更驗證信', 'Account/Email', 'pending')) + link('返回帳號資料', 'Account/Profile', style='')
add('Account/Email', 'EMAIL', email)
add('Account/Email', 'EMAIL', notice('變更驗證信已寄出。') + email.replace('待驗證新 EMAIL</dt><dd>無', '待驗證新 EMAIL</dt><dd>new@example.test'), 'pending', '新 EMAIL 待驗證')
add('Account/Email', 'EMAIL', notice('需要重新驗證。' + link('前往重新驗證', 'Account/Reauth', style=''), True) + email, 'reauth-required', '敏感操作需重新驗證')
security = '<p>密碼狀態：已設定</p>' + form(field('目前密碼', '', 'password') + field('新密碼', '', 'password') + field('確認新密碼', '', 'password'), '更新密碼') + link('返回帳號資料', 'Account/Profile', style='')
add('Account/Security', '安全設定', panel('安全設定', security))
add('Account/Security', '安全設定', panel('安全設定', security.replace('<p>密碼狀態：已設定</p>', '<p>密碼狀態：未設定</p>').replace(field('目前密碼', '', 'password'), '')), 'no-password', '首次設定密碼')
for state, bound in [('default', True), ('unbound', False)]:
    provider = '<p>LINE 可作為獨立登入方式。綁定或解除前需先完成重新驗證。</p>' + panel('LINE', '<p>' + ('已綁定' if bound else '未綁定') + '</p>' + link('解除綁定' if bound else '綁定 LINE', 'Account/Providers', 'unbind-confirm' if bound else 'default')) + link('返回帳號資料', 'Account/Profile', style='')
    add('Account/Providers', '登入方式', provider, state, '已綁定' if bound else '未綁定')
provider_body = next(f['body'] for f in FRAMES if f['source'] == 'Account/Providers' and f['state'] == 'default')
add('Account/Providers', '登入方式', provider_body, 'unbind-confirm', '解除綁定確認', '原始碼使用 window.confirm；以灰階確認框表示瀏覽器原生對話框。', dialog('解除 LINE 綁定', '<p>解除 LINE 綁定後，將無法再用此 LINE 登入。確定解除？</p>' + actions(link('取消', 'Account/Providers'), link('確定解除', 'Account/Providers', 'unbound', 'button primary')), 'Account/Providers'))
reauth = '<p>敏感操作前需重新輸入密碼。通過後 10 分鐘內有效。</p>' + form(field('密碼', '', 'password'), '完成重新驗證', 'Account/Email') + link('取消', 'Account/Profile', style='')
add('Account/Reauth', '重新驗證', reauth, note='示範 back=/account/email；實際回到 back 指定頁面。')
add('Account/Reauth', '重新驗證', notice('密碼不正確，請再試一次。', True) + reauth, 'error', '驗證失敗')

# Rooms: owner, resident, restricted access, tabs, device menus and dialogs.
room_create = panel('建立房間', form(field('房間名稱', '客廳'), '建立房間', 'Rooms/Show'))
room_list = row('客廳', '房主 · ROOM-DEMO-01', link('進入房間', 'Rooms/Show')) + row('工作室', '住戶 · ROOM-DEMO-02', link('進入房間', 'Rooms/Show', 'resident'))
room_invite_link = link('我的邀請', 'Rooms/Invitations', style='')
add('Rooms/Index', '房間管理', room_invite_link + '<div class="grid-2">' + room_create + panel('我的房間', room_list) + '</div>')
add('Rooms/Index', '房間管理', room_invite_link + '<div class="grid-2">' + room_create + panel('我的房間', empty('尚未加入任何房間')) + '</div>', 'empty', '尚無房間')


def room_body(state='default'):
    owner = state != 'resident'
    header_actions = actions(link('退出', 'Rooms/Show', 'leave-confirm'), link('刪除', 'Rooms/Show', 'delete-confirm', 'button danger') if owner else '')
    tab_items = [('成員 2', 'Rooms/Show', 'default'), ('申請 2', 'Rooms/Show', 'requests'), ('邀請 1', 'Rooms/Show', 'invitations')]
    members = row('示範使用者', '房主 · USER-DEMO-01') + row('示範住戶', '住戶 · USER-DEMO-02', actions(link('升為房主', 'Rooms/Show', 'promoted'), link('移除', 'Rooms/Show', 'remove-member', 'button danger')) if owner else '')
    if state == 'promoted':
        members = row('示範使用者', '房主 · USER-DEMO-01', link('改為住戶', 'Rooms/Show')) + row('示範住戶', '房主 · USER-DEMO-02', actions(link('改為住戶', 'Rooms/Show'), link('移除', 'Rooms/Show', 'remove-member')))
    if state == 'requests':
        members = row('示範申請人', 'pending · USER-DEMO-03', actions(link('接受', 'Rooms/Show'), link('忽略', 'Rooms/Show', 'request-ignored'))) + row('先前申請人', 'ignored · USER-DEMO-04', link('恢復待決定', 'Rooms/Show', 'requests'))
    if state == 'request-ignored':
        members = row('示範申請人', 'ignored · USER-DEMO-03', link('恢復待決定', 'Rooms/Show', 'requests'))
    if state == 'invitations':
        members = form(field('使用者 ID', 'USER-DEMO-03'), '送出邀請') + row('示範受邀者', 'pending · USER-DEMO-03', link('取消', 'Rooms/Show', 'invitations'))
    member_panel = panel('房間成員', tabs(tab_items, state if state in ['requests', 'invitations'] else 'default') + members, '<span class="badge">2 人</span>')
    device_name = form(field('設備名稱', '客廳開關'), '套用', 'Rooms/Show') + link('取消', 'Rooms/Show', style='') if state == 'rename-device' else '<strong>客廳開關</strong>'
    devices = row(device_name, f'{SERIAL} · 未上鎖 · 已啟用<br>BTN-DEMO · 示範控制器', link('⋮ 操作', 'Rooms/Show', 'device-menu' if owner else 'resident-device-menu'))
    if state in ['device-menu', 'resident-device-menu']:
        devices += '<nav class="popover" aria-label="設備操作">' + link('設備資訊', 'Rooms/Show', 'device-info', '')
        if owner:
            devices += ''.join(link(label, 'Rooms/Show', target, '') for label, target in [('改名', 'rename-device'), ('停用', 'device-disabled'), ('上鎖', 'device-locked'), ('移除', 'remove-device')])
        devices += '</nav>'
    if state == 'device-disabled':
        devices = row('客廳開關', f'{SERIAL} · 未上鎖 · 已停用', link('啟用', 'Rooms/Show'))
    if state == 'device-locked':
        devices = row('客廳開關', f'{SERIAL} · 已上鎖 · 已啟用', link('解鎖', 'Rooms/Show'))
    return header_actions + member_panel + panel('加入設備', link('加入設備', 'Rooms/Show', 'add-device') if owner else '<p class="muted">僅房主可加入設備</p>') + panel('設備', devices, '<span class="badge">1 部</span>')


for state, label in [('default', '房主／成員'), ('resident', '住戶'), ('requests', '加入申請分頁'), ('request-ignored', '申請已忽略'), ('invitations', '邀請分頁'), ('promoted', '多位房主'), ('device-menu', '設備操作選單'), ('resident-device-menu', '住戶設備選單'), ('rename-device', '設備改名'), ('device-disabled', '設備已停用'), ('device-locked', '設備已上鎖')]:
    add('Rooms/Show', '客廳', room_body(state), state, label, '僅房主具有設備與成員管理操作；最後一位房主不顯示降級操作。')
# Resident menu uses the same resident body with only the info menu visible.
resident_menu = room_body('resident') + '<nav class="popover" aria-label="住戶設備操作">' + link('設備資訊', 'Rooms/Show', 'device-info', '') + '</nav>'
next(f for f in FRAMES if f['source'] == 'Rooms/Show' and f['state'] == 'resident-device-menu')['body'] = resident_menu
for state, label in [('restricted', '非成員'), ('join-pending', '加入申請審核中'), ('join-cancelled', '加入申請已取消')]:
    content = '<p>你不是這個房間的成員，無法查看成員、邀請、申請與設備內容。</p>'
    content += notice('加入申請審核中；房主接受後即可使用。') + link('取消申請', 'Rooms/Show', 'join-cancelled') if state == 'join-pending' else ('<p>先前的申請已取消，你可以再次提出申請。</p>' if state == 'join-cancelled' else '') + link('申請加入房間', 'Rooms/Show', 'join-pending', 'button primary')
    add('Rooms/Show', '客廳', panel('你目前無法使用這個房間', content), state, label)
add_device = form(field('序號', SERIAL) + field('隱碼', 'DEMO-SECRET') + field('名稱', '客廳開關') + check('加入後上鎖'), '確認加入', 'Rooms/Show', 'add-success') + link('取消', 'Rooms/Show', style='')
room_dialogs = [
    ('add-device', '加入設備', add_device),
    ('add-success', '設備加入成功', '<p>設備已加入房間。</p>' + link('關閉', 'Rooms/Show')),
    ('add-error', '設備加入失敗', notice('序號或隱碼不正確，請確認後重試。', True) + actions(link('返回修改', 'Rooms/Show', 'add-device'), link('關閉', 'Rooms/Show'))),
    ('remove-device', '確認移除設備', '<p>確定要從房間移除「客廳開關」嗎？</p>' + actions(link('取消', 'Rooms/Show'), link('確認移除', 'Rooms/Show', 'devices-empty', 'button danger'))),
    ('remove-member', '確認移除成員', '<p>確定要將「示範住戶」從房間移除嗎？</p><p class="muted">移除後，該使用者將無法查看或操作這個房間的設備。</p>' + actions(link('取消', 'Rooms/Show'), link('確認移除', 'Rooms/Show'))),
    ('device-info', '設備資訊', details([('設備名稱', '客廳開關'), ('設備序號', SERIAL), ('產品', 'BTN-DEMO · 示範控制器'), ('鎖定狀態', '未上鎖'), ('啟用狀態', '已啟用'), ('加入時間', DATE), ('最近更新', DATE)]) + link('關閉', 'Rooms/Show')),
    ('leave-confirm', '退出房間', '<p>確定要退出房間？</p>' + actions(link('取消', 'Rooms/Show'), link('確定退出', 'Rooms/Index'))),
    ('delete-confirm', '刪除房間', '<p>確定要刪除房間？</p>' + actions(link('取消', 'Rooms/Show'), link('確定刪除', 'Rooms/Index', 'empty', 'button danger'))),
]
for state, title, body in room_dialogs:
    add('Rooms/Show', '客廳', room_body(), state, title, '退出／刪除使用 window.confirm，其餘為應用程式對話框。', dialog(title, body, 'Rooms/Show'))
add('Rooms/Show', '客廳', panel('房間成員', row('示範使用者', '房主')) + panel('加入設備', link('加入設備', 'Rooms/Show', 'add-device')) + panel('設備', empty('沒有設備')), 'devices-empty', '沒有設備')
invitation_panels = panel('收到邀請', row('工作室', 'pending · 邀請人：示範房主', actions(link('接受', 'Rooms/Show', 'resident'), link('忽略', 'Rooms/Invitations', 'empty')))) + panel('我的申請', row('共享空間', 'pending', link('取消', 'Rooms/Invitations', 'empty'))) + panel('已送出邀請', row('客廳', 'pending · 受邀者：示範住戶')) + panel('收到申請', row('客廳', 'pending · 申請人：示範申請人', actions(link('接受', 'Rooms/Show'), link('忽略', 'Rooms/Invitations', 'ignored'))))
add('Rooms/Invitations', '我的邀請', link('返回房間管理', 'Rooms/Index', style='') + invitation_panels)
add('Rooms/Invitations', '我的邀請', ''.join(panel(t, empty(e)) for t, e in [('收到邀請', '沒有待處理邀請'), ('我的申請', '沒有加入申請'), ('已送出邀請', '沒有送出邀請'), ('收到申請', '沒有收到申請')]), 'empty', '沒有邀請與申請')
add('Rooms/Invitations', '我的邀請', panel('收到申請', row('示範申請人', 'ignored', link('恢復待決定', 'Rooms/Invitations'))), 'ignored', '忽略後可恢復')

# Button dashboard and editor.
def buttons_body(edit=False, unavailable=False, menu=False, empty_buttons=False):
    toolbar = actions(link('客廳 ⌄', 'Buttons/Index', 'page-picker'), link('☰ 分頁選單', 'Buttons/Index', 'page-menu'))
    body = toolbar + tabs([('客廳', 'Buttons/Index', 'default'), ('工作室', 'Buttons/Index', 'other-page')], 'default')
    if edit:
        body += panel('編輯分頁', form(field('分頁名稱', '客廳') + select('欄數', '3 欄|2 欄|4 欄|5 欄'), '儲存', 'Buttons/Index', filters=True) + actions(link('取消', 'Buttons/Index'), link('加入按鈕', 'Buttons/Index', 'add-button')))
    if empty_buttons:
        return body + empty('這個分頁還沒有按鈕。')
    tile_specs = [('⏻', '客廳燈', False), ('○', '風扇', True), ('□', '總開關', False)]
    tiles = ''
    for symbol, text, circle in tile_specs:
        dest = 'button-menu' if edit else 'job-running'
        tiles += link(f'<span class="tile-symbol">{symbol}</span><span>{text}</span>' + ('<small>⋮ 編輯</small>' if edit else '<small>設備已停用</small>' if unavailable else ''), 'Buttons/Index', 'button-info' if unavailable else dest, 'tile' + (' circle' if circle else '') + (' unavailable' if unavailable else ''))
    if edit:
        tiles += link('<span class="tile-symbol">＋</span><small>空白位置</small>', 'Buttons/Index', 'add-button', 'tile tile-slot')
    body += '<div class="button-grid' + (' edit-grid' if edit else '') + '" style="--columns:3">' + tiles + '</div>'
    if menu:
        body += '<nav class="popover" aria-label="按鈕操作">' + ''.join(link(l, 'Buttons/Index', s, '') for l, s in [('按鈕資訊', 'button-info'), ('修改按鈕', 'edit-button'), ('刪除按鈕', 'delete-button')]) + '</nav>'
    return body


add('Buttons/Index', '按鈕', buttons_body())
add('Buttons/Index', '按鈕', panel('目前沒有分頁', '<p>輸入分頁名稱後新增第一個按鈕分頁。</p>' + form(field('新增分頁名稱', '客廳'), '新增分頁', 'Buttons/Index', 'empty-buttons')), 'empty', '沒有分頁')
add('Buttons/Index', '按鈕', buttons_body(empty_buttons=True), 'empty-buttons', '分頁沒有按鈕')
add('Buttons/Index', '按鈕', buttons_body(True), 'editing', '編輯分頁', '欄數 2–5；按鈕位置可於加入／修改表單調整。')
add('Buttons/Index', '按鈕', buttons_body(True, menu=True), 'button-menu', '按鈕操作選單')
add('Buttons/Index', '按鈕', buttons_body(unavailable=True), 'unavailable', '按鈕不可操作', '不可操作狀態以灰階斜線標示；點選可檢視資訊。')
add('Buttons/Index', '按鈕', buttons_body(empty_buttons=True).replace('客廳', '工作室'), 'other-page', '切換分頁')
page_menu = '<nav class="popover" aria-label="分頁選單">' + ''.join(link(l, 'Buttons/Index', s, '') for l, s in [('新增分頁', 'create-page'), ('排序分頁', 'order-pages'), ('編輯分頁', 'editing'), ('刪除分頁', 'delete-page')]) + '</nav>'
add('Buttons/Index', '按鈕', page_menu + buttons_body(), 'page-menu', '分頁選單')
add('Buttons/Index', '按鈕', '<nav class="popover" aria-label="選擇分頁">' + link('客廳 ✓', 'Buttons/Index', style='') + link('工作室', 'Buttons/Index', 'other-page', '') + '</nav>' + buttons_body(), 'page-picker', '選擇分頁')
add('Buttons/Index', '按鈕', form(field('新增分頁名稱', '新分頁'), '新增', 'Buttons/Index', 'empty-buttons') + buttons_body(), 'create-page', '新增分頁', '每位使用者最多 50 個分頁。')
button_fields = select('設備', '客廳開關 · DEMO-DEVICE-001|工作室開關 · DEMO-DEVICE-002') + select('功能', '切換電源 · FUNC-DEMO-01|關閉電源 · FUNC-DEMO-02') + field('位置', '0', 'number') + select('形狀', '方形|圓形') + field('背景色', '#2563EB', hint='灰階 wireframe 保留色碼欄位。') + field('文字/icon 色', '#FFFFFF') + select('Icon', 'Power|Square|Circle') + field('文字', '客廳燈', hint='留空時顯示 icon')
for state, title in [('add-button', '加入按鈕'), ('edit-button', '修改按鈕')]:
    add('Buttons/Index', '按鈕', buttons_body(True), state, title, modal=dialog(title, form(button_fields, '套用', 'Buttons/Index', 'editing') + link('取消', 'Buttons/Index', 'editing', ''), 'Buttons/Index', 'editing'))
add('Buttons/Index', '按鈕', buttons_body(), 'button-info', '按鈕資訊', modal=dialog('客廳燈', details([('設備', '客廳開關'), ('序號', SERIAL), ('房間', '客廳'), ('產品', '示範控制器'), ('功能', '切換電源 · FUNC-DEMO-01'), ('狀態', '可操作')]), 'Buttons/Index'))
add('Buttons/Index', '按鈕', buttons_body(), 'order-pages', '排序分頁', modal=dialog('排序分頁', row('客廳', '位置 1', actions('<button disabled>↑</button>', link('↓ 下移', 'Buttons/Index', 'order-moved'))) + row('工作室', '位置 2', link('↑ 上移', 'Buttons/Index', 'order-moved')) + actions(link('取消', 'Buttons/Index'), link('儲存', 'Buttons/Index', style='button primary')), 'Buttons/Index'))
add('Buttons/Index', '按鈕', buttons_body(), 'order-moved', '分頁順序調整', modal=dialog('排序分頁', row('工作室', '位置 1', link('↓ 下移', 'Buttons/Index', 'order-pages')) + row('客廳', '位置 2', link('↑ 上移', 'Buttons/Index', 'order-pages')) + actions(link('取消', 'Buttons/Index'), link('儲存', 'Buttons/Index', style='button primary')), 'Buttons/Index'))
for state, title, text, target in [('delete-page', '刪除分頁', '確定要刪除「客廳」？此操作無法復原。', 'empty'), ('delete-button', '刪除按鈕', '確定要刪除這個按鈕？', 'editing')]:
    add('Buttons/Index', '按鈕', buttons_body(state == 'delete-button'), state, title, '原始碼使用 window.confirm；此框表示原生確認視窗。', dialog(title, '<p>' + text + '</p>' + actions(link('取消', 'Buttons/Index'), link('確定刪除', 'Buttons/Index', target, 'button danger')), 'Buttons/Index'))
job_labels = [('queued', '等待設備取得任務'), ('running', '設備執行中'), ('succeeded', '執行成功'), ('failed', '執行失敗'), ('device_offline', '設備已離線'), ('timed_out', '任務已逾時'), ('unauthorized', '無權限'), ('canceled', '已取消'), ('frontend-timeout', '前端等待逾時')]
for status, label in job_labels:
    running = status in ['queued', 'running']
    progress = 45 if status == 'running' else 0 if status == 'queued' else 100
    job = f'<strong>任務狀態：{label}</strong><div class="progress" aria-label="任務進度 {progress}%"><span style="width:{progress}%"></span></div><p>{progress}%</p>'
    job += '<p>等待已解除，設備仍可稍後回報結果。</p>' if status == 'frontend-timeout' else ''
    job += '<p>任務執行中，請等待完成或逾時。</p>' if running else link('關閉', 'Buttons/Index')
    board = buttons_body()
    if running:
        board = board.replace('<a class="tile', '<a aria-disabled="true" tabindex="-1" class="tile unavailable').replace('href="buttons-index--job-running.html"', '')
    add('Buttons/Index', '按鈕', notice(job, not running and status != 'succeeded') + board, 'job-' + status, '任務：' + label, 'queued／running 時鎖住按鈕操作；前端等待逾時與後端 timed_out 為不同畫面。')

# Shared asynchronous states, shown once per representative shell, and empty lists.
for source, title in [('Rooms/Index', '房間管理'), ('Buttons/Index', '按鈕')]:
    add(source, title, notice('載入中…') + '<div class="panel" aria-busy="true"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>', 'loading', '資料載入中', '此畫面代表同版型的共通非同步載入狀態；灰條用於標示待載入區域。')
    add(source, title, notice('資料載入失敗，請重新整理後再試。', True), 'load-error', '資料載入失敗')
def site_header(guest=False):
    nav = ''.join(link(label, source, style='') for label, source in [('按鈕', 'Buttons/Index'), ('房間管理', 'Rooms/Index'), ('帳號管理', 'Account/Profile'), ('登入/註冊' if guest else '登出', 'Auth/Login')])
    return '<header class="site-header">' + link('Button Control Center', 'Home', style='brand') + '<nav class="site-nav" aria-label="主要導覽">' + nav + '</nav><div class="mobile-nav"><button data-toggle="mobile-menu" aria-controls="mobile-menu" aria-expanded="false">menu</button><nav id="mobile-menu" aria-label="行動主要導覽" hidden>' + nav + '</nav></div></header>'


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
        main = site_header(frame['source'] == 'Home') + main
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

- 包含首頁、登入／註冊、EMAIL 驗證、密碼重設、個人帳號、房間與按鈕操作。
- 保留房主／住戶／非成員、邀請／申請、設備操作與對話框。
- 按鈕任務涵蓋 queued、running、succeeded、failed、device_offline、timed_out、unauthorized、canceled 及前端等待逾時；queued／running 時鎖住其他按鈕操作。

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
