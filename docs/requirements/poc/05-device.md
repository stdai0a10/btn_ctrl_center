# 設備主檔與管理後台開發規劃

## 目的

建立設備主檔管理功能，讓系統先在資料庫登錄實體設備，再由客戶使用設備上的序號與隱碼將設備加入房間。

本功能分為兩個範圍：

- 管理後台
  - 服務管理員可以查看所有設備及設備所在房間。
  - 系統管理員可以建立新的設備主檔。
- 客戶前台
  - 房間成員可以查看房間內的設備。
  - 房主可以使用序號與隱碼，將資料庫中已存在的設備加入房間。

---

## 名詞定義

### 設備主檔

代表系統已知的實體設備，儲存於 `devices` 資料表。

設備主檔由系統管理員建立，不代表設備已被任何使用者或房間持有。

### 未加入房間

設備的 `current_room_id` 為 `null`。

剛由系統管理員建立的設備必須處於此狀態：

- 不屬於任何房間。
- 不存在個人持有者。
- 沒有房間內名稱。
- 預設未上鎖。
- 預設啟用。

### 已加入房間

設備的 `current_room_id` 指向一間房間。

設備同一時間只能加入一間房間。設備與使用者之間不建立直接持有關係；使用者是否能查看或管理設備，由使用者與設備所在房間的關係決定。

---

## 核心規則

1. 每部設備具有全域唯一的序號。
2. 每部設備具有隱碼。
3. 隱碼只能在以下流程輸入：
   - 系統管理員建立設備主檔。
   - 房主將設備加入房間。
4. 隱碼只能以 hash 形式儲存。
5. 所有頁面、列表、詳細 API、log 與 error response 均不得回傳：
   - 隱碼明文。
   - `secret_hash`。
6. 服務管理員可以查看所有設備，但不能建立設備主檔。
7. 系統管理員可以查看所有設備及建立設備主檔。
8. 一般使用者不得存取管理後台設備 API。
9. 房間成員只能查看自己已加入房間內的設備。
10. 只有房主可以將設備加入房間。
11. 客戶將設備加入房間時，設備必須已存在於 `devices`。
12. 系統不得因客戶輸入未知序號而自動建立設備主檔。
13. 設備移轉、鎖定、解鎖與移除規則沿用 `.data/Story-Room.md`。
14. 設備序號一律執行 `mb_strtoupper(trim($serialNumber))` 後再儲存或查詢。
15. 房間成員可以查看設備啟用狀態，只有房主可以啟用或停用設備。
16. 設備移轉或解除房間時，`is_enabled` 重設為 `true`。

---

## 實作決策與限制

以下為本文件的強制決策，不得在實作時改採其他方案：

1. 序號一律去除前後空白並轉為大寫。
2. 管理後台房間設備使用獨立 API：

   ```http
   GET /manage/api/rooms/{room_public_id}/devices
   ```

3. 權限不足統一由 Laravel permission middleware 回傳 HTTP 403，不建立 `DEVICE_CREATE_FORBIDDEN`。
4. 本次開發必須包含設備啟用狀態 `is_enabled`。
5. 必須沿用現有專案架構與元件，只能擴充，不得重寫既有房間設備流程：
   - `App\Models\Device`
   - `App\Services\DeviceService`
   - `App\Http\Controllers\DeviceController`
   - `App\Services\Manage\ManageActionLogger`
   - `App\Support\ManagementRbac`
   - 既有 `ApiController` response 格式
   - Inertia + React
   - 既有管理後台 Layout 與 CSS
6. 設備移轉紀錄 API 依 `created_at DESC` 排序，每頁 20 筆。
7. 不記錄設備列表的每次搜尋、篩選或換頁，只記錄設備建立、設備詳細查看及房間設備查看。
8. 一般 feature tests 使用 SQLite；真正的 row lock 與同時移轉測試使用 MySQL integration test。
9. 若 CI 沒有 MySQL，併發測試可放入獨立 test group，但正式驗收前必須在 MySQL 執行。
10. 每個 Phase 完成後必須：
    - 執行該 Phase 相關測試。
    - 建立獨立 commit。
    - commit message 使用 `Phase N: ...` 格式。
11. 最後一個 Phase 必須執行完整 `php artisan test` 與 `npm run build`。

---

## 權限設計

### 房間權限

