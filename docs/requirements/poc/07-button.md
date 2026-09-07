# 按鈕頁與設備 Pull-Based Job 開發規劃

## 目的

建立登入後的主要「按鈕」頁面，讓一般使用者可以透過自訂分頁與自訂按鈕，對自己有權限觸及的指定設備派發任務。

本規劃整合 `docs/requirements/poc/07-button-simple.md` 與 `docs/requirements/poc/runner-pull-based-job-service.md`，並套用以下核心關係：

1. 設備本身就是 runner，不另建立獨立 runner 身分。
2. 設備使用自身序號與隱碼向 Server 換取長效 JWT token。
3. 一般使用者按下按鈕，代表 Server 指派一筆任務給該按鈕綁定的指定設備。
4. 設備輪詢取得任務後，依任務 payload 內的產品功能代碼執行對應功能。

---

## 目前專案基礎

本功能必須沿用現有專案架構與既有模型：

- Laravel + Inertia + React。
- 一般使用者 API 使用 `routes/api.php` 與 `auth:sanctum`。
- 管理後台 API 使用 `routes/web.php` 的 `/manage/api/*`，並套用 `auth`、`manage.authenticated`、`permission:manage.access`。
- API response 沿用 `App\Http\Controllers\ApiController`。
- 專案已安裝 `tymon/jwt-auth`，設備與伺服器之間的 authorization 使用 JWT Bearer token。
- `config/jwt.php` 已存在，需確認正式環境設定 `JWT_SECRET` 或非對稱金鑰，並保留 blacklist 能力以支援撤銷。
- 設備主檔已存在：
  - `App\Models\Device`
  - `App\Services\DeviceService`
  - `App\Support\DeviceSerial`
- 產品與產品功能已存在：
  - `App\Models\Product`
  - `App\Models\ProductFunction`
  - `product_functions.code`
  - `product_functions.description`
- 設備與產品關聯已存在：
  - `devices.product_id`
  - `Device belongsTo Product`
  - `Product hasMany ProductFunction`
- 房間成員關係是判斷一般使用者是否能選擇與操作設備的依據。
- Laravel 內建 queue 已使用 `jobs` 資料表，因此本功能任務資料表不得命名為通用 `jobs`，應使用 `button_action_jobs`。

---

## 名詞定義

### 設備

設備是實體硬體，也是 `docs/requirements/poc/runner-pull-based-job-service.md` 內的 runner。

設備會：

- 使用序號與隱碼取得長效 JWT token。
- 使用長效 JWT token 換取短效 JWT token。
- 使用短效 JWT token 輪詢自己的任務。
- 執行任務 payload 內指定的產品功能代碼。
- 回報進度與完成結果。

### 長效 JWT token

設備使用序號與隱碼取得的 JWT 憑證。長效 JWT token 只允許用來申請短效 JWT token，不可直接 poll 任務或回報結果。

### 短效 JWT token

設備執行期使用的 JWT 憑證。短效 JWT token 用於：

- poll 任務。
- 回報任務進度。
- 回報任務完成。

同一設備同一時間只允許一個有效短效 JWT token。

### JWT claims

設備 JWT 必須帶有足夠 claims 以區分長效與短效 token，避免混用。

建議 claims：

```json
{
  "sub": "device database id",
  "jti": "jwt unique id",
  "typ": "device_long|device_access",
  "serial_number": "DEVICE-000001",
  "token_version": 3,
  "scope": ["device:issue-access-token"]
}
```

短效 JWT scope：

```json
{
  "scope": ["device:poll", "device:progress", "device:complete"]
}
```

驗證時必須檢查：

- JWT 簽章與期限。
- `typ` 是否符合該 API。
- `sub` 是否為存在且未停用的設備。
- `token_version` 是否等於設備目前版本。
- 短效 JWT 的 `jti` 是否等於設備目前 `current_access_jti`。
- JWT 是否已被 `tymon/jwt-auth` blacklist 或本系統撤銷紀錄標記。

### 按鈕頁

使用者自訂的分頁。每個使用者最多 50 個按鈕頁，其他使用者不可查看或操作。

### 按鈕

按鈕頁上的一個格子項目。每個按鈕綁定：

- 一台指定設備。
- 該設備所屬產品底下的一個產品功能。

按鈕不綁定特定房間。只要該設備目前位於使用者可訪問的任一房間內，且設備與功能狀態允許操作，按鈕就可用。

### 按鈕任務

一般使用者短按按鈕後，Server 建立的 `button_action_jobs` 紀錄。該任務指定給按鈕綁定的設備，設備輪詢取得後執行。

---

## 核心規則

1. 登入後第一個主要頁面為 `/buttons`。
2. 按鈕頁只屬於建立它的使用者。
3. 按鈕頁不可轉移擁有者。
4. 每位使用者最多 50 個按鈕頁。
5. 每個按鈕頁最多 100 個按鈕。
6. 按鈕頁最少可以是 0 個。
7. 按鈕頁名稱最多 100 字，可與同一使用者其他按鈕頁重複。
8. 按鈕頁支援 2、3、4、5 欄 grid layout。
9. 分頁只允許直向滾動。
10. 編輯模式以單一按鈕頁為單位。
11. 新增、刪除、排序按鈕頁不屬於按鈕頁編輯模式。
12. 儲存編輯時採整頁覆蓋；多平台同時編輯不做衝突檢查，後儲存覆蓋先儲存。
13. 儲存前不寫入正式資料。
14. 取消、重新整理或關閉頁面皆視同放棄尚未儲存的編輯內容。
15. 儲存時後端必須重新驗證整頁與全部按鈕資料。
16. 前端 disabled 狀態只作為提示，觸發按鈕時後端必須重新檢查權限、設備狀態與功能狀態。
17. 使用者同一時間只允許一筆未結束的按鈕任務。
18. 按鈕操作 request ID 必須在同一使用者範圍內具備冪等性。
19. 前端等待逾時為 1 分鐘；逾時只解除前端等待，不代表設備端任務一定取消。
20. 設備完成任務時不會同時取得下一筆任務，下一輪 poll 才會取得新任務。

