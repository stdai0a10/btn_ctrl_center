# 系統管理員身分簡易規劃

## 目的

設計 `system_admin` 身分，用於管理整個服務後台的高權限操作。

---

## 權限原則

### 系統管理員

系統管理員不與一般使用者帳號分離，而是使用同一套 `user` 帳號，透過 RBAC 判斷是否具有系統管理權限

系統管理員可以：

- 登入服務管理後台
- 查看管理後台首頁
- 查看所有使用者
- 查看單一使用者的
  - 詳細資料
  - 加入的房間
- 查看所有房間
- 查看單一房間的
  - 詳細資料
  - 使用者
- 管理服務管理員
  - 查看所有服務管理員
  - 查看單一服務管理員詳細資料
  - 授予使用者服務管理員身分
  - 撤銷使用者的服務管理員身分
- 查看所有審計資料
  - 後台登入失敗紀錄
  - 後台操作紀錄

### 管理系統管理員

不可透過 Web 後台建立、授予 `system_admin`。

當系統內沒有任何 `system_admin` 時，可透過 CLI 新增第一位 `system_admin`。

系統管理員數量：N >= 0, i.e. 可以不設定系統管理員

### RBAC 設計

#### 角色規則

- `user` 可以同時擁有多個角色，代表可以同時擁有：
  - `system_admin`
  - `service_manager`
- 是否能進入後台與執行後台功能，皆由 permission 判斷，不直接依賴 role 名稱

#### 系統管理員 Role

`system_admin`

#### 系統管理員預設 Permissions

`system_admin` 預設擁有以下 permissions：

```text
manage.access
manage.dashboard.view
manage.users.view
manage.users.detail
manage.rooms.view
manage.rooms.detail
manage.service_managers.view
manage.service_managers.detail
manage.service_managers.grant
manage.service_managers.revoke
audit.access
audit.login_failures.view
audit.manage_actions.view
```

### Permission 說明

| Permission | 說明 |
| --- | --- |
| `manage.access` | 可進入 `/manage` 後台 |
| `manage.dashboard.view` | 可查看管理員首頁 |
| `manage.users.view` | 可查看使用者列表 |
| `manage.users.detail` | 可查看使用者詳細資料 |
| `manage.rooms.view` | 可查看房間列表 |
| `manage.rooms.detail` | 可查看房間詳細資料 |
| `manage.service_managers.view` | 可查看服務管理員列表 |
| `manage.service_managers.detail` | 可查看服務管理員詳細資料 |
| `manage.service_managers.grant` | 授予使用者服務管理員身分 |
| `manage.service_managers.revoke` | 撤銷使用者的服務管理員身分 |
| `audit.access` | 可進入 `/manage/audit` 後台 |
| `audit.login_failures.view` | 可查看登入失敗紀錄，包含詳細資料 |
| `audit.manage_actions.view` | 可查看管理者操作紀錄，包含詳細資料 |

---

## 系統管理員管理方式

系統管理員本身只能透過 CLI 管理。

後台頁面不可新增、刪除或修改 `system_admin` 身分。

### CLI 功能

```text
php artisan system-admin:list
php artisan system-admin:add {user_public_id}
php artisan system-admin:remove {user_public_id}
```

### 功能說明

#### 系統管理員列表

指令：`system-admin:list`

列表建議顯示：

|欄位|說明|
|---|---|
|使用者公開 ID|使用者的公開識別碼|
|使用者名稱|使用者名稱|
|Email|已驗證 Email|
|帳號狀態|正常、停用、刪除等|
|加入 `system_admin` 的時間|成為系統管理員的時間|
|最後登入時間|最近登入管理後台時間|

#### 新增系統管理員

指令：`system-admin:add {user_public_id}`

新增時應檢查：

- 使用者是否存在
- 使用者帳號是否啟用
- 使用者是否已經是 `system_admin`
- 寫入 `manage_action_logs`

#### 移除系統管理員

指令：`system-admin:remove {user_public_id}`

移除時應檢查：

- 使用者是否存在
- 使用者是否目前是 `system_admin`
- 允許移除最後一位 `system_admin`
- 寫入 `manage_action_logs`

