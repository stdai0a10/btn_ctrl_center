# 產品主檔與設備關聯開發規劃

## 目的

建立「產品」模型，讓設備主檔可以關聯到產品。產品代表同一種設備型號或商品規格；設備代表實際出貨或登錄到系統的一台實體設備。

本功能的核心目標：

- 管理後台可以維護產品主檔。
- 管理後台可以維護產品支援的功能清單。
- 新增設備主檔時，可以指定該設備屬於哪一個產品。
- 查看設備時，可以知道設備對應的產品與產品功能。

產品與設備的關係：

```text
Product 1 ---- N Device
Product 1 ---- N ProductFunction
```

---

## 名詞定義

### 產品

產品是設備的型號或商品主檔，例如某一款按鈕、控制器或感測器。

產品資料儲存在 `products` 資料表。

### 產品功能

產品功能描述某一產品支援哪些操作或能力，例如：

- 單擊。
- 長按。
- 開關控制。
- 上鎖狀態回報。

產品功能資料儲存在 `product_functions` 資料表，並歸屬於某一個產品。

### 設備

設備是實際存在的一台硬體，儲存在既有 `devices` 資料表。

本次開發會在 `devices` 新增 `product_id`，用來表示設備屬於哪一個產品。

---

## 核心規則

1. 產品主檔由管理後台維護。
2. 服務管理員可以查看產品、產品功能，以及設備對應的產品。
3. 系統管理員可以新增與修改產品主檔。
4. 系統管理員可以新增與修改產品功能。
5. 產品功能代碼由系統產生，必須全域唯一。
6. 設備同一時間只能屬於一個產品。
7. 新增設備主檔時必須指定產品。
8. 既有設備若在 migration 前已存在，允許暫時沒有產品，後台顯示為「未指定產品」。
9. 不得因客戶加入設備到房間而自動建立產品。
10. 不得因客戶加入設備到房間而修改設備的產品。
11. 客戶端只能查看房間內設備所屬產品的安全公開資訊，不可修改產品或產品功能。
12. 產品刪除不列入第一版，避免造成既有設備失去產品關聯。

---

## 實作決策與限制

以下為本文件的強制決策：

1. 產品使用 `public_id` 作為對外識別碼，避免在 URL 或 API 中暴露資料庫自增 ID。
2. `products.model_number` 必須唯一。
3. `product_functions.code` 必須全域唯一。
4. `devices.product_id` 在資料庫層級先允許 `null`，以支援既有資料 migration。
5. 新增設備 API 在應用層必須要求 `product_public_id`。
6. 產品功能代碼由後端產生，不由管理員手動輸入。
7. 產品與產品功能第一版只支援建立與修改，不支援刪除。
8. 必須沿用現有專案架構：
   - Laravel
   - Inertia + React
   - 既有管理後台 Layout 與 CSS
   - 既有 `ApiController` response 格式
   - Spatie permission
   - `ManageActionLogger`
9. 權限不足一律由 Laravel permission middleware 回傳 HTTP 403。
10. 所有 Phase 完成後都必須執行對應測試；最後一個 Phase 必須執行完整 `php artisan test` 與 `npm run build`。

---

## 權限設計

### 管理後台權限

新增 permissions：

|Permission|說明|預設角色|
|---|---|---|
|`manage.products.view`|查看產品列表|`service_manager`、`system_admin`|
|`manage.products.detail`|查看產品詳細資料|`service_manager`、`system_admin`|
|`manage.products.create`|建立產品主檔|`system_admin`|
|`manage.products.update`|修改產品主檔|`system_admin`|
|`manage.product_functions.create`|建立產品功能|`system_admin`|
|`manage.product_functions.update`|修改產品功能|`system_admin`|

角色規則：

- `service_manager`
  - 可以查看產品與產品功能。
  - 可以查看設備屬於哪個產品。
  - 不可建立或修改產品。
  - 不可建立或修改產品功能。
- `system_admin`
  - 擁有全部產品與產品功能權限。

Controller 不得直接判斷 role 名稱，必須以 permission 判斷功能權限。

### 客戶端權限

客戶端不新增產品管理權限。

|操作|權限|
|---|---|
|查看房間內設備的產品資訊|房間成員|
|修改產品主檔|不開放|
|修改產品功能|不開放|
|修改設備所屬產品|不開放|

---