沿用既有 Room Policy：

|操作|權限|
|---|---|
|查看房間設備|房間成員|
|將設備加入房間|房主|
|修改設備房間內名稱|房主|
|啟用或停用設備|房主|
|鎖定或解鎖設備|房主|
|從房間移除設備|房主|

### 管理後台權限

新增 permissions：

|Permission|說明|預設角色|
|---|---|---|
|`manage.devices.view`|查看設備列表|`service_manager`、`system_admin`|
|`manage.devices.detail`|查看設備詳細資料|`service_manager`、`system_admin`|
|`manage.devices.create`|建立設備主檔|`system_admin`|

角色規則：

- `service_manager`
  - 擁有 `manage.devices.view`。
  - 擁有 `manage.devices.detail`。
  - 不擁有 `manage.devices.create`。
- `system_admin`
  - 擁有以上全部設備 permissions。
- 是否能執行功能一律由 permission 判斷，不直接在 Controller 判斷 role 名稱。

目前不規劃透過 Web 後台：

- 刪除設備主檔。
- 修改設備序號。
- 查看隱碼或 `secret_hash`。
- 將設備強制加入或移出房間。
- 變更既有設備的隱碼。

---

## 資料模型

### devices

沿用既有資料表：

```text
id
serial_number
secret_hash
current_room_id nullable
name nullable
is_locked
is_enabled
created_at
updated_at
```

欄位說明：

|欄位|說明|
|---|---|
|`serial_number`|設備實體上標示的全域唯一序號|
|`secret_hash`|設備隱碼的不可逆 hash|
|`current_room_id`|目前所在房間；`null` 表示尚未加入房間|
|`name`|設備加入房間後，由房主設定的房間內名稱|
|`is_locked`|是否禁止移除或轉移|
|`is_enabled`|設備是否啟用；新設備預設為 `true`|

資料庫約束：

- `serial_number` 必須具有 unique index。
- `current_room_id` 可為 `null`。
- 房間被刪除或設備被解除時，`current_room_id` 設為 `null`。
- `secret_hash` 不得為 `null`。
- `is_enabled` 不得為 `null`，預設值為 `true`。

序號正規化規則：

- 一律使用 `mb_strtoupper(trim($serialNumber))`。
- 建立設備、查詢設備及將設備加入房間必須共用同一個 normalizer。
- 資料庫只儲存正規化後的序號。

### device_transfer_logs

沿用既有設備移轉紀錄：

```text
id
device_id
from_room_id nullable
to_room_id nullable
transferred_by_user_id
from_room_public_id_snapshot nullable
to_room_public_id_snapshot nullable
transferred_by_user_public_id_snapshot
created_at
```

系統管理員建立設備主檔時不建立 transfer log，因為設備尚未加入房間。

設備第一次加入房間時：

- `from_room_id = null`
- `to_room_id = 目標房間 ID`

snapshot 欄位用於在房間或使用者日後被刪除時保留稽核識別資料。新寫入的移轉紀錄必須同時保存當時的房間 public ID 與操作者 public ID。

---

## 後端模組建議

### 客戶設備服務

沿用：

```text
App\Services\DeviceService
```

負責：

- 驗證設備是否存在。
- 驗證隱碼。
- 將設備加入房間。
- 設備移轉。
- 修改房間內設備名稱。
- 啟用與停用設備。
- 鎖定與解鎖。
- 從房間移除。

### 管理後台設備服務

新增：

```text
App\Services\Manage\DeviceCatalogService
```

負責：

- 正規化設備序號。
- 檢查序號是否重複。
- hash 隱碼。
- 建立未加入房間的設備主檔。
- 寫入管理後台操作紀錄。

建立設備應使用 transaction，並由資料庫 unique constraint 作為最終的重複防護。

序號 unique constraint 衝突必須轉換為 `DEVICE_SERIAL_ALREADY_EXISTS`，不得直接將資料庫 exception 回傳給客戶端。

### 管理後台 Controller

新增：

```text
App\Http\Controllers\Manage\DeviceController
```

負責：

- 設備列表。
- 設備詳細資料。
- 建立設備主檔。

Controller 不應直接處理隱碼 hash 或建立 audit log，應交由 service。

---

## 管理後台頁面

### 設備一覽頁

建議路徑：

```text
/manage/devices
```

權限：

```text
manage.devices.view
```