---

## 整體流程

### 設備取得 token

```text
Device                         Server
  |                              |
  | POST /api/device-auth/long-token
  | serial_number + secret       |
  |----------------------------->|
  | verify serial + secret       |
  | rotate long JWT              |
  |<-----------------------------|
  | device_id + long JWT         |
  |                              |
  | POST /api/devices/{id}/access-tokens
  | Authorization: Bearer long JWT
  |----------------------------->|
  | revoke old short JWT         |
  | create new short JWT         |
  |<-----------------------------|
  | short JWT                    |
```

### 使用者按下按鈕到設備執行

```text
User Frontend                  Server                         Device
  |                              |                              |
  | POST /api/button-actions     |                              |
  | button + request_id          |                              |
  |----------------------------->|                              |
  | validate user/device/function|                              |
  | create job for device        |                              |
  |<-----------------------------|                              |
  | job id                       |                              |
  |                              | POST /api/devices/{id}/poll |
  |                              |<-----------------------------|
  |                              | assign oldest queued job     |
  |                              |----------------------------->|
  |                              | product_function_code        |
  |                              |                              |
  |                              | progress / complete          |
  |                              |<-----------------------------|
  | poll job status              |                              |
  |<---------------------------->|                              |
```

---

## 按鈕可用狀態

按鈕狀態由後端統一計算，前端不得自行推導安全結果。

disabled 原因依以下優先順序顯示：

|優先序|狀態|建議顯示|
|---:|---|---|
|1|設備不在使用者可訪問的任一房間內|設備目前不可用|
|2|設備被後台禁用|設備已被系統鎖定|
|3|設備被房主停用|設備已被房主停用|
|4|設備離線|設備已離線|
|5|功能無法使用|功能無法使用|

建議新增：

```text
App\Services\Button\ButtonAvailabilityService
```

負責：

- 判斷設備目前房間是否存在且使用者仍是房間成員。
- 判斷設備是否被系統停用。
- 判斷設備是否被房主停用。
- 判斷設備是否在線。
- 判斷產品功能是否仍屬於該設備產品且可用。
- 回傳一致狀態 payload。

建議 payload：

```json
{
  "available": false,
  "reason": "device_offline",
  "message": "設備已離線"
}
```

### 需要補強的設備狀態

既有 `devices.is_enabled` 已用於房主啟用/停用設備。為了區分後台禁用與房主停用，建議在 `devices` 補上：

```text
is_system_disabled boolean default false
system_disabled_at nullable
system_disabled_by_user_id nullable
runner_status
runner_last_seen_at nullable
runner_current_job_id nullable
```

`runner_status` 建議值：

```text
registered
idle
running
offline
disabled
error
```

產品功能若需要停用但保留資料，建議新增：

```text
product_functions.is_enabled boolean default true
```

若第一版不提供產品功能停用 UI，仍可先以「功能不存在、功能已刪除、產品已鎖定或功能不屬於設備產品」視為 `function_unavailable`。

---

## 資料模型

### devices 擴充

設備本身就是 runner，因此設備 JWT 狀態與在線狀態直接掛在設備或設備附屬 JWT 紀錄表。

建議擴充 `devices`：

```text
long_token_jti nullable
long_token_issued_at nullable
long_token_expires_at nullable
long_token_revoked_at nullable
token_version unsignedInteger default 1
current_access_jti nullable
current_access_expires_at nullable
runner_status default registered
runner_current_job_id nullable
runner_last_seen_at nullable
runner_registered_at nullable
runner_disabled_at nullable
is_system_disabled default false
system_disabled_at nullable
system_disabled_by_user_id nullable
```

說明：

- `secret_hash` 繼續作為序號與隱碼驗證來源。
- 設備使用序號與隱碼換取長效 JWT token。
- 再次以序號與隱碼換取長效 JWT token 時，必須遞增 `token_version`，撤銷舊長效 JWT 與舊短效 JWT。
- `current_access_jti` 用於確保同一設備同一時間只有一個有效短效 JWT。
- `token_version` 用於讓舊 JWT 立即失效，即使 JWT 尚未到期。
- `runner_disabled_at` 表示設備執行端被停用，停用後不可 poll/progress/complete。
- `is_system_disabled` 表示設備業務上被後台禁用，按鈕不可操作。

### device_jwt_tokens

JWT 發行與撤銷紀錄表。系統不保存 JWT 明文，但需保存 `jti` 以支援撤銷、稽核與「目前短效 JWT」檢查。

```text
id
jti unique
device_id
type
token_version
issued_at
expires_at
revoked_at nullable
last_used_at nullable
metadata json nullable
created_at
updated_at
```

資料庫約束：

```text
unique(jti)
index(device_id, type)
index(expires_at)
```

規則：

- JWT 明文只在發行成功 response 出現一次。
- 資料庫不保存 JWT 明文。
- 長效 JWT 與短效 JWT 都應記錄 `jti`。
- 同一設備同一時間只允許一個有效短效 JWT。
- `devices.current_access_jti` 必須等於目前有效短效 JWT 的 `jti`。
- 撤銷 JWT 時，應同時寫入 `device_jwt_tokens.revoked_at`，並盡可能使用 `tymon/jwt-auth` blacklist 使該 JWT 不可再用。

### button_pages

```text
id
public_id unique
user_id
name
layout_columns
sort_order
created_at
updated_at
```

欄位規則：

|欄位|規則|
|---|---|
|`public_id`|對外識別碼，建議格式 `BPG-XXXXXXXXXXXX`|
|`user_id`|擁有者，不可轉移|
|`name`|最多 100 字，可重複|
|`layout_columns`|只允許 2、3、4、5|
|`sort_order`|同一 user 內排序|

