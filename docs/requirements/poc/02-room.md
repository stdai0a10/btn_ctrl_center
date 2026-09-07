# 房間設備管理功能

房間設備管理

## 規格

### models

#### 使用者 (人)

- 是使用者
- 有公開 ID，全域唯一

#### 房間 (房間、家庭)

- 虛擬的
- 有公開 ID，全域唯一且不可變更
- 由人建立，建立完該人即變成房主
- 刪除房間
  - 在房間無其他成員，只剩最後一名房主時，該名最後的房主退出同時會刪除房間

#### 設備

- 設備上刻有序號，全域唯一，會在服務中顯示
- 設備上刻有隱碼，不會在服務中顯示，只能透過肉眼讀取

### 關係

#### 房間和人的關係

- 人在房間裡的身分
  - 住戶: 加入房間後即為住戶
  - 房主: 一間房間內有比較高身份的住戶
- 住戶/房主是「使用者在某房間中的身份」，不是全域身份
- 使用者在同一房間內只能有一種身份
- 房主
  - 房間至少有一名房主
  - 任何操作都不得讓房間變成無房主
- 房主權限
  - 房主可以邀請人加入房間
  - 房主可以把同房間的住戶變成房主
  - 房主可以把同房間的房主(包括自己)變成住戶，但如果房間只有一名房主，該房主不得將自己降為住戶
  - 房主可以加入設備到房間
- 房間可以容納多人
- 房間一定要有房主
- 房主可以不只一個
- 一人可以當多間房間的住戶
- 一人可以當多間房間的房主
- 加入人到房間的方式
  - 自建房間
  - 房主可以邀請人加入房間，受邀人同意後即加入
    - 受邀人可以選擇同意或忽略
    - 受邀人未選擇前該邀請都不會過期或消失
    - 房主可以取消邀請
  - 人請求加入房間，任一房主同意後即加入
    - 房主可以選擇同意或忽略
    - 房主未選擇前該請求都不會過期或消失
    - 可以取消請求
- 人退出房間
  - 由房主移除房間成員
    - 不能移除自己
    - 房主要移除自己只能透過"自行退出"
  - 自行退出，但不得造成房間無房主
    - 例外: 房間剩房主自己時可以退出，退出時等同刪除房間。

#### 房間和設備的關係

- 房間可以容納多部設備
- 設備同時只能屬於一間房間，不會同時出現在多間房間內
- 房主可以管理設備的啟用狀態
- 加入設備到房間
  - 房主才能進行
  - 需要 序號 & 隱碼
  - 加入房間後可以命名
  - 加入後可以上鎖
    - 上鎖後禁止移除
    - 上鎖後禁止轉移
  - 只有房主可以鎖定 / 解鎖設備
  - 在其他房間進行加入設備程序後，該設備即移轉到其他房間
    - 若設備已上鎖，其他房間即使輸入正確序號與隱碼，也不得移轉
    - 移轉後原設定全刪除
    - 移轉後原房間住戶不再可操作該設備
- 從房間移除設備
  - 房主才能進行
  - 要先解鎖設備後才能移除
  - 刪除房間時會強制解除所有設備，包含上鎖設備

## 開發建議

### 技術架構

- 前端：React
  - 建議使用 React Router 管理頁面
  - API 狀態建議使用 TanStack Query 或自訂 hooks
  - 表單可使用 React Hook Form
  - 重要操作需有 loading、錯誤訊息、確認視窗

- 後端：Laravel 12 + MySQL
  - API 採 RESTful 設計
  - 權限判斷建議集中於 Policy / Service
  - 複雜操作使用 DB Transaction
  - 邀請、請求、設備移轉等操作需避免 race condition

### 資料表設計建議

#### users

帳號註冊登入已完成，沿用既有 users。

```text
id
public_id
name
email
created_at
updated_at
```

#### rooms

房間資料：

```text
id
public_id
name
created_by_user_id
created_at
updated_at
deleted_at
```

建議：

- public_id 使用 UUID / ULID
- 設 unique index
- 不允許修改
- 建議 soft delete

#### room_user

房間與成員關聯：

```text
id
room_id
user_id
role
joined_at
created_at
updated_at
```

role：

```text
owner
resident
```

索引：

```text
unique(room_id, user_id)
index(user_id)
index(room_id, role)
```

規則：

- 一人同房間僅一種身份
- 房間至少保有一位房主

#### room_invitations

```text
id
room_id
inviter_user_id
invitee_user_id
status
created_at
updated_at
cancelled_at
accepted_at
ignored_at
```

status：

```text
pending
accepted
ignored
cancelled
```

#### room_join_requests

```text
id
room_id
requester_user_id
status
approved_by_user_id
created_at
updated_at
cancelled_at
accepted_at
ignored_at
```

status：

```text
pending
accepted
ignored
cancelled
```

#### devices

```text
id
serial_number
secret_hash
current_room_id
name
is_locked
created_at
updated_at
```

建議：

- serial_number unique
- 隱碼只存 hash
- API 不回傳隱碼
- current_room_id nullable

#### device_transfer_logs

```text
id
device_id
from_room_id
to_room_id
transferred_by_user_id
created_at
```

用於設備移轉追蹤。

### Service 分層建議

```text
App\Services\RoomService
App\Services\RoomMemberService
App\Services\RoomInvitationService
App\Services\RoomJoinRequestService
App\Services\DeviceService
```

#### RoomService

負責：

- 建立房間
- 刪除房間
- 最後房主退出刪除房間
- 刪房同步解除設備

#### RoomMemberService

負責：