顯示欄位：

|欄位|說明|
|---|---|
|序號|設備序號|
|房間狀態|已加入房間或未加入|
|所在房間公開 ID|未加入時顯示 `-`|
|所在房間名稱|未加入時顯示 `-`|
|房間內設備名稱|未命名時顯示 `-`|
|鎖定狀態|已上鎖或未上鎖|
|啟用狀態|已啟用或已停用|
|建立時間|設備主檔建立時間|
|更新時間|最近更新時間|

查詢功能：

- 依設備序號搜尋。
- 依房間公開 ID 搜尋。
- 依房間名稱搜尋。
- 依房間內設備名稱搜尋。
- 依加入狀態篩選：
  - 全部。
  - 已加入房間。
  - 未加入房間。
- 依鎖定狀態篩選。
- 依啟用狀態篩選。
- 分頁。

操作：

- 所有具 `manage.devices.detail` 的管理員可以查看詳細資料。
- 只有具 `manage.devices.create` 的管理員顯示「新增設備」按鈕。

不得顯示隱碼或 `secret_hash`。

### 設備詳細頁

建議路徑：

```text
/manage/devices/{serial_number}
```

權限：

```text
manage.devices.detail
```

顯示內容：

- 設備序號。
- 是否已加入房間。
- 房間內設備名稱。
- 鎖定狀態。
- 啟用狀態。
- 建立時間。
- 更新時間。
- 目前房間：
  - 房間公開 ID。
  - 房間名稱。
  - 房間狀態。
  - 連結至 `/manage/rooms/{room_public_id}`。
- 最近設備移轉紀錄：
  - 時間。
  - 原房間公開 ID。
  - 目標房間公開 ID。
  - 操作者使用者公開 ID。

移轉紀錄：

- 依 `created_at DESC` 排序。
- 每頁 20 筆。
- 使用 snapshot public ID 顯示已刪除房間或使用者的歷史識別資料。
- API 必須支援分頁。

不得顯示隱碼或 `secret_hash`。

### 新增設備頁

建議路徑：

```text
/manage/devices/create
```

權限：

```text
manage.devices.create
```

欄位：

|欄位|規則|
|---|---|
|序號|必填、正規化後全域唯一|
|隱碼|必填，不可回顯|
|確認隱碼|必填，必須與隱碼相同|

建立成功後：

- `current_room_id = null`
- `name = null`
- `is_locked = false`
- `is_enabled = true`
- 隱碼以 hash 儲存。
- 寫入 `manage_action_logs`。
- 導向設備詳細頁或顯示成功結果。

重複序號時：

- 不建立設備。
- 回傳明確的序號重複錯誤。
- 不在錯誤 response 中包含隱碼。

---

## 房間詳細頁整合

### 客戶前台

既有頁面：

```text
/rooms/{room_public_id}
```

所有房間成員均可看到房間內設備：

- 設備序號。
- 房間內設備名稱。
- 鎖定狀態。
- 啟用狀態。
- 其他未來允許房間成員查看的設備狀態。

只有房主顯示設備管理操作：

- 加入設備。
- 修改名稱。
- 啟用或停用。
- 鎖定或解鎖。
- 移除設備。

不得顯示：

- 隱碼。
- `secret_hash`。
- 不屬於該房間的設備。

### 管理後台

既有頁面：

```text
/manage/rooms/{room_public_id}
```

新增房間設備區塊，顯示：

- 設備序號。
- 房間內設備名稱。
- 鎖定狀態。
- 啟用狀態。
- 加入或最近移轉時間。
- 設備詳細頁連結。

服務管理員只能查看，不得從房間詳細頁直接加入、移除、移轉、鎖定或解鎖設備。

---

## API 規劃

### 客戶 API

沿用既有 API：

```http
GET    /api/rooms/{room}/devices
POST   /api/rooms/{room}/devices
PATCH  /api/rooms/{room}/devices/{device}
DELETE /api/rooms/{room}/devices/{device}
POST   /api/rooms/{room}/devices/{device}/lock
POST   /api/rooms/{room}/devices/{device}/unlock
POST   /api/rooms/{room}/devices/{device}/enable
POST   /api/rooms/{room}/devices/{device}/disable
```

啟用與停用端點：