### button_page_items

```text
id
public_id unique
button_page_id
device_id
product_function_id
position
shape
background_color
content_type
icon_key nullable
label nullable
foreground_color
created_at
updated_at
```

欄位規則：

|欄位|規則|
|---|---|
|`public_id`|對外識別碼，建議格式 `BTN-XXXXXXXXXXXX`|
|`device_id`|綁定設備，不因設備移轉房間而改變|
|`product_function_id`|綁定產品功能|
|`position`|grid 位置，非負整數，同頁不可重複|
|`shape`|`rounded_square` 或 `circle`|
|`background_color`|hex 色碼，例如 `#2563EB`|
|`content_type`|`icon` 或 `text`|
|`icon_key`|使用內建 icon key，`content_type=icon` 時必填|
|`label`|自訂文字，最多 100 字，`content_type=text` 時必填|
|`foreground_color`|hex 色碼|

資料庫約束：

```text
unique(button_page_id, position)
index(device_id)
index(product_function_id)
```

### button_action_jobs

```text
id
public_id unique
user_id
request_id
button_page_item_id nullable
device_id
product_function_id
status
source
payload json nullable
progress unsignedTinyInteger default 0
progress_message nullable
result json nullable
error_code nullable
error_message nullable
locked_by_device_id nullable
lease_expires_at nullable
started_at nullable
finished_at nullable
front_end_timeout_at nullable
last_progress_at nullable
created_at
updated_at
```

資料庫約束：

```text
unique(user_id, request_id)
index(user_id, status)
index(device_id, status, created_at)
index(product_function_id)
```

狀態：

|狀態|說明|
|---|---|
|`queued`|已建立，等待指定設備 poll|
|`running`|指定設備已取得並執行中|
|`succeeded`|設備功能執行成功|
|`failed`|設備功能執行失敗|
|`device_offline`|建立或派送時設備不可用|
|`timed_out`|Server 判定逾時或租約過期|
|`unauthorized`|操作時使用者已失去權限|
|`canceled`|被系統或管理端取消|

`payload` 建議只放設備執行所需的安全資料：

```json
{
  "type": "button_function",
  "device_serial_number": "DEVICE-000001",
  "product_function_code": "PFN-XXXXXXXXXXXX"
}
```

不得放入：

- 設備隱碼。
- `secret_hash`。
- 使用者敏感資料。
- 長效或短效 JWT。

### button_action_job_events

建議建立，用於追蹤進度與除錯：

```text
id
button_action_job_id
type
message nullable
metadata json nullable
created_at
```

事件類型：

```text
created
assigned
progress
completed
failed
lease_expired
frontend_timeout
```

---

## 後端服務設計

### ButtonPageService

```text
App\Services\Button\ButtonPageService
```

負責：

- 建立按鈕頁。
- 修改按鈕頁名稱與欄數。
- 刪除按鈕頁。
- 排序按鈕頁。
- 儲存整頁按鈕配置。
- 驗證使用者擁有權。
- 驗證分頁數量上限。
- 驗證按鈕數量上限、位置不可重疊、外觀欄位合法。

### ButtonSelectionService

```text
App\Services\Button\ButtonSelectionService
```

負責：

- 回傳目前使用者可選擇的設備。
- 回傳設備所屬產品的可選產品功能。
- 過濾使用者不可存取的設備。
- 過濾不可用設備或不可用功能。

### ButtonAvailabilityService

負責操作模式下的狀態判斷，需同時供：

- 按鈕頁列表 API。
- 長按資訊 payload。
- 觸發按鈕 API。

### ButtonActionService

```text
App\Services\Button\ButtonActionService
```

負責：

- 接收短按操作。
- 驗證 request ID 冪等性。
- 驗證同一 user 是否已有未結束任務。
- 重新檢查按鈕擁有權、設備權限、設備狀態、功能狀態。
- 建立指定 `device_id` 的 `button_action_jobs`。
- 建立任務 payload，包含 `product_function_code`。
- 回傳可追蹤的 job public ID。

### DeviceTokenService

```text
App\Services\DeviceRuntime\DeviceTokenService
```

負責：

- 使用設備序號與隱碼發行長效 JWT token。
- 使用 `tymon/jwt-auth` 建立帶有自訂 claims 的設備 JWT。
- 長效 JWT token 驗證。
- 短效 JWT token 發行與撤銷。
- 短效 JWT token 驗證。
- 驗證 `typ`、`scope`、`jti`、`sub`、`token_version`。
- 更新設備 `runner_last_seen_at`。
- 停用設備時撤銷 JWT，並遞增 `token_version` 讓既有 JWT 失效。

### DeviceJobService

```text
App\Services\DeviceRuntime\DeviceJobService
```

負責：

- 設備 poll FIFO 派工。
- progress 回報。
- complete 回報。
- job lease 延長。
- complete 冪等性。
- 設備 runner 狀態更新。

---

## API 規劃

### 一般使用者按鈕頁 API

所有 API 套用：

```text
auth:sanctum
```

#### 取得按鈕頁

```http
GET /api/button-pages
```

回傳：

- 使用者自己的全部按鈕頁。
- 依 `sort_order` 排序。
- 每個按鈕含可用狀態。

#### 建立按鈕頁

```http
POST /api/button-pages
```

Request：

```json
{
  "name": "客廳",
  "layout_columns": 3
}
```

#### 修改按鈕頁基本資料

```http
PATCH /api/button-pages/{button_page_public_id}
```

可修改：

- `name`
- `layout_columns`

#### 儲存整頁按鈕

```http
PUT /api/button-pages/{button_page_public_id}/layout
```

Request：

```json
{
  "name": "客廳",
  "layout_columns": 3,
  "buttons": [
    {
      "public_id": "BTN-XXXXXXXXXXXX",
      "device_serial_number": "DEVICE-000001",
      "product_function_code": "PFN-XXXXXXXXXXXX",
      "position": 0,
      "shape": "rounded_square",
      "background_color": "#2563EB",
      "content_type": "icon",
      "icon_key": "power",
      "label": null,
      "foreground_color": "#FFFFFF"
    }
  ]
}
```

