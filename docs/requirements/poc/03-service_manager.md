# 服務管理頁面功能

## 目的

提供僅限服務管理員使用的後台頁面，用於查看與管理服務內的使用者、房間與其關聯資料。

本功能與一般使用者前台功能分離，所有頁面與 API 皆必須檢查服務管理員身分。

---

## 名詞調整

原本專案中提到的「房屋」一律改稱為「房間」。

例如：

- 房屋一覽 → 房間一覽
- 使用者已加入的房屋 → 使用者已加入的房間
- 房屋內的使用者 → 房間內的使用者

---

## 重要規則

雖然服務管理員帳號與一般使用者帳號不分離，但管理後台登入流程必須獨立檢查：

- 使用者輸入帳號與密碼
- 驗證帳號密碼正確
- 檢查該使用者是否具有 `manage.access` 權限
- 通過後建立管理後台登入狀態
- 導向管理後台首頁

若使用者沒有管理權限，即使帳號密碼正確，也不得進入後台。

---

## 權限原則

### 管理員身分

服務管理員不使用獨立帳號。

服務管理員是一般使用者帳號的一種權限身分，透過 RBAC 判斷。也就是說：

- 所有帳號都存在於一般使用者帳號資料表
- 是否能進入管理後台，由角色與權限決定

服務管理員可以：

- 登入服務管理後台
- 查看所有使用者
- 查看單一使用者加入的房間
- 查看所有房間
- 查看單一房間內的使用者

服務管理員身分是服務層級權限，與房間內的房主身分無關。

服務管理員的建立方式將會在其他文件裡說明。

### 一般使用者

一般使用者不得進入服務管理後台。

即使一般使用者是某個房間的房主，也不能操作服務管理頁面。

### RBAC 設計

#### 角色範例

|角色|說明|
|---|---|
|user|一般使用者，不可進入管理後台|
|service_manager|服務管理員，可操作管理後台|
|system_admin|系統管理員，擁有所有 `manage.*` 權限，並可管理服務管理員|

備註：本文件只規範 `service_manager` 的管理頁面權限，`system_admin` 的實際權限將會在其他文件裡說明。

#### 權限範例

|權限|說明|
|---|---|
|manage.access|可以進入管理後台|
|manage.dashboard.view|可以查看管理後台首頁的儀表板|
|manage.users.view|可以查看使用者一覽|
|manage.users.detail|可以查看單一使用者|
|manage.rooms.view|可以查看房間一覽|
|manage.rooms.detail|可以查看單一房間|

---

## 頁面規劃

### 服務管理登入頁

提供服務管理員登入後台。

#### 建議路徑

`/manage/login`

#### 登入方式

只能使用帳號密碼登入。

若使用者帳號沒有設定密碼，即使具有管理權限，也不得登入管理後台。

使用者必須完成管理後台帳號密碼驗證，系統會在目前登入 Session 中記錄管理後台登入狀態，例如 `manage_authenticated_at`、`manage_authenticated_user_id`。
若前端使用 Web / Inertia 架構，管理後台 API 應使用 Session-based authentication 與 CSRF 保護，不需要額外發放長期 API token。

管理後台登入成功後，應重新產生 Session ID，以降低 Session Fixation 風險。

不得僅因使用者已登入前台，就允許進入管理後台。

不得使用：

- LINE 登入 / LIFF 登入
- Email magic link
- 第三方 OAuth 登入

#### 欄位

|欄位|說明|
|----|----|
|帳號|必填，填寫Email|
|密碼|使用者密碼|

#### 登入檢查

登入時必須檢查：

- 帳號是否存在
- 帳號是否已設定密碼
- 密碼是否正確
- 帳號是否啟用
- 使用者是否具有 `manage.access` 權限
- 是否觸發登入失敗限制

#### 登入限制

管理後台登入應加入：

- 帳號密碼錯誤次數限制
- IP rate limit
- 帳號 rate limit
- 登入失敗紀錄
- 登入成功紀錄

#### 登入成功後

導向服務管理首頁。

#### 登入有效期限

管理後台登入狀態有效期限為自登入起算 30 分鐘，不因使用者操作而延長。

若超過有效期限，管理後台登入狀態失效，使用者將被導回 `/manage/login`，需要重新輸入帳號密碼才能繼續操作 `/manage/*`。

#### 登入失敗

- 不回傳精確錯誤原因
- 超過 rate limit 後暫時封鎖

#### 登出

管理後台登出僅清除管理後台登入狀態，不一定登出一般前台登入狀態。

登出時應清除：

- `manage_authenticated_at`
- `manage_authenticated_user_id`

清除後導向 `/manage/login`。

### 管理後台首頁

管理員登入後的首頁。

此頁面作為管理後台入口，提供各管理功能的快速連結與服務概況。

#### 建議路徑

`/manage`

#### 權限需求