## 資料模型

### products

新增資料表：

```text
id
public_id unique
model_number unique
name
created_at
updated_at
```

欄位說明：

|欄位|說明|
|---|---|
|`id`|資料庫內部 ID|
|`public_id`|對外識別碼，用於 URL、API 與 audit log|
|`model_number`|產品型號，必填且唯一|
|`name`|產品名稱，必填|

規則：

- `public_id` 由系統產生，不允許使用者輸入。
- `model_number` 建立與查詢時一律執行 `mb_strtoupper(trim($modelNumber))`。
- `model_number` 不可重複。
- `name` 允許修改。
- 第一版不實作刪除產品。

### product_functions

新增資料表：

```text
id
product_id foreign
code unique
description
created_at
updated_at
```

欄位說明：

|欄位|說明|
|---|---|
|`id`|資料庫內部 ID|
|`product_id`|所屬產品|
|`code`|功能代碼，由系統產生，全域唯一|
|`description`|功能說明，必填，可修改|

規則：

- `code` 由後端產生，格式建議為大寫隨機字串，例如 `PFN-XXXXXXXXXXXX`。
- `code` 必須全域唯一。
- `description` 允許修改。
- 第一版不實作刪除產品功能。
- 同一產品底下允許多個功能。

### devices

擴充既有資料表：

```text
id
product_id nullable foreign
serial_number unique
secret_hash
current_room_id nullable
name nullable
is_locked
is_enabled
created_at
updated_at
```

新增欄位：

|欄位|說明|
|---|---|
|`product_id`|設備所屬產品；既有設備可暫時為 `null`|

規則：

- 新增設備主檔時必須指定產品。
- `product_id` 指向 `products.id`。
- 第一版不允許從管理後台修改既有設備的產品，除非另行實作「設備補指定產品」功能。
- 客戶加入設備到房間時不得修改 `product_id`。
- 設備列表與詳細資料應顯示產品資訊。

---

## Model 關聯

新增：

```text
App\Models\Product
App\Models\ProductFunction
```

關聯：

```php
Product hasMany Device
Product hasMany ProductFunction
ProductFunction belongsTo Product
Device belongsTo Product
```

`Device` model 新增：

```php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}
```

`Product` model 建議：

- `$fillable = ['public_id', 'model_number', 'name']`
- `devices()`
- `functions()`

`ProductFunction` model 建議：

- `$fillable = ['product_id', 'code', 'description']`
- `product()`

---

## 後端服務設計

### ProductCatalogService

新增：

```text
App\Services\Manage\ProductCatalogService
```

負責：

- 建立產品。
- 修改產品名稱與型號。
- 正規化產品型號。
- 檢查產品型號是否重複。
- 建立產品功能。
- 修改產品功能說明。
- 產生唯一產品功能代碼。
- 寫入管理後台操作紀錄。

Controller 不應直接處理：

- 型號正規化。
- unique 衝突轉換。
- 功能代碼產生。
- audit log metadata 組裝。

### DeviceCatalogService 調整

既有：

```text
App\Services\Manage\DeviceCatalogService
```

調整：

- 建立設備時新增 `product_public_id` 參數。
- 使用 `product_public_id` 查詢產品。
- 建立設備時寫入 `product_id`。
- 建立設備 audit log metadata 增加產品資訊。

建立設備時若產品不存在，回傳：

```text
PRODUCT_NOT_FOUND
```

---

## 管理後台頁面

### 產品一覽頁

路徑：

```text
/manage/products
```

權限：

```text
manage.products.view
```

顯示欄位：

|欄位|說明|
|---|---|
|產品 ID|`public_id`|
|產品型號|`model_number`|
|產品名稱|`name`|
|功能數量|`product_functions` count|
|設備數量|`devices` count|
|建立時間|`created_at`|
|更新時間|`updated_at`|

支援：

- 依產品 ID 搜尋。
- 依產品型號搜尋。
- 依產品名稱搜尋。
- 分頁。

操作：

- 有 `manage.products.detail` 權限者可以進入詳細頁。
- 有 `manage.products.create` 權限者顯示「新增產品」按鈕。

### 產品詳細頁

路徑：

```text
/manage/products/{product_public_id}
```

權限：

```text
manage.products.detail
```

顯示內容：

- 產品 ID。
- 產品型號。
- 產品名稱。
- 建立時間。
- 更新時間。
- 產品功能列表。
- 關聯設備列表摘要。