---

## 服務管理員管理方式

服務管理員身分須由系統管理員透過 web 管理後台進行授權或是撤銷。

授予 `service_manager` 時應檢查：

- 目標使用者是否存在
- 目標使用者帳號是否啟用
- 目標使用者是否已經具有 `service_manager`，如果已經具有則忽略
- 操作者是否具有 `manage.service_managers.grant`
- 寫入 `manage_action_logs`
  - 若操作被忽略，仍應記錄 `manage_action_logs`，並在 metadata 中標記為 no-op。

撤銷 `service_manager` 時應檢查：

- 目標使用者是否存在
- 目標使用者是否目前具有 `service_manager`，如果已經撤銷則忽略
- 操作者是否具有 `manage.service_managers.revoke`
- 不得影響目標使用者的 `system_admin` 身分
- 寫入 `manage_action_logs`
  - 若操作被忽略，仍應記錄 `manage_action_logs`，並在 metadata 中標記為 no-op。

## 頁面規劃

系統管理員可瀏覽除了既有的

```text
/manage/login
/manage
/manage/users
/manage/users/{user_public_id}
/manage/rooms
/manage/rooms/{room_public_id}
```

還包括管理服務管理員

```text
/manage/service-managers
/manage/service-managers/{user_public_id}
```

以及審計資料

```text
/manage/audit
/manage/audit/login-failures
/manage/audit/manage-actions
```

已存在於 [docs/requirements/poc/03-service_manager.md](./03-service_manager.md) 裡的頁面，在這邊就不再贅述

### 服務管理員一覽頁

服務管理員列表頁。

#### 建議路徑

`/manage/service-managers`

#### 權限需求

訪問該頁面需要 `manage.service_managers.view`

#### 顯示內容

建議顯示欄位：

|欄位|說明|
|----|----|
|Checkbox|批次授權或撤銷|
|使用者公開 ID|使用者的公開識別碼|
|顯示名稱|使用者名稱|
|Email|已驗證 Email|
|帳號狀態|正常、停用、刪除等|
|是否為服務管理員|是否具有 `service_manager` role|
|建立時間|帳號建立時間|
|最近登入時間|最近登入時間|
|操作|查看詳細、授權、撤銷|

預設只顯示具有 `service_manager` role 的使用者。

不具有 `service_manager` role 的使用者只能透過搜尋尋找。

不得顯示任何登入憑證、Token、密碼 Hash 或第三方登入 access token。

#### 查詢功能

建議支援：

- 依使用者公開 ID 搜尋
- 依 Email 搜尋
- 依顯示名稱搜尋
- 依角色篩選
- 依帳號狀態篩選
- 分頁

### 服務管理員詳細頁

服務管理員詳細頁。

#### 建議路徑

`/manage/service-managers/{user_public_id}`

#### 權限需求

訪問該頁面需要 `manage.service_managers.detail`

#### 顯示內容

建議顯示：

- 使用者基本資料
  - 使用者公開 ID
  - 顯示名稱
  - Email
  - 帳號狀態
  - 建立時間
  - 最近登入時間
- 是否具有 `service_manager`
- 目前擁有的 roles
- 目前擁有的 permissions
- 最近管理後台登入紀錄
- 最近管理後台操作紀錄
- 最近服務管理員身分異動紀錄

不得顯示任何登入憑證、Token、密碼 Hash 或第三方登入 access token。

### 審計資料首頁

#### 建議路徑

`/manage/audit`

#### 權限需求

訪問該頁面需要 `audit.access`

#### 顯示內容

顯示各種審計資料的快速入口

### 登入失敗紀錄一覽頁

登入失敗紀錄列表頁。

註：目前不規劃獨立詳細頁，詳細資料可在列表展開或彈窗顯示。

#### 建議路徑

`/manage/audit/login-failures`

#### 權限需求

訪問該頁面需要 `audit.access` & `audit.login_failures.view`

#### 顯示內容

建議顯示：