- 進入管理後台首頁：`manage.access`
- 查看服務概況資料：`manage.dashboard.view`

#### 顯示內容

建議顯示：

- 快速入口
  - 使用者一覽
  - 房間一覽
- 服務概況，可顯示：
  - 使用者總數
  - 房間總數
  - 今日新增使用者數
  - 今日新增房間數
  - 最近登入管理後台時間
- 權限資訊，可顯示目前登入管理員的：
  - 使用者公開 ID
  - 顯示名稱
  - 管理角色
  - 可用權限

### 使用者一覽頁

查看服務中所有使用者。

#### 建議路徑

`/manage/users`

#### 權限需求

`manage.users.view`

#### 顯示內容

建議顯示：

|欄位|說明|
|----|----|
|使用者公開 ID|使用者的公開識別碼|
|顯示名稱|使用者名稱|
|Email|已驗證 Email|
|LINE 綁定狀態|是否已綁定 LINE|
|建立時間|帳號建立時間|
|最近登入時間|最近登入時間|
|狀態|正常、停用、刪除等|

不得顯示任何登入憑證、Token、密碼 Hash 或第三方登入 access token。

#### 查詢功能

建議支援：

- 依公開 ID 搜尋
- 依 Email 搜尋
- 依顯示名稱搜尋
- 依狀態篩選
- 分頁

### 單一使用者詳細頁

點擊使用者後可查看該使用者詳細資料。

#### 建議路徑

`/manage/users/{user_public_id}`

#### 權限需求

`manage.users.detail`

#### 顯示內容

建議顯示：

- 使用者基本資料
  - 使用者公開 ID
  - 顯示名稱
  - Email
  - LINE 綁定狀態
  - 帳號狀態
  - 建立時間
  - 最近登入時間
- 使用者目前所有權限
- 已加入的房間列表
  - 房間公開 ID
  - 房間名稱
  - 房間內的身分
    - 住戶
    - 房主
  - 加入時間

不得顯示任何登入憑證、Token、密碼 Hash 或第三方登入 access token。

### 房間一覽頁

查看服務中所有房間。

#### 建議路徑

`/manage/rooms`

#### 權限需求

`manage.rooms.view`

#### 顯示內容

建議顯示：

|欄位|說明|
|----|----|
|房間公開 ID|房間的公開識別碼|
|房間名稱|房間名稱|
|建立者|建立房間的使用者|
|建立時間|房間建立時間|
|成員數量|房間內使用者數量|
|房主數量|房間內房主數量|
|狀態|正常、已刪除等|

#### 查詢功能

建議支援：

- 依房間公開 ID 搜尋
- 依房間名稱搜尋
- 依建立者搜尋
- 依狀態篩選
- 分頁

### 單一房間詳細頁

點擊房間後可查看該房間詳細資料。

#### 建議路徑

`/manage/rooms/{room_public_id}`

#### 權限需求

`manage.rooms.detail`

#### 顯示內容

建議顯示：

- 房間基本資料
  - 房間公開 ID
  - 房間名稱
  - 建立者
  - 建立時間
  - 房間狀態
  - 成員數量
  - 房主數量
- 房間內的使用者列表
  - 使用者公開 ID
  - 顯示名稱
  - Email
  - 房間內身分
    - 住戶
    - 房主
  - 加入時間

---

## 開發建議

### 後台路徑

服務管理員登入

```text
/manage/login
```

管理後台首頁

```text
/manage
```

使用者管理

```text
/manage/users
/manage/users/{user_public_id}
```

房間管理

```text
/manage/rooms
/manage/rooms/{room_public_id}
```

### 建議 API

服務管理員登入登出

```text
POST /manage/api/login
POST /manage/api/logout
```

管理後台首頁

```text
GET /manage/api/me
GET /manage/api/dashboard
```

使用者管理

```text
GET /manage/api/users
GET /manage/api/users/{user_public_id}
GET /manage/api/users/{user_public_id}/rooms
```

房間管理

```text
GET /manage/api/rooms
GET /manage/api/rooms/{room_public_id}
GET /manage/api/rooms/{room_public_id}/users
```

### RBAC 權限設計建議

由 `spatie/laravel-permission` 提供。

角色與權限的 `guard_name` 應與系統登入 guard 保持一致，例如 `web`。

### Middleware 建議

#### 頁面 Middleware

所有 `/manage/*` 頁面，除了 `/manage/login` 以外，都必須經過管理權限檢查。

建議流程：

1. `auth`
2. `manage.authenticated`
3. `permission:manage.access`

其中 `manage.authenticated` 負責檢查：

- 是否已登入
- 是否具有管理登入狀態
- `manage_authenticated_at` 是否有效
- `manage_authenticated_user_id` 是否與目前登入使用者 ID 相同
- 不符合時導向 `/manage/login`

#### API Middleware

所有 `/manage/api/*`，除了 `POST /manage/api/login` 以外，都必須檢查：