後端行為：

1. 確認按鈕頁屬於目前使用者。
2. 驗證按鈕數量最多 100。
3. 驗證 `position` 不重複。
4. 驗證顏色、形狀、內容類型與長度。
5. 對新增按鈕或變更設備/功能的按鈕，驗證設備與功能目前仍可被選擇。
6. 對既有按鈕，若設備或功能目前不可用，允許保留綁定，但操作模式顯示 disabled。
7. 使用 transaction 整頁覆蓋保存。

#### 刪除按鈕頁

```http
DELETE /api/button-pages/{button_page_public_id}
```

#### 排序按鈕頁

```http
PUT /api/button-pages/order
```

Request：

```json
{
  "button_page_public_ids": [
    "BPG-AAA",
    "BPG-BBB"
  ]
}
```

後端需確認清單全部屬於目前使用者。

### 可選設備與功能 API

```http
GET /api/buttons/selectable-targets
```

回傳目前使用者可選擇的設備與功能。

```json
{
  "device": {
    "serial_number": "DEVICE-000001",
    "name": "客廳按鈕",
    "room": {
      "public_id": "ROOM-XXXXXXXXXXXX",
      "name": "家"
    },
    "product": {
      "model_number": "BTN-001",
      "name": "智慧按鈕"
    }
  },
  "functions": [
    {
      "code": "PFN-XXXXXXXXXXXX",
      "description": "短按觸發"
    }
  ]
}
```

### 按鈕操作 API

#### 觸發按鈕

```http
POST /api/button-actions
```

Request：

```json
{
  "button_public_id": "BTN-XXXXXXXXXXXX",
  "request_id": "frontend-generated-uuid"
}
```

後端行為：

1. 檢查 `request_id` 在同一使用者範圍內是否已存在。
2. 若同一 request ID 已建立相同操作，回傳既有 job。
3. 若同一 request ID 對應不同操作，回傳 `BUTTON_REQUEST_ID_CONFLICT`。
4. 檢查目前使用者是否已有 `queued` 或 `running` 任務。
5. 重新檢查按鈕屬於目前使用者。
6. 重新檢查使用者是否仍可操作該設備與功能。
7. 建立指定 `device_id` 的 `button_action_jobs`。
8. payload 內寫入 `product_function_code`。

Response：

```json
{
  "job": {
    "public_id": "BAJ-XXXXXXXXXXXX",
    "status": "queued",
    "expires_at": "2026-06-25T12:01:00+08:00"
  }
}
```

#### 查詢目前未結束任務

```http
GET /api/button-actions/current
```

用途：

- 使用者關閉頁面後重新開啟，如果仍有未結束任務，前端可恢復等待畫面。

#### 查詢單一任務

```http
GET /api/button-actions/{button_action_job_public_id}
```

限制：

- 使用者只能查詢自己的任務。
- 不回傳設備 token、內部 ID、敏感 payload。

### 設備執行期 API

設備執行期 API 放在 `routes/api.php`，但不可使用一般使用者 session 驗證。

#### 設備取得長效 JWT token

```http
POST /api/device-auth/long-token
```

Request：

```json
{
  "serial_number": "DEVICE-000001",
  "secret": "physical-device-secret",
  "name": "optional-device-runtime-name",
  "version": "1.0.0",
  "capabilities": ["button-function"]
}
```

Server 行為：

1. 正規化序號。
2. 查詢設備主檔。
3. 使用 `Hash::check()` 驗證隱碼。
4. 檢查設備未被 runtime 停用。
5. 遞增 `devices.token_version`。
6. 撤銷舊長效 JWT 與既有短效 JWT。
7. 使用 `tymon/jwt-auth` 簽發 `typ=device_long` 的 JWT。
8. 記錄 JWT `jti` 至 `devices.long_token_jti` 與 `device_jwt_tokens`。
9. 更新設備 runtime 狀態與 `runner_last_seen_at`。

Response：

```json
{
  "device_id": "DEVICE-000001",
  "long_token": "device-long-jwt"
}
```

#### 設備申請短效 JWT token

```http
POST /api/devices/{serial_number}/access-tokens
Authorization: Bearer <device-long-jwt>
```

Response：

```json
{
  "access_token": "device-access-jwt",
  "token_type": "Bearer",
  "expires_in": 900,
  "expires_at": "2026-06-25T12:30:00+08:00"
}
```

Server 行為：

- 驗證長效 JWT。
- 檢查 `typ=device_long` 與 scope。
- 檢查設備是否存在。
- 檢查設備 runtime 是否被停用。
- 檢查 JWT `token_version` 是否等於設備目前版本。
- 作廢舊短效 JWT。
- 使用 `tymon/jwt-auth` 建立 `typ=device_access` 的短效 JWT。
- 更新 `devices.current_access_jti`。
- 更新 `devices.runner_last_seen_at`。

#### 設備 poll 任務

```http
POST /api/devices/{serial_number}/poll
Authorization: Bearer <device-access-jwt>
```

Request：

```json
{
  "status": "idle",
  "current_job_id": null
}
```

沒有任務：

```http
204 No Content
```

有任務：

```json
{
  "job_id": "BAJ-XXXXXXXXXXXX",
  "type": "button_function",
  "payload": {
    "product_function_code": "PFN-XXXXXXXXXXXX"
  }
}
```

派工規則：

- 使用 transaction。
- 只從該設備的 `queued` jobs 取最早建立的任務。
- FIFO 排序使用 `created_at ASC, id ASC`。
- 派送後立即更新為 `running`。
- 設定 `locked_by_device_id` 與 `lease_expires_at`。
- 更新設備 `runner_status = running`。