- 成員管理
- 角色升降
- 移除成員
- 自行退出
- 房主數量檢查

#### RoomInvitationService

負責：

- 發送邀請
- 取消邀請
- 接受邀請
- 忽略邀請

#### RoomJoinRequestService

負責：

- 申請加入
- 取消申請
- 同意申請
- 忽略申請

#### DeviceService

負責：

- 加入設備
- 設備移轉
- 設備命名
- 上鎖/解鎖
- 移除設備

### 權限設計

建議建立：

```text
RoomPolicy
RoomMemberPolicy
DevicePolicy
```

共用判斷：

```php
isRoomOwner(User $user, Room $room)
isRoomMember(User $user, Room $room)
canKeepAtLeastOneOwner(Room $room)
canManageDevice(User $user, Room $room)
```

權限：

|操作|權限|
|---|---|
|查看房間|房間成員|
|邀請人|房主|
|同意加入請求|房主|
|移除成員|房主|
|自行退出|本人|
|角色升降|房主|
|加入設備|房主|
|移除設備|房主|
|鎖定/解鎖設備|房主|

### API 設計

#### 房間 API

```http
GET    /api/rooms
POST   /api/rooms
GET    /api/rooms/{room}
PATCH  /api/rooms/{room}
DELETE /api/rooms/{room}
```

#### 房間成員 API

```http
GET    /api/rooms/{room}/members
DELETE /api/rooms/{room}/members/{user}
POST   /api/rooms/{room}/leave
PATCH  /api/rooms/{room}/members/{user}/role
```

#### 邀請 API

```http
GET    /api/room-invitations
POST   /api/rooms/{room}/invitations
POST   /api/room-invitations/{invitation}/accept
POST   /api/room-invitations/{invitation}/ignore
POST   /api/room-invitations/{invitation}/cancel
```

#### 加入請求 API

```http
GET    /api/room-join-requests
POST   /api/rooms/{room}/join-requests
POST   /api/room-join-requests/{request}/accept
POST   /api/room-join-requests/{request}/ignore
POST   /api/room-join-requests/{request}/cancel
```

#### 設備 API

```http
GET    /api/rooms/{room}/devices
POST   /api/rooms/{room}/devices
PATCH  /api/rooms/{room}/devices/{device}
DELETE /api/rooms/{room}/devices/{device}
POST   /api/rooms/{room}/devices/{device}/lock
POST   /api/rooms/{room}/devices/{device}/unlock
```

### Transaction 建議

以下操作需包 transaction：

- 建立房間 + 建立房主關聯
- 接受邀請
- 接受加入請求
- 房主降級
- 房主退出
- 成員移除
- 設備加入
- 設備移轉
- 刪除房間

設備移轉建議：

```php
Device::where('serial_number', $serialNumber)
    ->lockForUpdate()
    ->first();
```

避免同時移轉 race condition。

### React 前端頁面建議

#### 房間列表頁

功能：

- 我的房間列表
- 建立房間
- 顯示住戶/房主身份

#### 房間詳情頁

功能：

- 房間資訊
- 成員管理
- 設備管理
- 邀請管理
- 加入請求管理
- 自行退出

房主額外功能：

- 邀請人
- 升降角色
- 移除成員
- 新增設備
- 鎖定設備
- 解鎖設備

#### 我的邀請頁

功能：

- 查看收到邀請
- 接受
- 忽略

#### 設備加入頁

欄位：

```text
序號
隱碼
設備名稱
加入後是否上鎖
```

提示：

- 可能觸發設備移轉
- 上鎖設備不可移轉
- 移轉會清空原設定

### 錯誤碼建議

```text
ROOM_LAST_OWNER_REQUIRED
ROOM_MEMBER_ALREADY_EXISTS
ROOM_INVITATION_ALREADY_PENDING
ROOM_JOIN_REQUEST_ALREADY_PENDING
ROOM_OWNER_CANNOT_REMOVE_SELF
DEVICE_NOT_FOUND
DEVICE_SECRET_INVALID
DEVICE_LOCKED
DEVICE_ALREADY_IN_THIS_ROOM
DEVICE_NOT_IN_ROOM
DEVICE_MUST_UNLOCK_BEFORE_REMOVE
```

範例：

```json
{
  "message": "Cannot remove the last owner.",
  "code": "ROOM_LAST_OWNER_REQUIRED"
}
```

### 測試建議

Feature Test 至少覆蓋：

- 建立房間自動成為房主
- 房間不能沒有房主
- 最後房主退出刪房
- 邀請流程
- 加入申請流程
- 房主升降權限
- 自己不可被移除
- 唯一房主不可降級
- 設備加入驗證
- 上鎖設備不可移除
- 上鎖設備不可移轉
- 設備移轉後原房間不可操作
- 刪房解除所有設備

## Phase 分割建議

### Phase 1 核心資料與房間管理

- 建立資料表與 Migration
- Room / Member 基礎 Model 關聯
- 建立房間 API
- 房間成員管理
- 權限 Policy

### Phase 2 成員加入流程

- 邀請加入流程
- 申請加入流程
- 成員角色升降
- 退出與刪房邏輯

### Phase 3 設備管理

- 加入設備
- 設備命名
- 設備上鎖 / 解鎖
- 移除設備
- 設備移轉
- Transfer log

### Phase 4 前端管理介面

- 房間列表頁
- 房間詳情頁
- 邀請頁
- 設備管理頁

### Phase 5 穩定化

- Feature Test
- 例外處理
- 錯誤碼整理
- Transaction / race condition 檢查
- API 文件