- 只有房主可以呼叫。
- 設備必須屬於 URL 指定的房間。
- `enable` 將 `is_enabled` 設為 `true`。
- `disable` 將 `is_enabled` 設為 `false`。
- 重複啟用或停用採 idempotent 行為，回傳成功且維持既有狀態。

#### 將設備加入房間

```http
POST /api/rooms/{room}/devices
```

Request：

```json
{
  "serial_number": "DEVICE-000001",
  "secret": "physical-device-secret",
  "name": "客廳按鈕",
  "lock": false
}
```

處理順序：

1. 確認操作者是房主。
2. 使用共用 normalizer 執行 `mb_strtoupper(trim($serialNumber))`。
3. 使用序號查詢 `devices`，並使用 `lockForUpdate()` 鎖定資料。
4. 若設備不存在，回傳 `DEVICE_NOT_FOUND`，不得自動建立。
5. 使用 `Hash::check()` 驗證隱碼。
6. 套用既有鎖定、同房間及移轉規則。
7. 更新 `current_room_id`、`name`、`is_locked`，並將 `is_enabled` 設為 `true`。
8. 寫入 `device_transfer_logs`。

### 管理後台 API

#### 設備列表

```http
GET /manage/api/devices
```

權限：

```text
manage.devices.view
```

Query parameters：

```text
search
room_public_id
assignment_status=all|assigned|unassigned
locked=true|false
enabled=true|false
page
per_page
```

#### 設備詳細

```http
GET /manage/api/devices/{serial_number}
```

權限：

```text
manage.devices.detail
```

#### 建立設備

```http
POST /manage/api/devices
```

權限：

```text
manage.devices.create
```

Request：

```json
{
  "serial_number": "DEVICE-000001",
  "secret": "physical-device-secret",
  "secret_confirmation": "physical-device-secret"
}
```

Response 不得包含 `secret` 或 `secret_hash`。

#### 房間詳細內的設備

固定使用獨立 API：

```http
GET /manage/api/rooms/{room_public_id}/devices
```

此 API 必須支援分頁，不得將完整設備列表塞入既有房間詳細 API。

權限：

```text
manage.rooms.detail
manage.devices.view
```

---

## Middleware

管理後台頁面共同 middleware：

```text
auth
manage.authenticated
permission:manage.access
```

細部頁面權限：

|頁面|Permission|
|---|---|
|`/manage/devices`|`manage.devices.view`|
|`/manage/devices/create`|`manage.devices.create`|
|`/manage/devices/{serial_number}`|`manage.devices.detail`|

管理後台 API 共同 middleware：

```text
auth
manage.authenticated
permission:manage.access
```

各 API 再套用對應的 `manage.devices.*` permission。

注意路由順序：

- `/manage/devices/create` 必須在 `/manage/devices/{serial_number}` 前註冊，避免 `create` 被解析為序號。
- `POST /manage/api/devices` 與 `GET /manage/api/devices` 必須使用不同 permission。

---

## 操作紀錄

管理後台設備操作應使用既有 `manage_action_logs`。

固定 action：

```text
devices.detail.view
devices.create
rooms.devices.view
```

不記錄 `GET /manage/api/devices` 的列表搜尋、篩選及換頁，避免產生大量低價值紀錄。

建立設備紀錄範例：

```json
{
  "actor_type": "manage_user",
  "action": "devices.create",
  "target_type": "device",
  "target_public_id": "DEVICE-000001",
  "metadata": {
    "before": null,
    "after": {
      "serial_number": "DEVICE-000001",
      "current_room_public_id": null,
      "is_locked": false,
      "is_enabled": true
    }
  }
}
```

禁止寫入 metadata：

- 隱碼明文。
- `secret_hash`。
- Request 的完整 body。

---

## 錯誤碼

沿用：

```text
DEVICE_NOT_FOUND
DEVICE_SECRET_INVALID
DEVICE_LOCKED
DEVICE_ALREADY_IN_THIS_ROOM
DEVICE_NOT_IN_ROOM
DEVICE_MUST_UNLOCK_BEFORE_REMOVE
```

新增：

```text
DEVICE_SERIAL_ALREADY_EXISTS
```

建議語意：

|錯誤碼|說明|
|---|---|
|`DEVICE_NOT_FOUND`|客戶輸入的序號不存在於設備主檔|
|`DEVICE_SECRET_INVALID`|隱碼不正確|
|`DEVICE_SERIAL_ALREADY_EXISTS`|系統管理員建立設備時，序號已存在|