#### 設備回報進度

```http
POST /api/device-jobs/{job_id}/progress
Authorization: Bearer <device-access-jwt>
```

Request：

```json
{
  "progress": 45,
  "message": "Processing",
  "status": "running"
}
```

Server 行為：

- 驗證短效 JWT。
- 檢查 `typ=device_access` 與 scope。
- 檢查 JWT `sub` 屬於該設備。
- 檢查 JWT `jti` 等於 `devices.current_access_jti`。
- 檢查任務由該設備鎖定。
- 更新 progress、message、last_progress_at。
- 延長或刷新 lease。
- 更新設備 `runner_last_seen_at`。

#### 設備回報完成

```http
POST /api/device-jobs/{job_id}/complete
Authorization: Bearer <device-access-jwt>
```

成功：

```json
{
  "status": "succeeded",
  "result": {
    "duration_ms": 120
  }
}
```

失敗：

```json
{
  "status": "failed",
  "error_message": "Device command failed"
}
```

complete 必須支援安全重試：

- 同一設備對同一任務重複回報相同結果，回傳成功。
- 已成功後又回報失敗，回傳 `409 Conflict`。
- 已失敗後又回報成功，回傳 `409 Conflict`。

---

## 管理後台規劃

按鈕頁屬於客戶前台，第一版不提供管理後台修改使用者按鈕配置。

管理後台需提供設備執行狀態與任務查詢。

### 新增 permissions

|Permission|說明|預設角色|
|---|---|---|
|`manage.device_runtime.view`|查看設備執行狀態與 token 狀態|`service_manager`、`system_admin`|
|`manage.device_runtime.manage`|停用設備 runtime、撤銷 token|`system_admin`|
|`manage.button_jobs.view`|查看按鈕任務|`service_manager`、`system_admin`|
|`manage.button_jobs.cancel`|取消 queued/running 任務|`system_admin`|

### 建議頁面

可整合到既有設備管理，也可新增獨立頁：

```text
/manage/devices/{serial_number}
/manage/device-runtime
/manage/button-jobs
/manage/button-jobs/{button_action_job_public_id}
```

### 建議 API

```http
GET  /manage/api/device-runtime
GET  /manage/api/device-runtime/{serial_number}
POST /manage/api/device-runtime/{serial_number}/disable
POST /manage/api/device-runtime/{serial_number}/enable
POST /manage/api/device-runtime/{serial_number}/revoke-tokens

GET  /manage/api/button-jobs
GET  /manage/api/button-jobs/{button_action_job_public_id}
POST /manage/api/button-jobs/{button_action_job_public_id}/cancel
```

管理後台 response 不得回傳：

- 長效 JWT 明文。
- 短效 JWT 明文。
- JWT `jti` 以外的簽章內容或完整 token。
- 設備隱碼或 `secret_hash`。
- 使用者密碼或第三方 token。

重要操作寫入 `manage_action_logs`。

---

## 前端頁面規劃

### 路由

```text
/buttons
```

調整建議：

- 使用者登入成功後導向 `/buttons`。
- 已登入使用者進入 `/` 時可導向 `/buttons` 或顯示主要入口。
- `AppLayout` 主要導覽新增「按鈕」，並放在房間管理前。

### 按鈕頁主畫面

- 0 個分頁時顯示空狀態與新增分頁入口。
- 有分頁時顯示分頁列與目前分頁 grid。
- 分頁可新增、刪除、排序。

### 編輯模式

每個分頁獨立進入編輯模式。

功能：

- 修改分頁名稱。
- 修改欄數。
- 新增按鈕。
- 移動按鈕。
- 修改按鈕外觀。
- 刪除按鈕。
- 儲存整頁。
- 取消整頁變更。

注意：

- 修改按鈕外觀不可直接修改設備關聯。
- 若要修改設備關聯，第一版建議視為刪除舊按鈕後新增新按鈕。
- 修改欄數時，前端依「從第一格開始由左到右、由上到下」重排位置。

### 按鈕編輯欄位

|欄位|控制元件|
|---|---|
|設備與功能|選單或搜尋式選擇器|
|形狀|segmented control|
|背景色|color input / swatch|
|內容類型|segmented control|
|icon|icon picker|
|文字|textarea 或 input，最多 100 字|
|文字/icon 色|color input / swatch|

icon 建議使用既有 `lucide-react` 白名單，例如：

```text
power
play
pause
square
volume
sun
moon
plus
minus
arrow-up
arrow-down
```

### 操作模式

- 短按：建立指定設備的任務。
- 長按或更多資訊：顯示按鈕資訊 dialog。
- disabled 按鈕不可送出操作，仍可長按查看原因。
- 送出操作後顯示等待畫面並阻止其他操作。
- 等待畫面輪詢 job status，直到成功、失敗、逾時、設備離線或無權限等終態。

等待畫面恢復：

- `/buttons` 載入時呼叫 `GET /api/button-actions/current`。
- 若有 `queued` 或 `running` 任務，直接顯示等待畫面。

---

## 安全要求

1. 所有按鈕頁 API 必須檢查 `user_id`。
2. 使用者不可取得其他使用者的按鈕頁或任務。
3. 儲存按鈕頁時不得信任前端傳來的設備名稱、房間名稱或功能名稱。
4. 儲存與觸發時必須用 `device_serial_number`、`product_function_code` 查詢真實資料。
5. 觸發按鈕時必須重新檢查使用者是否仍可存取設備目前所在房間。
6. 觸發按鈕時必須重新檢查設備是否後台禁用、房主停用、離線。
7. 觸發按鈕時必須重新檢查產品功能是否仍屬於該設備產品且可用。
8. 同一 user 的 `request_id` 必須唯一。
9. 同一 user 同一時間只允許一筆未結束任務。
10. 設備序號與隱碼只可用於取得長效 JWT。
11. 長效 JWT 只可用於申請短效 JWT。
12. 短效 JWT 只可用於設備 poll/progress/complete。
13. JWT 明文不可保存至資料庫，只可保存 `jti`、期限、類型與撤銷狀態。
14. 短效 JWT 過期、撤銷、`token_version` 不符或不是 current `jti` 時必須拒絕。
15. progress/complete 必須確認任務鎖定於該設備。
16. 設備 runtime 被停用後所有 JWT 應失效，並遞增 `token_version`。
17. 任務 payload 不得包含設備隱碼、`secret_hash` 或使用者敏感資料。
18. 管理後台不可顯示完整 JWT、設備隱碼或 `secret_hash`。