產品功能列表顯示：

|欄位|說明|
|---|---|
|功能代碼|`code`|
|功能說明|`description`|
|建立時間|`created_at`|
|更新時間|`updated_at`|

關聯設備列表顯示：

|欄位|說明|
|---|---|
|設備序號|`serial_number`|
|所在房間|已加入房間時顯示房間公開 ID 與名稱|
|啟用狀態|`is_enabled`|
|鎖定狀態|`is_locked`|

操作：

- 有 `manage.products.update` 權限者可以修改產品型號與名稱。
- 有 `manage.product_functions.create` 權限者可以新增產品功能。
- 有 `manage.product_functions.update` 權限者可以修改產品功能說明。

### 新增產品頁

路徑：

```text
/manage/products/create
```

權限：

```text
manage.products.create
```

欄位：

|欄位|規則|
|---|---|
|產品型號|必填，正規化後唯一|
|產品名稱|必填|

建立成功後：

- 產生 `public_id`。
- 寫入 `manage_action_logs`。
- 導向產品詳細頁。

### 修改產品頁或彈窗

可使用獨立頁面或詳細頁內彈窗。

權限：

```text
manage.products.update
```

可修改：

- 產品型號。
- 產品名稱。

不可修改：

- `public_id`。
- 已關聯設備。
- 產品功能代碼。

### 設備新增頁調整

既有：

```text
/manage/devices/create
```

新增欄位：

|欄位|規則|
|---|---|
|產品|必填，輸入產品型號時顯示相近的既有產品建議，選擇後帶入產品|

產品欄位互動規劃：

- 欄位顯示名稱建議為「產品型號」。
- 使用者輸入時，以目前輸入文字查詢相近的 `model_number`。
- 建議清單至少顯示：
  - 產品型號 `model_number`。
  - 產品名稱 `name`。
- 使用者點選建議後：
  - 輸入框顯示選到的 `model_number`。
  - 表單內部保存該產品的 `product_public_id`。
- 送出建立設備時，只送出 `product_public_id`，不得只送出文字型號。
- 若使用者修改輸入框文字，且文字不再符合已選產品，必須清空已保存的 `product_public_id`。
- 若沒有相近產品，顯示「找不到相近產品」。
- 送出時若尚未選定產品，顯示「請從建議清單選擇產品」。
- 建議查詢應 debounce，建議 250ms 到 400ms，避免每次按鍵都立即送出 API。
- 建議清單最多顯示 10 筆。
- 查詢時使用正規化後的產品型號比對，但畫面顯示資料庫中的 `model_number`。

送出 API 時使用：

```json
{
  "product_public_id": "PRD-XXXXXXXXXXXX",
  "serial_number": "DEVICE-000001",
  "secret": "physical-device-secret",
  "secret_confirmation": "physical-device-secret"
}
```

### 設備列表與詳細頁調整

設備列表新增顯示：

- 產品型號。
- 產品名稱。

設備詳細頁新增顯示：

- 產品 ID。
- 產品型號。
- 產品名稱。
- 產品功能列表。
- 產品詳細頁連結。

若 `product_id = null`：

- 顯示「未指定產品」。
- 不顯示產品功能。

---

## 客戶前台調整

房間設備列表可顯示設備所屬產品資訊：

- 產品型號。
- 產品名稱。

是否顯示產品功能，第一版建議只顯示在設備詳細或折疊區，不直接塞入設備卡片主畫面，避免 UI 過於雜亂。

客戶端不可執行：

- 建立產品。
- 修改產品。
- 建立產品功能。
- 修改產品功能。
- 修改設備所屬產品。

---

## API 規劃

### 管理後台產品 API

#### 產品列表

```http
GET /manage/api/products
```

權限：

```text
manage.products.view
```

Query parameters：

```text
search
suggest
page
per_page
```

`suggest=true` 用於建立設備頁的產品型號建議提示。

建議查詢規則：

- 僅回傳必要欄位：
  - `public_id`
  - `model_number`
  - `name`
- 預設 `per_page = 10`。
- 使用 `search` 比對 `model_number` 與 `name`。
- `model_number` 查詢應套用與產品建立相同的正規化規則。
- 結果排序優先順序：
  1. `model_number` 完全相同。
  2. `model_number` 以輸入值開頭。
  3. `model_number` 包含輸入值。
  4. `name` 包含輸入值。