權限不足一律使用 Laravel permission middleware 回傳 HTTP 403，不使用設備業務錯誤碼。

---

## 安全要求

1. 隱碼使用 Laravel `Hash` 儲存與驗證。
2. 不得使用可逆加密取代 hash，除非未來有明確業務需求需要取回原始隱碼。
3. `Device` model 必須將 `secret_hash` 設為 hidden。
4. API payload 使用明確欄位白名單，不直接序列化完整 model。
5. 管理後台建立設備時，不得在 log、exception、validation message 中記錄隱碼。
6. 客戶加入設備時，設備查詢與更新必須包在 transaction 並使用 row lock，避免同一設備同時加入不同房間。
7. 服務管理員即使可查看設備，也不得取得隱碼驗證能力或建立設備能力。
8. 所有管理後台設備 API 必須經過管理登入狀態及 permission 檢查。
9. 序號重複由 service 檢查及資料庫 unique constraint 雙重保護。
10. 建立與加入流程必須共用相同的序號 normalizer。
11. 設備移轉或解除房間時必須將 `is_enabled` 重設為 `true`。
12. API 不得直接序列化完整 `Device` model。
13. `RoomService` 刪除房間、`RoomMemberService` 最後房主退出及 `DeviceService` 移除/移轉設備時，都必須同步套用 `is_enabled = true`。

---

## 測試規劃

### 管理後台權限

- `service_manager` 可以查看設備列表。
- `service_manager` 可以查看設備詳細資料。
- `service_manager` 可以在房間詳細頁查看設備。
- `service_manager` 不可建立設備。
- `system_admin` 可以查看設備列表與詳細資料。
- `system_admin` 可以建立設備。
- 一般使用者不可存取任何 `/manage/devices*` 頁面或 API。
- 未完成管理後台登入驗證者不可存取設備管理功能。

### 建立設備

- 建立設備時序號與隱碼必填。
- 隱碼確認不一致時拒絕建立。
- 建立後 `current_room_id`、`name` 為 `null`。
- 建立後 `is_locked` 為 `false`。
- 建立後 `is_enabled` 為 `true`。
- 資料庫只儲存隱碼 hash。
- 重複序號不可建立。
- 序號正規化後重複也不可建立。
- response 與 audit log 不包含隱碼或 `secret_hash`。
- 建立成功會寫入 `manage_action_logs`。

### 設備查詢

- 設備列表包含已加入及未加入房間的設備。
- 可以依序號搜尋。
- 可以篩選已加入與未加入設備。
- 可以篩選鎖定與啟用狀態。
- 已加入設備會顯示正確房間。
- 未加入設備的房間資料為 `null`。
- 房間詳細 API 只回傳該房間的設備。
- 列表、詳細及房間 API 都不回傳隱碼或 `secret_hash`。

### 客戶加入設備

- 房主可以將資料庫中存在且隱碼正確的設備加入房間。
- 資料庫不存在的序號回傳 `DEVICE_NOT_FOUND`。
- 不存在的序號不會自動建立設備資料。
- 隱碼錯誤時拒絕加入。
- 住戶不可將設備加入房間。
- 房間成員可以查看房間內設備。
- 非房間成員不可查看該房間設備。
- 同一設備不能同時屬於兩間房間。
- 既有鎖定與移轉規則維持有效。
- 房主可以啟用或停用設備。
- 住戶不可啟用或停用設備。
- 設備移轉或解除房間後重設為啟用。
- 移轉紀錄依時間倒序並每頁 20 筆。
- 房間或使用者刪除後仍可由 snapshot 取得歷史 public ID。

### 併發與資料庫測試

- SQLite feature tests 驗證一般業務規則。
- MySQL integration tests 驗證 `lockForUpdate()` 與同一設備同時移轉。
- CI 沒有 MySQL 時可跳過獨立 MySQL test group，但正式驗收前必須執行。
- 同時建立相同正規化序號時，最終只能成功一筆。
- 同時將同一設備加入不同房間時，最終只能存在一個 `current_room_id`，且移轉紀錄必須一致。

---

## Phase 分割建議

### Phase 1：RBAC 與設備查詢 API

目標：

- 讓服務管理員與系統管理員可以查看設備資料。