---

## 錯誤碼

新增建議：

```text
BUTTON_PAGE_LIMIT_EXCEEDED
BUTTON_PAGE_NOT_FOUND
BUTTON_PAGE_FORBIDDEN
BUTTON_PAGE_BUTTON_LIMIT_EXCEEDED
BUTTON_POSITION_DUPLICATED
BUTTON_TARGET_NOT_SELECTABLE
BUTTON_TARGET_UNAVAILABLE
BUTTON_REQUEST_ID_CONFLICT
BUTTON_ACTION_ALREADY_RUNNING
BUTTON_ACTION_NOT_FOUND
BUTTON_ACTION_FORBIDDEN
DEVICE_SYSTEM_DISABLED
DEVICE_RUNTIME_DISABLED
DEVICE_OFFLINE
DEVICE_LONG_TOKEN_INVALID
DEVICE_ACCESS_TOKEN_INVALID
DEVICE_JOB_NOT_LOCKED
DEVICE_JOB_STATE_CONFLICT
PRODUCT_FUNCTION_UNAVAILABLE
```

既有錯誤碼可沿用：

```text
DEVICE_NOT_FOUND
DEVICE_SECRET_INVALID
```

權限不足仍可沿用 Laravel Authorization / permission middleware 的 HTTP 403。

---

## 測試規劃

### 按鈕頁

- 使用者初始可為 0 個分頁。
- 使用者最多建立 50 個分頁。
- 不能查看、修改、刪除其他使用者的分頁。
- 可新增、刪除、排序分頁。
- 分頁名稱最多 100 字。
- 欄數只允許 2、3、4、5。
- 儲存整頁時會覆蓋既有按鈕。
- 每頁最多 100 個按鈕。
- 同頁 position 不可重複。
- 刪除按鈕不改變其他按鈕位置。
- 修改欄數後可保存前端重排結果。

### 按鈕綁定與可用狀態

- 只能選擇目前使用者可存取房間內的設備。
- 只能選擇設備產品底下的功能。
- 設備移出使用者可存取房間後，既有按鈕保留但 disabled。
- 設備被房主停用後，按鈕顯示房主停用。
- 設備被後台禁用後，按鈕顯示系統鎖定。
- 設備未取得 token 或太久未 poll/progress/complete 時，按鈕顯示設備離線。
- 功能不存在、停用或不屬於設備產品時，按鈕顯示功能無法使用。
- disabled 原因符合優先順序。

### 按鈕操作

- 可用按鈕可建立指定設備的 action job。
- job payload 包含 `product_function_code`。
- 前端 request ID 重送相同操作回傳同一 job。
- 同一 request ID 對不同操作回傳 conflict。
- 使用者已有未結束 job 時不可建立新 job。
- 使用者不可查詢其他使用者的 job。
- 觸發時若使用者已失去房間權限，建立 `unauthorized` 或拒絕。
- 觸發時若設備離線，回傳對應狀態。
- 前端逾時不阻止設備後續 complete 更新結果。

### 設備 JWT 與任務輪詢

- 設備可用序號與隱碼取得長效 JWT。
- 序號不存在回傳 `DEVICE_NOT_FOUND`。
- 隱碼錯誤回傳 `DEVICE_SECRET_INVALID`。
- 長效 JWT 不可 poll/progress/complete。
- 長效 JWT 可換短效 JWT。
- 換新短效 JWT 後舊短效 JWT 失效。
- 短效 JWT 過期後不可使用。
- `typ`、`scope`、`sub`、`jti`、`token_version` 不符時不可使用。
- 設備 poll 只取得指定給自己的 queued job。
- 設備 poll 使用 FIFO 派工。
- 同一 job 不會被重複派送。
- progress 更新 job 與設備 runtime 狀態。
- complete 更新 job 與設備 runtime 狀態。
- complete 相同結果可重試。
- complete 衝突結果回傳 409。
- 設備 runtime disabled 後 JWT 與 poll/progress/complete 均不可用。
- lease 過期後 job 可標記為 `timed_out`。

### 前端

- `/buttons` 可載入 0 分頁空狀態。
- 可新增、刪除、排序分頁。
- 可進入、儲存、取消編輯模式。
- 可新增按鈕、移動按鈕、修改外觀。
- disabled 按鈕顯示原因。
- 短按會顯示等待畫面。
- 重新開啟頁面可恢復未結束任務等待畫面。
- 執行 `npm run build` 成功。

---

## Phase 執行狀態與提交規則

提交規則：

- 每完成一個 Phase 必須立即建立一次 commit。
- commit message 使用各 Phase 既定名稱，方便追蹤功能邊界。
- 若同一階段的實作已先完成但尚未提交，需在該階段結束時依 Phase 邊界補齊提交。
- 後續 Phase 不與已完成 Phase 混合提交；若發現既有 Phase 內容需要修正，需在 commit message 清楚標示修正範圍。

目前進度：