|欄位|說明|
|----|----|
|時間|登入失敗時間|
|Email|嘗試登入時輸入的 Email|
|使用者公開 ID|若可識別使用者，顯示使用者公開 ID|
|IP|登入來源 IP|
|User-Agent|User-Agent|
|失敗原因|失敗原因|
|鎖定至|是否觸發暫時封鎖，以及解除時機|
|是否成功|是否成功，登入失敗頁通常顯示 false|

不得顯示任何登入憑證、Token、密碼 Hash 或第三方登入 access token。

#### 查詢功能

建議支援：

- 依 Email 搜尋
- 依 IP 搜尋
- 依使用者公開 ID 搜尋
- 依失敗原因篩選
- 依時間區間篩選
- 只顯示被暫時封鎖的紀錄
- 分頁

### 管理後台操作紀錄一覽頁

管理後台操作紀錄列表頁。

註：目前不規劃獨立詳細頁，詳細資料可在列表展開或彈窗顯示。

#### 建議路徑

`/manage/audit/manage-actions`

#### 權限需求

訪問該頁面需要 `audit.access` & `audit.manage_actions.view`

#### 顯示內容

建議顯示：

|欄位|說明|
|---|---|
|時間|發生時間|
|操作者|操作者 user public ID|
|Action|操作類型|
|Target Type|被操作對象類型|
|Target Public ID|被操作對象公開 ID|
|IP Address|操作者 IP|
|User-Agent|操作者 User-Agent|

#### 查詢功能

建議支援：

- 依操作者搜尋
- 依操作類型搜尋
- 依目標類型篩選
- 依目標公開 ID 搜尋
- 依 IP 搜尋
- 依時間區間篩選
- 分頁

---

## 開發建議

### 後台路徑

已存在 [docs/requirements/poc/03-service_manager.md](./03-service_manager.md) 裡的建議，在這邊就不再贅述

服務管理員管理

```text
/manage/service-managers
/manage/service-managers/{user_public_id}
```

審計資料首頁

```text
/manage/audit
```

登入失敗紀錄

```text
/manage/audit/login-failures
```

管理後台操作紀錄

```text
/manage/audit/manage-actions
```

### 建議 API

已存在 [docs/requirements/poc/03-service_manager.md](./03-service_manager.md) 裡的建議，在這邊就不再贅述

服務管理員管理

```text
GET /manage/api/service-managers
GET /manage/api/service-managers/{user_public_id}
POST /manage/api/service-managers/{user_public_id}/grant
POST /manage/api/service-managers/grant-many
POST /manage/api/service-managers/{user_public_id}/revoke
POST /manage/api/service-managers/revoke-many
```

登入失敗紀錄

```text
GET /manage/api/audit/login-failures
```

管理後台操作紀錄

```text
GET /manage/api/audit/manage-actions
```

### Middleware 建議

已存在 [docs/requirements/poc/03-service_manager.md](./03-service_manager.md) 裡的建議，在這邊就不再贅述

#### 頁面 Middleware

以下 頁面

```text
/manage/service-managers
/manage/service-managers/{user_public_id}
/manage/audit
/manage/audit/login-failures
/manage/audit/manage-actions
```

都必須經過管理權限檢查。

建議流程：

1. `auth`
2. `manage.authenticated`
3. `permission:manage.access`
4. `/manage/audit` 和 `/manage/audit/*` 頁面需要額外檢查 `permission:audit.access`

#### API Middleware

以下 API

```text
GET /manage/api/service-managers
GET /manage/api/service-managers/{user_public_id}
POST /manage/api/service-managers/{user_public_id}/grant
POST /manage/api/service-managers/grant-many
POST /manage/api/service-managers/{user_public_id}/revoke
POST /manage/api/service-managers/revoke-many
GET /manage/api/audit/login-failures
GET /manage/api/audit/manage-actions
```

都必須檢查

- 使用者已登入
- 已完成管理後台帳號密碼驗證
- `manage_authenticated_at` 仍在有效期限內
- `manage_authenticated_user_id` 是否與目前登入使用者 ID 相同
- 具有 `manage.access` 權限
- 具有該 API 需要的細部權限
- `/manage/api/audit/*` API 需要額外檢查 `audit.access` 權限

建議流程：

1. `auth`
2. `manage.authenticated`
3. `permission:manage.access`
4. `/manage/api/audit/*` API 需要額外檢查 `permission:audit.access`