包含：

- 建立 migration：
  - `devices.is_enabled boolean default true`
  - `device_transfer_logs.from_room_public_id_snapshot nullable`
  - `device_transfer_logs.to_room_public_id_snapshot nullable`
  - `device_transfer_logs.transferred_by_user_public_id_snapshot`
- 新增 permissions：
  - `manage.devices.view`
  - `manage.devices.detail`
  - `manage.devices.create`
- 將 view/detail 指派給 `service_manager`。
- 將全部設備 permissions 指派給 `system_admin`。
- 建立管理後台設備列表 API。
- 建立管理後台設備詳細 API。
- 建立獨立房間設備查詢 API：
  - `GET /manage/api/rooms/{room_public_id}/devices`
- 確保所有 response 不包含隱碼或 `secret_hash`。

完成條件：

- 服務管理員可查看所有設備。
- 可判斷設備位於哪間房間或尚未加入房間。
- 可在管理後台房間詳細資料中查看該房間設備。
- migration 可在既有資料上成功執行，既有設備的 `is_enabled` 為 `true`。

Phase 完成後：

- 執行 RBAC、設備列表、設備詳細及房間設備 API 測試。
- 建立 `Phase 1: ...` commit。

### Phase 2：系統管理員建立設備 API

目標：

- 讓具 `manage.devices.create` 的系統管理員建立設備主檔。

包含：

- 建立 `DeviceCatalogService`。
- 建立設備 API。
- 序號正規化及 unique 檢查。
- 隱碼 hash。
- 建立後維持未加入房間狀態。
- 建立後 `is_enabled = true`。
- 寫入 `manage_action_logs`。

完成條件：

- 系統管理員可建立設備。
- 服務管理員不可建立設備。
- 建立後設備不屬於任何 room 或 user。
- 隱碼不會以明文儲存或回傳。

Phase 完成後：

- 執行設備建立、重複序號、敏感資料及 audit tests。
- 建立 `Phase 2: ...` commit。

### Phase 3：管理後台設備頁面

目標：

- 提供完整的設備查詢與建立介面。

包含：

- `/manage/devices`
- `/manage/devices/{serial_number}`
- `/manage/devices/create`
- 搜尋、篩選及分頁。
- 房間與設備頁面互相連結。
- 依 permission 顯示新增設備入口。

完成條件：

- 服務管理員可從後台查看所有設備。
- 系統管理員可從後台新增設備。
- 管理後台不顯示任何隱碼資料。

Phase 完成後：

- 執行相關頁面權限測試與 `npm run build`。
- 建立 `Phase 3: ...` commit。

### Phase 4：客戶設備流程整合

目標：

- 確保客戶只能加入已存在於設備主檔的設備。

包含：

- 統一建立與加入流程的序號正規化。
- 確認 `DeviceService` 不會自動建立未知設備。
- 維持 transaction、row lock、隱碼驗證及移轉規則。
- 新增設備啟用/停用 API、前端操作及權限檢查。
- 設備移轉或解除房間時將 `is_enabled` 重設為 `true`。
- 確認所有房間成員可查看房間設備。
- 確認只有房主可進行設備管理。

完成條件：

- 未登錄設備不能加入房間。
- 已登錄設備可使用正確序號與隱碼加入房間。
- 房間成員可查看自己房間內的設備。

Phase 完成後：

- 執行房間設備、啟用狀態、鎖定、移除及移轉測試。
- 建立 `Phase 4: ...` commit。

### Phase 5：測試、稽核與安全檢查

目標：

- 完成權限、資料安全及整合驗收。

包含：

- Feature tests。
- RBAC permission matrix。
- audit log tests。
- 敏感資料 response 檢查。
- 重複序號與併發加入測試。
- SQLite feature tests 與 MySQL integration test group。
- 驗證移轉紀錄 snapshot、倒序及每頁 20 筆。
- 前端 build。
- API 文件更新。

完成條件：

- 所有角色只能執行文件允許的操作。
- 隱碼及 `secret_hash` 不會出現在頁面、API 或 log。
- 設備建立、查詢及加入房間流程均有測試覆蓋。

Phase 完成後：

- 執行完整 `php artisan test`。
- 在 MySQL 執行併發 integration test group。
- 執行 `npm run build`。
- 建立 `Phase 5: ...` commit。