|Phase|狀態|commit|
|---|---|---|
|Phase 1：按鈕頁資料模型與基本 API|已完成|`b4f803c` `Phase 1: Button page data model and API`|
|Phase 2：設備/功能選擇與按鈕可用狀態|已完成|`02c8be3` `Phase 2: Button target selection and availability`|
|Phase 3：按鈕頁前端 UI|已完成|`cafc96d` `Phase 3: Button page UI`|
|Phase 4：設備長效/短效 JWT 機制|已完成|`8c5aa30` `Phase 4: Device JWT authentication`|
|Phase 5：按鈕任務與設備派工|已完成|`045b872` `Phase 5: Button action jobs and device dispatch`|
|Phase 6：等待畫面與操作流程整合|待確認/補強|尚未建立獨立 Phase 6 commit|
|Phase 7：管理後台設備 runtime 與任務查詢|待開發|尚未提交|
|Phase 8：穩定化、逾時掃描與整合驗收|待開發|尚未提交|

備註：

- 目前前端等待 dialog、job 狀態輪詢、current job 恢復等操作流程已隨 Phase 3 與 Phase 5 的第一版實作完成部分內容。
- Phase 6 仍保留為獨立驗收階段，用於確認等待流程完整性、補齊缺口與建立獨立提交。

已提交內容對照：

- Phase 1 commit 建立按鈕頁資料表、model、service 與 controller 方法；API route 實際在 Phase 5 commit 接上。
- Phase 2 commit 建立可選設備/功能與可用狀態服務，並新增 `devices` runtime/JWT 狀態欄位與 `product_functions.is_enabled`；API route 實際在 Phase 5 commit 接上。
- Phase 3 commit 建立 `/buttons` UI、登入後導向、導覽項目與 build 所需樣式；長按資訊 dialog 尚未完成，現階段以按鈕資訊文字與 title 呈現。
- Phase 4 commit 建立設備 JWT controller、service 與 token 紀錄表；JWT 撤銷目前以 `device_jwt_tokens.revoked_at`、`devices.token_version` 與 `devices.current_access_jti` 判斷，尚未整合 `tymon/jwt-auth` blacklist。
- Phase 5 commit 建立按鈕任務、設備 poll/progress/complete、全部按鈕/設備 runtime API route 與 Feature tests；job lease 目前負責寫入與 progress 續租，逾期掃描留到 Phase 8。

### Phase 1：按鈕頁資料模型與基本 API

目標：

- 建立使用者自訂分頁的資料模型、服務與 controller 方法。

包含：

- 建立 `button_pages` migration。
- 建立 `button_page_items` migration。
- 建立 `ButtonPage` model。
- 建立 `ButtonPageItem` model。
- 建立 `ButtonPageService`。
- 建立 `ButtonPageController` 的列表、建立、修改、刪除、排序、整頁 layout 儲存方法。
- controller 與 service 內實作 owner 檢查與頁面數量、按鈕數量、position、外觀欄位驗證。
- API route 與 `auth:sanctum` middleware 實際在 Phase 5 commit 接上。

完成條件：

- 後端具備按鈕頁管理所需的 model、service、controller 方法。
- controller/service 可拒絕非擁有者操作。
- 後端可驗證分頁數量、按鈕數量、position 與外觀欄位。
- 完整 API 可用性由 Phase 5 route 與 Feature tests 驗證。

Phase 完成後：

- 建立 `Phase 1: Button page data model and API` commit：`b4f803c`。
- 按鈕頁 Feature tests 實際在 Phase 5 commit 補上並通過。

### Phase 2：設備/功能選擇與按鈕可用狀態

目標：

- 讓按鈕可綁定既有設備與產品功能，並由後端計算可用狀態。

包含：

- 建立 `ButtonSelectionService`。
- 建立 `ButtonAvailabilityService`。
- 建立 `ButtonTargetController`。
- `GET /api/buttons/selectable-targets` route 實際在 Phase 5 commit 接上。
- 按鈕頁 API 回傳按鈕可用狀態。
- 新增設備 runtime/JWT 狀態欄位、後台禁用狀態欄位。
- 新增 `product_functions.is_enabled`。
- 確認產品功能必須屬於設備產品。

完成條件：

- 使用者只能選到自己目前可存取的設備與功能。
- 既有按鈕在設備或功能不可用時會保留並顯示 disabled 原因。
- disabled 優先順序符合規格。

Phase 完成後：

- 建立 `Phase 2: Button target selection and availability` commit：`02c8be3`。
- 按鈕綁定、可選設備、可用狀態 tests 實際在 Phase 5 commit 補上並通過。

### Phase 3：按鈕頁前端 UI

目標：

- 完成 `/buttons` 使用者操作介面。

包含：

- 新增 `/buttons` route 與 Inertia page。
- 登入成功後導向 `/buttons`。
- `AppLayout` 新增「按鈕」主要導覽。
- 0 分頁空狀態。
- 分頁新增、刪除、排序。
- 分頁 grid 顯示。
- 編輯模式。
- 按鈕新增、刪除、position 數字調整、顏色與文字外觀修改。
- 整頁儲存與取消。
- disabled 原因以按鈕下方文字與 title 顯示。
- 任務狀態提示面板與 current job 輪詢第一版。
- 長按或資訊 dialog 尚未完成，留到 Phase 6 或後續 UX 補強。

完成條件：

- 使用者可從 UI 完成分頁與按鈕配置。
- 編輯模式未儲存前不寫入正式資料。
- `npm run build` 成功。

Phase 完成後：

- 執行 `npm run build`。
- 更新登入導向測試。
- 建立 `Phase 3: Button page UI` commit：`cafc96d`。

### Phase 4：設備長效/短效 JWT 機制

目標：

- 讓設備可用序號與隱碼取得長效 JWT，並以長效 JWT 換短效 JWT。

包含：