並針對不同 API 再細分權限，例如：

- permission:manage.service_managers.view
- permission:manage.service_managers.detail
- permission:manage.service_managers.grant
- permission:manage.service_managers.revoke
- permission:audit.access
- permission:audit.login_failures.view
- permission:audit.manage_actions.view

### 資料模型建議

RBAC 模型由 `spatie/laravel-permission` 提供。

已存在 [docs/requirements/poc/03-service_manager.md](./03-service_manager.md) 裡的建議，在這邊就不再贅述

#### manage_login_logs

登入相關紀錄使用 `manage_login_logs`

#### manage_action_logs

操作相關紀錄使用 `manage_action_logs`

如果要讓它更適合作為 audit 資料來源，建議稍微補強欄位：

```text
id
actor_type
actor_user_id nullable
action
target_type
target_id nullable
target_public_id nullable
ip_address nullable
user_agent nullable
metadata
created_at
```

`actor_type` 建議值

- `manage_user`
- `cli`
- `system`

`actor_user_id` 在 `actor_type = cli` 時可為 null，並應在 `metadata` 中記錄 command 名稱與執行環境資訊。

`action` 建議使用固定字串，避免直接使用畫面文字。建議值：

- `system_admin.grant`
- `system_admin.revoke`
- `service_manager.grant`
- `service_manager.revoke`
- `users.detail.view`
- `rooms.detail.view`
- `audit.login_failures.view`
- `audit.manage_actions.view`

`metadata` 建議

```json
// CLI 操作範例
{
  "command": "system-admin:add",
  "before": {
    "roles": []
  },
  "after": {
    "roles": ["system_admin"]
  }
}
```

## Phase 分割建議

### Phase 1：RBAC 與系統管理員基礎設定

目標是建立 `system_admin` 身分所需的角色與權限基礎。

包含：

- 建立或確認 `system_admin` role
- 建立或確認以下 permissions：
  - `manage.access`
  - `manage.dashboard.view`
  - `manage.users.view`
  - `manage.users.detail`
  - `manage.rooms.view`
  - `manage.rooms.detail`
  - `manage.service_managers.view`
  - `manage.service_managers.detail`
  - `manage.service_managers.grant`
  - `manage.service_managers.revoke`
  - `audit.access`
  - `audit.login_failures.view`
  - `audit.manage_actions.view`
- 將上述 permissions 指派給 `system_admin`
- 確認 `user` 可以同時擁有：
  - `system_admin`
  - `service_manager`
- 確認是否能進入後台與執行功能皆由 permission 判斷
- 確認不直接依賴 role 名稱判斷後台功能權限

完成條件：

- `system_admin` role 可正常建立
- `system_admin` 預設擁有文件中定義的所有 permissions
- 同一個 user 可同時擁有 `system_admin` 與 `service_manager`
- 具備 `manage.access` 的使用者可通過既有管理後台登入流程

---

### Phase 2：系統管理員 CLI 管理

目標是完成 `system_admin` 的 CLI 管理功能，並確保 Web 後台不可管理 `system_admin`。

包含：

- 建立系統管理員列表指令：

