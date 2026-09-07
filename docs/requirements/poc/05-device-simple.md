# 設備功能簡易開發規劃

## 目標

- 服務管理員可以在管理後台查看資料庫中的所有設備。
- 服務管理員可以確認設備位於哪一間房間，或尚未加入房間。
- 服務管理員可以在房間詳細頁查看該房間的設備。
- 只有系統管理員可以建立設備主檔。
- 客戶只能將資料庫中已存在的設備加入房間。
- 房間成員可以查看自己房間內的設備。

## 核心規則

1. 設備序號全域唯一。
2. 設備隱碼只儲存 hash，不儲存明文。
3. 頁面、API、錯誤訊息及 log 都不得顯示隱碼或 `secret_hash`。
4. 新建立的設備：
   - `current_room_id = null`
   - `name = null`
   - `is_locked = false`
   - 不屬於任何房間或使用者。
5. 設備同一時間只能屬於一間房間。
6. 房主使用序號與隱碼將設備加入房間。
7. 設備不存在時回傳 `DEVICE_NOT_FOUND`，不得自動建立。
8. 設備移轉、鎖定及移除規則沿用 `docs/requirements/poc/02-room.md`。

## 權限

新增管理權限：

|Permission|服務管理員|系統管理員|
|---|---:|---:|
|`manage.devices.view`|是|是|
|`manage.devices.detail`|是|是|
|`manage.devices.create`|否|是|

客戶端權限：

|操作|權限|
|---|---|
|查看房間設備|房間成員|
|加入、命名、鎖定、解鎖或移除設備|房主|

## 資料表

沿用 `devices`：

```text
id
serial_number unique
secret_hash
current_room_id nullable
name nullable
is_locked
created_at
updated_at
```

沿用 `device_transfer_logs` 記錄設備第一次加入及房間間移轉。

序號在建立和查詢前應使用相同規則正規化，例如去除空白並統一為大寫。

## 管理後台

### 設備列表

路徑：

```text
/manage/devices
```

顯示：

- 設備序號。
- 是否已加入房間。
- 房間公開 ID 與名稱。
- 房間內設備名稱。
- 鎖定狀態。
- 建立及更新時間。

支援：

- 依序號或房間搜尋。
- 篩選已加入或未加入房間。
- 篩選鎖定狀態。
- 分頁。

### 設備詳細

路徑：

```text
/manage/devices/{serial_number}
```

顯示設備資料、目前房間及最近移轉紀錄，不顯示隱碼。

### 新增設備

路徑：

```text
/manage/devices/create
```

僅限 `manage.devices.create`。

欄位：

- 序號。
- 隱碼。
- 確認隱碼。

建立成功後設備維持未加入房間狀態，並寫入 `manage_action_logs`。

### 房間詳細頁

在既有：

```text
/manage/rooms/{room_public_id}
```

增加設備列表。管理員只能查看，不可在管理後台直接移轉、鎖定或移除設備。

## API

### 管理後台

```http
GET  /manage/api/devices
GET  /manage/api/devices/{serial_number}
POST /manage/api/devices
GET  /manage/api/rooms/{room_public_id}/devices
```

對應權限：

|API|Permission|
|---|---|
|設備列表|`manage.devices.view`|
|設備詳細|`manage.devices.detail`|
|建立設備|`manage.devices.create`|
|房間設備|`manage.rooms.detail` 與 `manage.devices.view`|

### 客戶端

沿用：

```http
GET  /api/rooms/{room}/devices
POST /api/rooms/{room}/devices
```

加入設備流程：

1. 檢查操作者是房主。
2. 依序號查詢並鎖定設備資料。
3. 設備不存在時拒絕操作。
4. 驗證隱碼。
5. 套用鎖定及移轉規則。
6. 更新設備房間資料。
7. 寫入移轉紀錄。

## 後端結構

新增：

```text
App\Http\Controllers\Manage\DeviceController
App\Services\Manage\DeviceCatalogService
```

`DeviceCatalogService` 負責：

- 序號正規化。
- 重複序號檢查。
- 隱碼 hash。
- 建立設備。
- 寫入管理操作紀錄。

客戶加入設備繼續使用既有 `App\Services\DeviceService`。

## 稽核與安全

- 建立設備記錄 `devices.create`。
- 查看詳細資料可記錄 `devices.detail.view`。
- audit metadata 不得包含隱碼、`secret_hash` 或完整 request body。
- 建立設備與加入房間使用 transaction。
- 加入房間時使用 `lockForUpdate()`，避免同一設備同時加入不同房間。
- 由資料庫 unique constraint 最終防止重複序號。

## 開發階段

### Phase 1：權限與查詢

- 新增三個設備 permissions。
- 更新 `service_manager` 與 `system_admin` 權限。
- 完成設備列表、詳細及房間設備 API。

完成條件：服務管理員可查看所有設備及設備所在房間。

### Phase 2：建立設備

- 建立 `DeviceCatalogService`。
- 完成建立設備 API。
- 實作序號正規化、隱碼 hash、重複檢查及 audit log。

完成條件：只有系統管理員可建立未加入房間的設備。

### Phase 3：管理後台頁面

- 建立設備列表、詳細及新增頁面。
- 在房間詳細頁顯示設備。
- 依 permission 顯示新增設備入口。

完成條件：服務管理員可查看，系統管理員可新增。

### Phase 4：客戶流程與測試

- 確認未知設備不會自動建立。
- 確認房間成員可查看設備，只有房主可管理設備。
- 補齊權限、敏感資料、重複序號、移轉及 audit tests。
- 執行後端測試與前端 build。

完成條件：角色權限正確，設備資料安全，主要流程皆有測試。