- 使用 Phase 2 已新增的 `devices` runtime/JWT 狀態欄位。
- 建立 `device_jwt_tokens` migration。
- 建立 `DeviceJwtToken` model。
- 建立 `DeviceTokenService`。
- 建立 `POST /api/device-auth/long-token`。
- 建立 `POST /api/devices/{serial_number}/access-tokens`。
- 使用 `tymon/jwt-auth` 簽發設備 JWT。
- JWT 需包含 `typ`、`scope`、`jti`、`sub`、`serial_number`、`token_version` claims。
- JWT 明文不保存至資料庫。
- 重新取得長效 JWT 時遞增 `token_version`，並以資料庫紀錄撤銷舊長效/短效 JWT。
- 新短效 JWT 會撤銷舊短效 JWT 紀錄，並更新 `devices.current_access_jti`。
- `tymon/jwt-auth` blacklist 尚未整合，留待後續安全補強。

完成條件：

- 設備可用序號與隱碼取得長效 JWT。
- 設備可取得短效 JWT。
- 舊短效 JWT 會立即失效。
- runtime disabled 的設備不可取得或使用 JWT。

Phase 完成後：

- 建立 `Phase 4: Device JWT authentication` commit：`8c5aa30`。
- 設備 JWT 流程由 Phase 5 的整合 Feature test 覆蓋。

### Phase 5：按鈕任務與設備派工

目標：

- 短按按鈕可建立指定設備任務，設備可 poll、progress、complete。

包含：

- 建立 `button_action_jobs` migration。
- 建立 `button_action_job_events` migration。
- 建立 `ButtonActionJob` model。
- 建立 `ButtonActionService`。
- 建立 `DeviceJobService`。
- 在 `routes/api.php` 接上 Phase 1、Phase 2、Phase 4、Phase 5 相關 API route。
- 建立 `POST /api/button-actions`。
- 建立 `GET /api/button-actions/current`。
- 建立 `GET /api/button-actions/{job}`。
- 建立設備 poll/progress/complete API。
- FIFO 派工。
- job lease 寫入與 progress 續租。
- complete 冪等性。
- 同一 user 同時只能有一筆未結束任務。

完成條件：

- 使用者按下可用按鈕會建立指定設備 job。
- job payload 內含產品功能代碼。
- 設備只會取得派給自己的 queued job。
- 設備可回報進度與完成。
- 前端可查詢 job 狀態。

Phase 完成後：

- 執行 Button action 與 Device job tests。
- 建立 `Phase 5: Button action jobs and device dispatch` commit：`045b872`。

### Phase 6：等待畫面與操作流程整合

目標：

- 將短按操作與前端等待流程完整串起。

包含：

- 確認並補強 Phase 3/5 已完成的前端 request ID、短按送出 `POST /api/button-actions`、current job 輪詢與任務狀態面板。
- 將任務狀態面板調整為規劃中的等待 dialog 或明確確認維持現有 panel 設計。
- 前端在 queued/running 任務期間主動鎖定其他按鈕操作，不只依賴後端 409。
- 補上前端 1 分鐘逾時解除等待與操作鎖定。
- 補齊成功、失敗、設備離線、無權限、逾時等狀態顯示文字。
- 確認頁面載入時查詢 current job 並恢復未結束任務畫面。
- 視 UX 決策補上長按或資訊 dialog。

完成條件：

- 使用者短按後不可操作其他按鈕直到完成或逾時。
- 關閉頁面後重新開啟可恢復未結束任務狀態。
- 後端任務可在前端逾時後繼續被設備更新。

Phase 完成後：

- 執行操作流程 tests 與 `npm run build`。
- 建立 `Phase 6: Button action waiting flow` commit。

### Phase 7：管理後台設備 runtime 與任務查詢

目標：

- 讓服務管理員與系統管理員可查看設備執行狀態與按鈕任務。

包含：

- 新增 RBAC permissions：
  - `manage.device_runtime.view`
  - `manage.device_runtime.manage`
  - `manage.button_jobs.view`
  - `manage.button_jobs.cancel`
- 更新 `ManagementRbac` 與 seeder。
- 建立設備 runtime 管理 API。
- 建立 button job 查詢 API。
- 建立管理後台頁面或整合到既有設備詳細頁。
- 重要操作寫入 `manage_action_logs`。

完成條件：

- 服務管理員可查看設備 runtime 與任務。
- 系統管理員可停用設備 runtime、撤銷 JWT、取消任務。
- 管理後台不顯示任何完整 JWT 或敏感資料。

Phase 完成後：

- 執行管理後台 RBAC、API、audit tests。
- 建立 `Phase 7: Manage device runtime and button jobs` commit。

### Phase 8：穩定化、逾時掃描與整合驗收

目標：

- 補齊安全、逾時、併發與整合測試。

包含：

- 建立 scheduler/command 掃描：
  - 離線設備。
  - lease 過期任務。
  - 前端逾時但設備未完成任務。
  - 過期 device access JWT。
- MySQL integration test 驗證 FIFO 派工與 row lock。
- SQLite feature tests 覆蓋一般業務規則。
- 完整 RBAC matrix。
- 敏感資料 response 檢查。
- API 文件更新。
- 前端 build。

完成條件：

- 同一任務不會被重複派送。
- 設備只能取得指定給自己的任務。
- 同一使用者不會同時建立多筆未結束按鈕任務。
- 完整 JWT、設備隱碼、`secret_hash` 不會出現在 response 或 log。
- 所有按鈕、設備 runtime、任務狀態符合規劃。

Phase 完成後：

- 執行完整 `php artisan test`。
- 在 MySQL 執行設備併發派工 integration test group。
- 執行 `npm run build`。
- 建立 `Phase 8: Button feature verification` commit。

---

## 第一版不處理項目

以下功能不列入按鈕第一版：

- 多人共享同一按鈕頁。
- 按鈕頁模板市場。
- 按鈕批次匯入/匯出。
- 跨使用者複製按鈕頁。
- 樂觀鎖或編輯衝突提示。
- Server 主動推送 WebSocket/SSE 狀態。
- 設備 long polling。
- 多設備 pool 或 capabilities matching。
- artifact 上傳。
- log streaming。
- 每個產品功能的複雜參數表單。
- 按鈕觸發排程或自動化規則。
- 一般使用者自行管理設備 token。