```text
php artisan system-admin:list
````

- 建立新增系統管理員指令：

```text
php artisan system-admin:add {user_public_id}
```

- 建立移除系統管理員指令：

```text
php artisan system-admin:remove {user_public_id}
```

- `system-admin:list` 顯示：

  - 使用者公開 ID
  - 使用者名稱
  - Email
  - 帳號狀態
  - 加入 `system_admin` 的時間
  - 最後登入管理後台時間
- `system-admin:add {user_public_id}` 檢查：

  - 使用者是否存在
  - 使用者帳號是否啟用
  - 使用者是否已經是 `system_admin`
  - 寫入 `manage_action_logs`
- `system-admin:remove {user_public_id}` 檢查：

  - 使用者是否存在
  - 使用者是否目前是 `system_admin`
  - 允許移除最後一位 `system_admin`
  - 寫入 `manage_action_logs`
- 確認 Web 後台不可建立、授予、撤銷或刪除 `system_admin`
- 確認當系統內沒有任何 `system_admin` 時，仍可透過 CLI 新增第一位 `system_admin`

完成條件：

- 可透過 CLI 列出所有 `system_admin`
- 可透過 CLI 授予指定 user `system_admin`
- 可透過 CLI 移除指定 user 的 `system_admin`
- CLI 操作會寫入 `manage_action_logs`
- Web 後台沒有任何可管理 `system_admin` 的操作入口

---

### Phase 3：`manage_action_logs` 補強

目標是讓 `manage_action_logs` 可作為系統管理員、服務管理員與審計功能的主要操作紀錄來源。

包含：

- 確認或補強 `manage_action_logs` 欄位：

```text
id
actor_type
actor_user_id nullable
action
target_type
target_id nullable
target_public_id nullable
ip_address nullable
user_agent nullable
metadata
created_at
```

- 支援 `actor_type`：

  - `manage_user`
  - `cli`
  - `system`
- 當 `actor_type = cli` 時：

  - `actor_user_id` 可為 null
  - `metadata` 應記錄 command 名稱與執行環境資訊
- `action` 使用固定字串，避免直接使用畫面文字
- 支援以下 action：

  - `system_admin.grant`
  - `system_admin.revoke`
  - `service_manager.grant`
  - `service_manager.revoke`
  - `users.detail.view`
  - `rooms.detail.view`
  - `audit.login_failures.view`
  - `audit.manage_actions.view`
- `metadata` 支援記錄操作前後狀態，例如：

```json
{
  "command": "system-admin:add",
  "before": {
    "roles": []
  },
  "after": {
    "roles": ["system_admin"]
  }
}
```

完成條件：

- CLI 操作可寫入 `manage_action_logs`
- Web 後台操作可寫入 `manage_action_logs`
- no-op 操作可在 `metadata` 中標記
- `/manage/audit/manage-actions` 可使用 `manage_action_logs` 作為資料來源

---

### Phase 4：服務管理員管理 API

目標是讓系統管理員可以透過 API 授予或撤銷 `service_manager` 身分。

包含：

- 建立服務管理員列表 API：

```text
GET /manage/api/service-managers
```

- 建立服務管理員詳細 API：

```text
GET /manage/api/service-managers/{user_public_id}
```

- 建立單筆授權 API：

```text
POST /manage/api/service-managers/{user_public_id}/grant
```

- 建立批次授權 API：

```text
POST /manage/api/service-managers/grant-many
```

- 建立單筆撤銷 API：

```text
POST /manage/api/service-managers/{user_public_id}/revoke
```

- 建立批次撤銷 API：

```text
POST /manage/api/service-managers/revoke-many
```

- 授予 `service_manager` 時檢查：

  - 目標使用者是否存在
  - 目標使用者帳號是否啟用
  - 目標使用者是否已經具有 `service_manager`
  - 如果已經具有則忽略
  - 操作者是否具有 `manage.service_managers.grant`
  - 寫入 `manage_action_logs`
  - 若操作被忽略，仍應記錄 `manage_action_logs`，並在 metadata 中標記為 no-op
- 撤銷 `service_manager` 時檢查：

  - 目標使用者是否存在
  - 目標使用者是否目前具有 `service_manager`
  - 如果已經撤銷則忽略
  - 操作者是否具有 `manage.service_managers.revoke`
  - 不得影響目標使用者的 `system_admin` 身分
  - 寫入 `manage_action_logs`
  - 若操作被忽略，仍應記錄 `manage_action_logs`，並在 metadata 中標記為 no-op

完成條件：

- 可查詢服務管理員列表
- 可查詢單一服務管理員詳細資料
- 可單筆授予 `service_manager`
- 可批次授予 `service_manager`
- 可單筆撤銷 `service_manager`
- 可批次撤銷 `service_manager`
- 授權與撤銷操作皆會寫入 `manage_action_logs`
- 撤銷 `service_manager` 不會影響目標使用者的 `system_admin` 身分

---

### Phase 5：服務管理員管理頁面

目標是建立系統管理員管理 `service_manager` 的 Web 後台頁面。

包含：

- 建立服務管理員一覽頁：

```text
/manage/service-managers
```

- 建立服務管理員詳細頁：

```text
/manage/service-managers/{user_public_id}
```

- `/manage/service-managers` 顯示欄位：
  - Checkbox
  - 使用者公開 ID
  - 顯示名稱
  - Email
  - 帳號狀態
  - 是否為服務管理員
  - 建立時間
  - 最近登入時間
  - 操作
- `/manage/service-managers` 預設只顯示具有 `service_manager` role 的使用者
- 不具有 `service_manager` role 的使用者只能透過搜尋尋找
- `/manage/service-managers` 支援：
  - 依使用者公開 ID 搜尋
  - 依 Email 搜尋
  - 依顯示名稱搜尋
  - 依角色篩選
  - 依帳號狀態篩選
  - 分頁
- `/manage/service-managers/{user_public_id}` 顯示：
  - 使用者基本資料
  - 是否具有 `service_manager`
  - 目前擁有的 roles
  - 目前擁有的 permissions
  - 最近管理後台登入紀錄
  - 最近管理後台操作紀錄
  - 最近服務管理員身分異動紀錄
- 不得顯示任何登入憑證、Token、密碼 Hash 或第三方登入 access token
- 頁面權限檢查：
  - `/manage/service-managers` 需要 `manage.service_managers.view`
  - `/manage/service-managers/{user_public_id}` 需要 `manage.service_managers.detail`

完成條件：

- 系統管理員可查看服務管理員列表
- 系統管理員可搜尋不具有 `service_manager` role 的使用者
- 系統管理員可查看單一服務管理員詳細資料
- 系統管理員可從頁面進行授權、撤銷、批次授權、批次撤銷
- 頁面不顯示任何敏感憑證資訊

---

### Phase 6：審計資料 API

目標是建立審計資料查詢 API，提供登入失敗紀錄與管理後台操作紀錄。

包含：

- 建立登入失敗紀錄 API：

```text
GET /manage/api/audit/login-failures
```

- 建立管理後台操作紀錄 API：

```text
GET /manage/api/audit/manage-actions
```

- `/manage/api/audit/login-failures` 支援：
  - 依 Email 搜尋
  - 依 IP 搜尋
  - 依使用者公開 ID 搜尋
  - 依失敗原因篩選
  - 依時間區間篩選
  - 只顯示被暫時封鎖的紀錄
  - 分頁
- `/manage/api/audit/manage-actions` 支援：
  - 依操作者搜尋
  - 依操作類型搜尋
  - 依目標類型篩選
  - 依目標公開 ID 搜尋
  - 依 IP 搜尋
  - 依時間區間篩選
  - 分頁
- `/manage/api/audit/*` API 需要額外檢查 `audit.access`
- 登入失敗紀錄需要 `audit.login_failures.view`
- 管理後台操作紀錄需要 `audit.manage_actions.view`

完成條件：

- 可查詢後台登入失敗紀錄
- 可查詢後台操作紀錄
- API 可正確套用 `manage.access`
- API 可正確套用 `audit.access`
- API 可正確套用細部 audit permission
- API 不回傳登入憑證、Token、密碼 Hash 或第三方登入 access token

---

### Phase 7：審計資料頁面

目標是建立系統管理員查看審計資料的 Web 後台頁面。

包含：

- 建立審計資料首頁：

```text
/manage/audit
```

- 建立登入失敗紀錄一覽頁：

```text
/manage/audit/login-failures
```

- 建立管理後台操作紀錄一覽頁：

```text
/manage/audit/manage-actions
```

- `/manage/audit` 顯示：
  - 登入失敗紀錄入口
  - 管理後台操作紀錄入口
- `/manage/audit/login-failures` 顯示欄位：
  - 時間
  - Email
  - 使用者公開 ID
  - IP
  - User-Agent
  - 失敗原因
  - 鎖定至
  - 是否成功
- `/manage/audit/manage-actions` 顯示欄位：
  - 時間
  - 操作者
  - Action
  - Target Type
  - Target Public ID
  - IP Address
  - User-Agent
- 登入失敗紀錄目前不規劃獨立詳細頁
- 管理後台操作紀錄目前不規劃獨立詳細頁
- 詳細資料可在列表展開或彈窗顯示
- 不得顯示任何登入憑證、Token、密碼 Hash 或第三方登入 access token
- 頁面權限檢查：
  - `/manage/audit` 需要 `audit.access`
  - `/manage/audit/login-failures` 需要 `audit.access` 與 `audit.login_failures.view`
  - `/manage/audit/manage-actions` 需要 `audit.access` 與 `audit.manage_actions.view`

完成條件：

- 系統管理員可進入 `/manage/audit`
- 系統管理員可查看登入失敗紀錄
- 系統管理員可查看管理後台操作紀錄
- 頁面可支援搜尋、篩選與分頁
- 頁面可展開或彈窗查看詳細資料
- 頁面不顯示任何敏感憑證資訊

---

### Phase 8：Middleware 與權限整合檢查

目標是確認所有新增頁面與 API 都正確套用管理後台登入狀態與細部權限。

包含：

- 以下頁面需通過共同管理權限檢查：

```text
/manage/service-managers
/manage/service-managers/{user_public_id}
/manage/audit
/manage/audit/login-failures
/manage/audit/manage-actions
```

- 頁面共同檢查流程：

```text
auth
manage.authenticated
permission:manage.access
```

- `/manage/audit` 和 `/manage/audit/*` 頁面需要額外檢查：

```text
permission:audit.access
```

- 以下 API 需通過共同管理權限檢查：

```text
GET /manage/api/service-managers
GET /manage/api/service-managers/{user_public_id}
POST /manage/api/service-managers/{user_public_id}/grant
POST /manage/api/service-managers/grant-many
POST /manage/api/service-managers/{user_public_id}/revoke
POST /manage/api/service-managers/revoke-many
GET /manage/api/audit/login-failures
GET /manage/api/audit/manage-actions
```

- API 共同檢查流程：

```text
auth
manage.authenticated
permission:manage.access
```

- `/manage/api/audit/*` API 需要額外檢查：

```text
permission:audit.access
```

- 各頁面與 API 需再依功能檢查細部權限：

  - `manage.service_managers.view`
  - `manage.service_managers.detail`
  - `manage.service_managers.grant`
  - `manage.service_managers.revoke`
  - `audit.access`
  - `audit.login_failures.view`
  - `audit.manage_actions.view`

完成條件：

- 未登入者不可存取新增頁面與 API
- 未完成管理後台登入驗證者不可存取新增頁面與 API
- 沒有 `manage.access` 者不可存取新增頁面與 API
- 沒有 `audit.access` 者不可存取 `/manage/audit`、`/manage/audit/*` 與 `/manage/api/audit/*`
- 沒有細部 permission 者不可執行對應功能

---

### Phase 9：整合測試與安全檢查

目標是確認 `system_admin`、`service_manager`、audit 與既有管理後台功能整合正確。

包含：

- 測試 `system_admin` 可進入管理後台
- 測試 `system_admin` 可查看既有管理頁面
- 測試 `system_admin` 可管理 `service_manager`
- 測試 `system_admin` 可查看審計資料
- 測試 `service_manager` 不可管理 `service_manager`
- 測試 `service_manager` 不可查看 `/manage/audit`
- 測試一般使用者不可進入管理後台
- 測試同一 user 同時具有 `system_admin` 與 `service_manager` 時權限合併正常
- 測試移除 `service_manager` 不會影響 `system_admin`
- 測試 CLI 可在沒有任何 `system_admin` 時建立第一位 `system_admin`
- 測試 CLI 可移除最後一位 `system_admin`
- 測試所有新增頁面與 API 不顯示敏感憑證資訊
- 測試所有授權、撤銷、no-op、CLI 操作皆會寫入 `manage_action_logs`

完成條件：

- RBAC 權限判斷符合文件規劃
- CLI 管理流程符合文件規劃
- Web 後台不可管理 `system_admin`
- Web 後台可管理 `service_manager`
- 審計資料可正確查詢
- 敏感資料不會出現在頁面或 API response
- 操作紀錄可追蹤授權、撤銷、CLI 與 no-op 行為