Response item：

```json
{
  "public_id": "PRD-XXXXXXXXXXXX",
  "model_number": "BTN-001",
  "name": "智慧按鈕",
  "function_count": 3,
  "device_count": 20,
  "created_at": "2026-06-25T00:00:00.000000Z",
  "updated_at": "2026-06-25T00:00:00.000000Z"
}
```

#### 產品詳細

```http
GET /manage/api/products/{product_public_id}
```

權限：

```text
manage.products.detail
```

Response 應包含：

- 產品基本資料。
- 產品功能列表。
- 關聯設備分頁摘要。

關聯設備列表若資料量大，建議使用獨立分頁欄位：

```text
devices_page
devices_per_page
```

#### 建立產品

```http
POST /manage/api/products
```

權限：

```text
manage.products.create
```

Request：

```json
{
  "model_number": "BTN-001",
  "name": "智慧按鈕"
}
```

#### 修改產品

```http
PATCH /manage/api/products/{product_public_id}
```

權限：

```text
manage.products.update
```

Request：

```json
{
  "model_number": "BTN-001",
  "name": "智慧按鈕"
}
```

### 管理後台產品功能 API

#### 建立產品功能

```http
POST /manage/api/products/{product_public_id}/functions
```

權限：

```text
manage.product_functions.create
```

Request：

```json
{
  "description": "短按觸發"
}
```

Response：

```json
{
  "code": "PFN-XXXXXXXXXXXX",
  "description": "短按觸發"
}
```

#### 修改產品功能

```http
PATCH /manage/api/product-functions/{code}
```

權限：

```text
manage.product_functions.update
```

Request：

```json
{
  "description": "短按觸發"
}
```

### 管理後台設備 API 調整

#### 建立設備

既有：

```http
POST /manage/api/devices
```

新增必填欄位：

```json
{
  "product_public_id": "PRD-XXXXXXXXXXXX",
  "serial_number": "DEVICE-000001",
  "secret": "physical-device-secret",
  "secret_confirmation": "physical-device-secret"
}
```

Response 新增：

```json
{
  "product": {
    "public_id": "PRD-XXXXXXXXXXXX",
    "model_number": "BTN-001",
    "name": "智慧按鈕"
  }
}
```

#### 設備列表與詳細

既有：

```http
GET /manage/api/devices
GET /manage/api/devices/{serial_number}
GET /manage/api/rooms/{room_public_id}/devices
```

Response item 新增：

```json
{
  "product": {
    "public_id": "PRD-XXXXXXXXXXXX",
    "model_number": "BTN-001",
    "name": "智慧按鈕"
  }
}
```

若無產品：

```json
{
  "product": null
}
```

### 客戶 API 調整

既有：

```http
GET /api/rooms/{room}/devices
```

Response item 可新增安全產品資訊：

```json
{
  "product": {
    "model_number": "BTN-001",
    "name": "智慧按鈕"
  }
}
```

客戶 API 不回傳：

- `product.id`
- `product.public_id`
- 任何管理用 metadata
- 隱碼或 `secret_hash`

---

## Middleware 與路由順序

管理後台頁面共同 middleware：

```text
auth
manage.authenticated
permission:manage.access
```

頁面路由：

```text
GET /manage/products
GET /manage/products/create
GET /manage/products/{product_public_id}
```

注意：

- `/manage/products/create` 必須在 `/manage/products/{product_public_id}` 前註冊。
- API 的 `POST /manage/api/products` 與 `GET /manage/api/products` 必須套用不同 permission。

---

## 操作紀錄

管理後台產品操作應使用既有 `manage_action_logs`。

固定 action：

```text
products.create
products.update
products.detail.view
product_functions.create
product_functions.update
```

設備建立既有 action：

```text
devices.create
```

需在 metadata 增加產品資訊：

```json
{
  "before": null,
  "after": {
    "serial_number": "DEVICE-000001",
    "product_public_id": "PRD-XXXXXXXXXXXX",
    "product_model_number": "BTN-001",
    "current_room_public_id": null,
    "is_locked": false,
    "is_enabled": true
  }
}
```

禁止在產品與設備 audit metadata 中寫入：

- 設備隱碼明文。
- `secret_hash`。
- 完整 request body。

---