- 使用者已登入
- 已完成管理後台帳號密碼驗證
- `manage_authenticated_at` 仍在有效期限內
- `manage_authenticated_user_id` 是否與目前登入使用者 ID 相同
- 具有 `manage.access` 權限
- 具有該 API 需要的細部權限

建議流程：

1. `auth`
2. `manage.authenticated`
3. `permission:manage.access`

針對不同 API 再細分權限，例如：

- `permission:manage.dashboard.view`
- `permission:manage.users.view`
- `permission:manage.users.detail`
- `permission:manage.rooms.view`
- `permission:manage.rooms.detail`

### Session 設計建議

雖然服務管理員與一般使用者使用同一個帳號系統，但建議管理後台仍保留獨立的管理登入狀態。

原因：

- 避免一般前台登入後直接進入後台
- 管理後台可以要求重新輸入密碼
- 管理後台可以有較短的閒置逾時
- 管理後台可以有更嚴格的安全紀錄

建議做法：

- 一般登入狀態：代表使用者已登入前台
- 管理登入狀態：代表使用者已通過管理後台帳號密碼驗證

例如可在 Session 中記錄：`manage_authenticated_at`、`manage_authenticated_user_id`

進入 /manage/* 時檢查：

- 使用者是否登入
- 是否具有 `manage.access`
- 是否已完成管理後台密碼驗證
- `manage_authenticated_at` 是否仍在有效期限內
- `manage_authenticated_user_id` 是否與目前登入使用者 ID 相同

### 後台安全建議

#### 後台登入限制

管理後台登入應加入：

- 帳號密碼錯誤次數限制
- IP rate limit
- 帳號 rate limit
- 登入失敗紀錄
- 登入成功紀錄
- 權限檢查

不能只依靠前端隱藏頁面。

所有 `/manage/api/*` API，除了 `POST /manage/api/login` 以外，都必須在後端檢查權限。

#### 管理後台重新驗證

即使使用者已在前台登入，進入 /manage 時仍應要求重新輸入密碼。

這樣可以確保管理後台符合「只能使用帳號密碼登入」的要求。

### 資料模型建議

#### users

使用既有使用者資料表。

#### roles

角色資料表。由 `spatie/laravel-permission` 提供。

#### permissions

權限資料表。由 `spatie/laravel-permission` 提供。

#### role_has_permissions

角色與權限關聯表。由 `spatie/laravel-permission` 提供。

#### model_has_roles

使用者與角色關聯表。由 `spatie/laravel-permission` 提供。

#### model_has_permissions

使用者與權限關聯表。由 `spatie/laravel-permission` 提供。

#### manage_login_logs

```text
id
user_id nullable
email
ip_address
user_agent
success
failure_reason
locked_until nullable  用於記錄該次登入嘗試後，是否觸發暫時封鎖，以及封鎖到何時
created_at
```

#### manage_action_logs

管理後台操作紀錄。

```text
id
user_id
action
target_type
target_id nullable
target_public_id nullable
ip_address
user_agent
metadata
created_at
```

## Phase 分割建議

### Phase 1：RBAC 與管理後台登入

目標是先建立管理後台的權限基礎。

包含：

- 安裝並設定 `spatie/laravel-permission`
- 建立角色
  - `service_manager`
  - `system_admin` (如果不存在)
- 建立管理後台需要的基礎權限
  - `manage.access`
  - `manage.dashboard.view`
  - `manage.users.view`
  - `manage.users.detail`
  - `manage.rooms.view`
  - `manage.rooms.detail`
- 將服務管理權限指派給 `service_manager` 和 `system_admin`
- 建立 `/manage/login`
- 實作管理後台帳號密碼登入
- 登入成功後重新產生 Session ID
- 登入成功後記錄：
  - `manage_authenticated_at`
  - `manage_authenticated_user_id`
- 登入後導向 `/manage`

### Phase 2：管理後台首頁

目標是建立管理員登入後的入口頁。

包含：

- 建立 `/manage`
- 顯示使用者總數
- 顯示房間總數
- 顯示快速入口
- 顯示目前管理員資訊

### Phase 3：使用者一覽與使用者詳細頁

目標是讓管理員可以查看使用者資料。

包含：

- 建立 `/manage/users`
- 建立 `/manage/users/{user_public_id}`
- 支援搜尋
- 支援分頁
- 查看單一使用者已加入的房間

### Phase 4：房間一覽與房間詳細頁

目標是讓管理員可以查看房間資料。

包含：

- 建立 `/manage/rooms`
- 建立 `/manage/rooms/{room_public_id}`
- 支援搜尋
- 支援分頁
- 查看單一房間內的使用者

### Phase 5：管理後台稽核紀錄

目標是提高後台安全性與可追蹤性。

包含：

- 管理後台登入紀錄
- 管理後台操作紀錄
- 權限異動紀錄
- 查看登入失敗紀錄