## 錯誤碼

新增：

```text
PRODUCT_NOT_FOUND
PRODUCT_MODEL_ALREADY_EXISTS
PRODUCT_FUNCTION_NOT_FOUND
PRODUCT_FUNCTION_CODE_ALREADY_EXISTS
```

建議語意：

|錯誤碼|說明|
|---|---|
|`PRODUCT_NOT_FOUND`|指定的產品不存在|
|`PRODUCT_MODEL_ALREADY_EXISTS`|產品型號已存在|
|`PRODUCT_FUNCTION_NOT_FOUND`|指定的產品功能不存在|
|`PRODUCT_FUNCTION_CODE_ALREADY_EXISTS`|產品功能代碼產生時發生唯一值衝突|

權限不足一律使用 HTTP 403，不使用業務錯誤碼。

---

## 安全要求

1. 客戶端不可修改任何產品資料。
2. 客戶端不可取得產品資料庫內部 ID。
3. 管理後台 API 不可直接序列化完整 Eloquent model，必須使用 payload 白名單。
4. 建立設備時若產品不存在，不得自動建立產品。
5. 建立產品功能時，功能代碼必須由後端產生。
6. 修改產品功能時不得修改功能代碼。
7. 產品 model number 正規化規則必須集中於共用 helper 或 service。
8. 所有建立與修改操作必須寫入 audit log。
9. 建立產品、修改產品、建立產品功能、修改產品功能都必須使用 transaction。
10. 產品不可刪除，避免設備歷史資料失去關聯。

---

## 測試規劃

### RBAC 測試

- `service_manager` 可以查看產品列表。
- `service_manager` 可以查看產品詳細。
- `service_manager` 不可建立產品。
- `service_manager` 不可修改產品。
- `service_manager` 不可建立產品功能。
- `service_manager` 不可修改產品功能。
- `system_admin` 可以查看、建立與修改產品。
- `system_admin` 可以建立與修改產品功能。
- 一般使用者不可存取 `/manage/products*` 頁面或 API。

### 產品主檔測試

- 建立產品時型號與名稱必填。
- 建立產品會產生唯一 `public_id`。
- 建立產品時 `model_number` 會正規化。
- 正規化後相同的 `model_number` 不可重複建立。
- 修改產品時可修改型號與名稱。
- 修改產品時不可使用已存在型號。
- 建立與修改產品會寫入 `manage_action_logs`。

### 產品功能測試

- 系統管理員可以為產品建立功能。
- 建立功能時只需輸入功能說明。
- 功能代碼由後端產生。
- 功能代碼全域唯一。
- 可以修改功能說明。
- 不可修改功能代碼。
- 不存在的產品不可建立功能。
- 建立與修改功能會寫入 `manage_action_logs`。

### 設備整合測試

- 系統管理員建立設備時必須指定產品。
- 系統管理員建立設備頁輸入產品型號時，會顯示相近 `model_number` 建議。
- 系統管理員必須從建議清單選定產品後才能建立設備。
- 建立設備時若產品輸入文字存在但未選定 `product_public_id`，前端不得送出。
- 指定不存在產品時回傳 `PRODUCT_NOT_FOUND`。
- 建立設備後 `devices.product_id` 正確。
- 設備列表顯示產品型號與產品名稱。
- 設備詳細顯示產品資訊與產品功能。
- 房間設備列表可顯示產品資訊。
- 客戶加入設備到房間不會修改產品關聯。
- 客戶 API 不回傳產品內部 ID。
- 既有無產品設備在後台顯示為「未指定產品」。

### Migration 測試

- `products` 與 `product_functions` 可正常建立。
- `devices.product_id` 可在既有資料存在時成功新增。
- 新資料可正確建立外鍵關聯。
- 產品被設備引用時不可刪除。

---

## Phase 分割建議

### Phase 1：資料模型與 RBAC

目標：

- 建立產品與產品功能資料結構。
- 建立產品管理權限。

包含：

- 新增 `products` migration。
- 新增 `product_functions` migration。
- 新增 `devices.product_id` migration。
- 新增 `Product` model。
- 新增 `ProductFunction` model。
- 更新 `Device` model 關聯。
- 新增產品與產品功能 permissions。
- 更新 `ManagementRbac` 與 seeder。

完成條件：

- migration 可在既有資料上成功執行。
- `service_manager` 與 `system_admin` 權限符合規劃。
- Model 關聯可正常查詢。

Phase 完成後：

- 執行 migration 與 RBAC 相關測試。
- 建立 `Phase 1: Product data model and RBAC` commit。

### Phase 2：產品管理 API

目標：

- 讓管理後台可以查詢、建立與修改產品。

包含：

- 建立 `ProductCatalogService`。
- 建立管理後台產品列表 API。
- 建立管理後台產品詳細 API。
- 建立產品 API。
- 修改產品 API。
- 型號正規化。
- 型號重複檢查。
- audit log。

完成條件：

- 服務管理員可以查看產品。
- 系統管理員可以建立與修改產品。
- 一般使用者不可存取產品 API。

Phase 完成後：

- 執行產品 API、RBAC、audit tests。
- 建立 `Phase 2: Product management API` commit。

### Phase 3：產品功能 API

目標：

- 讓系統管理員可以維護產品支援的功能。

包含：

- 建立產品功能 API。
- 修改產品功能 API。
- 後端產生唯一功能代碼。
- 產品詳細 API 回傳產品功能。
- audit log。

完成條件：

- 系統管理員可以新增與修改產品功能。
- 服務管理員只能查看產品功能。
- 功能代碼不可由使用者指定或修改。

Phase 完成後：

- 執行產品功能 API、唯一代碼與 audit tests。
- 建立 `Phase 3: Product functions API` commit。

### Phase 4：設備與產品整合

目標：

- 讓設備主檔正式關聯產品。

包含：

- 調整 `DeviceCatalogService`。
- 建立設備 API 新增 `product_public_id` 驗證。
- 設備 payload 新增產品資訊。
- 設備列表、詳細、房間設備 API 載入產品。
- 客戶房間設備 API 回傳安全產品資訊。
- 確認客戶加入設備不會修改產品關聯。

完成條件：

- 新增設備時必須選擇既有產品。
- 設備列表與詳細可以看到產品資訊。
- 客戶房間設備列表可看到產品名稱與型號。
- 舊設備未指定產品時系統仍可正常運作。

Phase 完成後：

- 執行設備建立、設備查詢、客戶房間設備 tests。
- 建立 `Phase 4: Link devices to products` commit。

### Phase 5：管理後台頁面

目標：

- 完成產品管理 UI 與設備頁面產品資訊。

包含：

- `/manage/products`
- `/manage/products/create`
- `/manage/products/{product_public_id}`
- 產品修改介面。
- 產品功能新增與修改介面。
- 設備新增頁加入產品型號輸入與相近 `model_number` 建議提示。
- 設備列表與詳細頁顯示產品資訊。
- 房間設備區塊顯示產品資訊。

完成條件：

- 服務管理員可以從後台查看產品與產品功能。
- 系統管理員可以從後台新增與修改產品。
- 系統管理員可以從後台新增與修改產品功能。
- 系統管理員新增設備時必須從產品型號建議清單選擇產品。

Phase 完成後：

- 執行頁面權限測試。
- 執行 `npm run build`。
- 建立 `Phase 5: Product management UI` commit。

### Phase 6：整合驗收與安全檢查

目標：

- 確認權限、資料安全、設備整合與 UI 都符合預期。

包含：

- 完整 RBAC matrix 測試。
- 產品 API 測試。
- 產品功能 API 測試。
- 設備建立與產品關聯測試。
- 客戶 API 安全 payload 測試。
- audit log 測試。
- migration rollback 測試。
- 前端 build。

完成條件：

- 所有角色只能執行文件允許的操作。
- 客戶端不能修改產品。
- 客戶端不會取得產品內部 ID。
- 設備與產品關聯正確。
- 產品功能代碼唯一且不可由使用者指定。

Phase 完成後：

- 執行完整 `php artisan test`。
- 執行 `npm run build`。
- 建立 `Phase 6: Product feature verification` commit。

---

## 第一版不處理項目

以下功能不列入本次產品主檔第一版：

- 產品刪除。
- 產品功能刪除。
- 客戶端產品管理。
- 產品圖片。
- 產品版本管理。
- 韌體版本管理。
- 每台設備覆寫產品功能。
- 產品功能實際執行流程。
- 依產品限制設備可執行的房間操作。

這些項目應等產品主檔與設備關聯穩定後，再另開規劃文件。
