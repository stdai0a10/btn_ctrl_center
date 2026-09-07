# 帳號註冊登入

## 規格

1. 使用者帳號
   1. 每個使用者只有一個 User ID
   2. 登入方式都是透過綁定追加
   3. 每種第三方登入身分僅能綁定至一個 user
   4. 解除任一登入方式時，帳號須至少保留一種有效登入方式，否則不得解除
   5. 敏感操作前需重新驗證身分 (見 使用者自我管理)
      1. 若帳號已設定密碼，則要求重新輸入密碼
      2. 若尚未設定，則需透過既有登入方式完成重新驗證
2. 使用者自我管理
   1. 顯示資料
      1. ID: 不和他人重複的英數混和字串，創建時由系統給予，無法變更
      2. 名字:
      3. EMAIL: EMAIL & 驗證狀態
         1. 目前已驗證 EMAIL
         2. 待驗證新 EMAIL
      4. 密碼: 設置狀態
      5. LINE: 綁定狀態
   2. 名字設定
      1. 可自由修改
      2. 可與他人重複
      3. 若未設定則顯示 ID
   3. EMAIL 設定
      1. EMAIL 可申請變更
      2. 如果已被他人使用 (不論驗證完成與否)
         1. 如果是已被驗證，提示此 EMAIL 已被其他帳號使用
         2. 如果是未完成驗證或未完成註冊，提示此 EMAIL 仍處於保留有效期間內
      3. 須完成 EMAIL 驗證後才會覆蓋目前設定
      4. 驗證後不提供直接移除，只能完成新 EMAIL 驗證後取代
      5. 更換 EMAIL 需重新驗證身分
   4. 密碼設定(變更)
      1. 要驗證完 EMAIL 才能設定
      2. 設定密碼後即可以 EMAIL & 密碼 登入
      3. 如果之前設定過密碼，需驗證當前密碼後才能變更
      4. 密碼規則
         1. 密碼長度最低 12 碼
         2. 禁止常見弱密碼 (前端處理)
      5. 儲存密碼須 hash 處理
   5. LINE 帳號綁定管理
      1. 如果未綁定，則顯示未綁定，並顯示綁定按鈕
      2. 如果已綁定，則顯示已綁定，並顯示解除綁定按鈕
      3. 變更前需重新驗證身分
3. 註冊
   1. EMAIL 註冊
      1. 步驟
         1. 輸入 EMAIL & 密碼
         2. 發送 EMAIL 驗證信並提示
         3. 點擊驗證信內連結完成註冊
      2. 次數限制
         1. 同 IP 在 5 min 內請求上限為 5 次，若超過則封鎖該 IP 的請求 1 hr
      3. 未完成註冊帳號保留 24 hr，過期後自動清除，使用者需再次申請
      4. 如果 EMAIL 已被他人使用 (不論驗證完成與否)，提示接收 EMAIL 但不發送 EMAIL，也不建立帳號
   2. LINE 註冊
      1. 進 LINE OAuth 登入 (要確保安全)
      2. 如果已註冊過則進入登入流程
      3. 首次建立帳號時，將 LINE 名稱 寫入到 使用者資料，之後不因 LINE 名稱變更而自動更新站內名稱
4. 登入
   1. EMAIL & 密碼 登入 (網頁)
      1. 登入失敗次數限制
         1. 同一帳號 5 次失敗後暫時鎖定 15 分鐘
         2. 同 IP 5 次失敗後暫時鎖定 15 分鐘
   2. LINE & LINE LIFF 登入
      1. 如果未註冊過則進入註冊流程
5. 變更 EMAIL
   1. 不論驗證狀態，都顯示變更按鈕
   2. 如果輸入的 EMAIL 和他人重複，提示重複並拒絕變更
   3. 驗證完成前不覆蓋目前的 EMAIL 設定
   4. 輸入後提示接收 EMAIL 驗證信
6. EMAIL 驗證信
    1. token
       1. 限時 24 hr
       2. 限單次使用
       3. 重新發送時舊 token 失效
    2. 次數限制
       1. 冷卻時間：每 300 秒才能再寄一次
    3. 同帳號 1 小時上限 5 次，若超過則封鎖該 EMAIL 驗證請求 24 hr
    4. 同 IP 1 小時上限 5 次，若超過則封鎖該 IP 的 驗證請求 24 hr
    5. 未完成驗證 EMAIL 保留 24 hr，過期後自動清除，使用者需再次申請
    6. 驗證完成
       1. 顯示驗證完成
       2. 寄送通知給舊的已驗證 EMAIL
       3. 不做任何跳轉
    7. 當使用者完成 email 驗證並套用新的 email 後，必須使用新的email登入
7. 忘記密碼
   1. 未設置密碼也可使用忘記密碼
   2. 步驟
      1. 輸入 EMAIL
      2. 如果 EMAIL 已驗證，發送密碼變更信並提示接收 EMAIL
      3. 如果 EMAIL 不存在或未驗證，提示接收 EMAIL 但不發送 EMAIL
   3. 次數限制
      1. 冷卻時間：每 300 sec 才能再寄一次
      2. 同 EMAIL 在 1 hr 內請求上限為 5 次，若超過則封鎖該 EMAIL 的請求 24 hr
      3. 同 IP 在 1 hr 內請求上限為 5 次，若超過則封鎖該 IP 的請求 24 hr
8. 密碼變更信
    1. token
       1. 限時 1 hr
       2. 限單次使用
       3. 使用後無效
       4. 重新發送時舊 token 失效
9. 重設密碼頁面
    1. 設定密碼頁面是獨立頁面，僅能透過密碼變更信前往
       1. 必須驗證 token
       2. 同 IP 在 1 hr 內請求上限為 5 次，若超過則封鎖該 IP 的請求 24 hr
    2. 設定/更新完後
       1. 顯示變更完成
       2. 立即使所有既有 session 失效
       3. 寄送通知
       4. 不做任何跳轉
10. 帳號綁定 LINE
    1. 進 LINE OAuth 登入 (要確保安全)
    2. 如果已經綁定在其他帳號
       1. 提示義被其他帳號綁定，要求先解除對其他帳號的綁定
       2. 提供返回帳號設定頁連結
    3. 完成綁定程序
11. 帳號解除綁定 LINE
    1. 解除綁定前提示警告

## 開發建議

### 一、整體架構

- 前端：React + TypeScript + Vite
- 後端：Laravel 12 + MySQL
- 認證機制：
  - 使用 Laravel Sanctum（SPA cookie/session）
  - 不使用長效 API token 作為主登入方式
- 第三方登入：
  - 使用 Laravel Socialite 串接 LINE OAuth
- 非同步：
  - Laravel Queue 處理寄信、通知
- 快取/風控：
  - 建議使用 Redis（rate limit / session / queue）

### 二、資料庫設計（核心）

#### 1. users
- id
- public_id（對外顯示 ID，不可變更）
- name（nullable）
- password（nullable）
- primary_email_id
- last_reauth_at
- status（active / locked / pending）
- timestamps

#### 2. user_emails
- id
- user_id
- email（unique）
- is_verified
- verified_at
- is_primary
- reserved_until
- timestamps

#### 3. user_auth_providers
- id
- user_id
- provider（line）
- provider_user_id
- provider_name_snapshot
- access_token（optional）
- refresh_token（optional）
- timestamps

> unique(provider, provider_user_id)

#### 4. email_verification_requests
- id
- user_id（nullable）
- email
- purpose（register / change_email）
- token_hash
- expires_at
- used_at
- invalidated_at
- request_ip
- timestamps

#### 5. password_reset_requests
- id
- user_id
- email
- token_hash
- expires_at
- used_at
- invalidated_at
- request_ip
- timestamps

#### 6. security_reauth_logs
- id
- user_id
- method（password / line）
- passed_at
- expires_at
- request_ip
- user_agent
- timestamps

#### 7. auth_attempt_logs
- id
- type（login / register / forgot_password…）
- account_key
- ip
- is_success
- timestamps

### 三、後端模組拆分

建議不要集中在單一 Controller：

- Auth/
  - RegisterController
  - LoginController
  - LineAuthController
  - EmailVerificationController
  - ForgotPasswordController
  - ResetPasswordController

- Account/
  - ProfileController
  - EmailController
  - PasswordController
  - ProviderBindingController

- Security/
  - ReauthController

Service 層：
- UserRegistrationService
- EmailVerificationService
- PasswordResetService
- LineAuthService
- AccountBindingService
- ReauthenticationService
- RateLimitService

### 四、登入與認證策略

#### EMAIL 登入
- 僅允許 verified email
- 使用 Laravel Hash 驗證密碼
- 成功登入後 rotate session

#### LINE 登入
- OAuth redirect → callback
- 使用 Socialite 取得使用者資訊
- 依 provider_user_id 查找帳號
- 不存在則建立新帳號

#### SPA 認證流程
1. GET `/sanctum/csrf-cookie`
2. POST `/login`
3. 使用 cookie 維持登入態

### 五、關鍵流程建議

#### 1. EMAIL 註冊
- 建立 user + email（未驗證）
- 建立 verification request
- 寄驗證信
- 驗證成功後才啟用帳號

#### 2. EMAIL 驗證
- token 單次使用
- 重發使舊 token 失效
- 成功後：
  - email verified
  - 顯示成功（不跳轉）

#### 3. 變更 EMAIL
- 建立 change request（不直接覆蓋）
- 驗證成功才替換 primary email
- 寄通知給舊 email

#### 4. 密碼管理
- 僅 verified email 可設密碼
- 修改需驗證舊密碼
- hash 儲存
- 最低長度 12

#### 5. 忘記密碼
- 永遠回應「若資料正確會寄信」
- token 限時 + 單次使用
- 重設後：
  - 所有 session 失效
  - 寄通知

#### 6. LINE 綁定
- OAuth 驗證
- 若已綁其他帳號 → 拒絕
- 綁定前需 re-auth

#### 7. 解除綁定
- 檢查是否仍有其他登入方式
- 無則禁止
- 需 re-auth

### 六、敏感操作重新驗證（Re-auth）

適用情境：
- 修改 EMAIL
- 修改密碼
- 綁定/解除 LINE

方式：
- 有密碼 → 輸入密碼
- 無密碼 → LINE OAuth

實作：
- 設定 `reauth_valid_until`
- 有效時間建議 5~10 分鐘

### 七、Rate Limit 設計

建議使用 Laravel RateLimiter：

- login-by-ip
- login-by-account
- register-by-ip
- verify-email
- forgot-password

規則實作：
- IP + 帳號雙維度限制
- 超過後暫時封鎖
- 不回傳精確錯誤原因

### 八、安全建議

- Email 一律 lowercase
- 不回傳帳號是否存在（防枚舉）
- 密碼規則後端必驗
- OAuth 必須驗證 state
- 所有 token 使用 hash 儲存
- 驗證 token 必須：
  - 有效期限
  - 單次使用
  - 可失效

### 九、前端頁面規劃（React）

- /login
- /register
- /verify-email/result
- /forgot-password
- /reset-password
- /account/profile
- /account/security
- /account/email
- /account/providers
- /reauth

建議元件：
- ReauthDialog
- CooldownButton
- PasswordStrengthHint
- ProviderBindCard

### 十、API 規劃

#### Auth
- POST /api/auth/register/email
- POST /api/auth/login/email
- POST /api/auth/logout
- GET /api/auth/line/redirect
- GET /api/auth/line/callback
- GET /api/auth/me

#### Email
- POST /api/auth/email/resend
- GET /api/auth/email/verify

#### Password
- POST /api/auth/forgot-password
- POST /api/auth/reset-password
- PUT /api/account/password

#### Account
- GET /api/account/profile
- PUT /api/account/profile
- POST /api/account/email/change-request
- GET /api/account/email/change/verify
- POST /api/account/reauth

#### Provider
- POST /api/account/providers/line/bind
- DELETE /api/account/providers/line

### 十一、排程與清理

Laravel Scheduler：
- 清除未驗證帳號（24hr）
- 清除過期 token
- 清除 email reservation

### 十二、開發順序建議

1. 核心資料表（users / emails / providers）
2. EMAIL 註冊 + 驗證
3. 登入 / 登出
4. 忘記密碼 / 重設密碼
5. 帳號設定（email / password）
6. re-auth 機制
7. LINE 登入 / 綁定
8. rate limit / security log

### 十三、關鍵設計原則（重要）

1. 「帳號」與「登入方式」分離
2. 所有驗證流程使用 request table（不要直接改主資料）
3. 敏感操作必須 re-auth
4. 所有 token 都是：
   - 短效
   - 單次
   - 可失效
5. 錯誤訊息統一模糊化（防止帳號枚舉）

## Phase 分割建議

### Phase 0：專案初始化與骨架

**目標**

- 建立可開發的前後端骨架與環境

**範圍**

- React 專案初始化
- Laravel 12 專案初始化
- Sanctum 基本設定
- MySQL 連線
- Queue / Mail / Redis 基本設定
- CI / ENV / migration 流程建立
- API response 格式統一

**交付物**

- 可啟動前後端專案
- `/api/health` 或 `/api/me` 測試接口
- 開發 / staging 環境

### Phase 1：核心帳號模型
**目標**
- 建立所有帳號相關資料結構

**範圍**
- users
- user_emails
- user_auth_providers
- email_verification_requests
- password_reset_requests
- security_reauth_logs
- auth_attempt_logs
- public_id 規則
- email 正規化（lowercase）

**交付物**
- migrations
- models & relations
- seed / factory
- 基本 service skeleton

**完成條件**
- 可建立 user / email / provider 綁定
- DB constraint 正常運作

### Phase 2：EMAIL 註冊與驗證

**目標**
- 完成註冊流程

**範圍**

- EMAIL 註冊
- 建立未驗證帳號
- 發送驗證信
- 驗證 token
- resend 機制
- token 單次使用與失效

**前端**

- `/register`
- `/verify-email/result`

**完成條件**

- 可完成註冊
- email 不重複
- 驗證成功才啟用帳號

### Phase 3：登入 / 登出 / 取得使用者

**目標**

- 建立基本登入能力

**範圍**

- EMAIL 登入
- 登出
- `/me`
- session / csrf
- 登入失敗限制（基本版）

**前端**

- `/login`

**完成條件**

- verified 帳號可登入
- 未驗證不可登入
- session 正常維持

### Phase 4：忘記密碼 / 重設密碼

**目標**
- 完成帳號找回機制

**範圍**

- forgot password
- 發送 reset mail
- token 驗證
- 設定新密碼
- 失效所有 session
- 寄送通知

**前端**

- `/forgot-password`
- `/reset-password`

**完成條件**

- token 單次使用
- 重設後登入失效
- 成功流程完整

### Phase 5：帳號設定

**目標**

- 提供使用者自我管理功能

**範圍**

- 讀取資料
- 修改名稱
- EMAIL 變更申請
- EMAIL 驗證後替換
- 密碼設定 / 變更

**前端**

- `/account/profile`
- `/account/email`
- `/account/security`

**完成條件**

- 可修改 name
- email 可安全變更
- 密碼規則正確

### Phase 6：敏感操作重新驗證（Re-auth）

**目標**

- 提升帳號安全

**範圍**

- re-auth API
- password 驗證
- 短效驗證機制
- 敏感操作保護

**完成條件**

- 修改 email / password 前需 re-auth
- 有效期限機制正常

### Phase 7：LINE 登入 / 註冊

**目標**

- 導入 LINE 作為登入方式

**範圍**

- OAuth redirect / callback
- LINE 註冊
- LINE 登入
- 建立 session

**完成條件**

- 新用戶可用 LINE 註冊
- 舊用戶可用 LINE 登入

### Phase 8：LINE 綁定管理

**目標**
- 完成登入方式管理

**範圍**

- 綁定 LINE
- 綁定衝突處理
- 解除綁定
- 至少保留一種登入方式
- re-auth 驗證

**前端**

- `/account/providers`

**完成條件**

- 可綁定 LINE
- 已綁定不可重複
- 無其他登入方式不可解除

### Phase 9：安全與風控補強

**目標**

- 補齊所有安全規格

**範圍**

- rate limit（IP / 帳號 / EMAIL）
- auth attempt log
- token 管理
- scheduler 清理
- audit log

**完成條件**

- 所有入口具有限流
- 過期資料自動清理
- 可追蹤安全事件

### Phase 10：整合與優化

**目標**

- 完成產品可上線品質

**範圍**

- LINE LIFF 登入
- UX 優化（錯誤訊息 / loading / cooldown）
- email template
- 測試（E2E）
- 驗收整理

**完成條件**

- LINE 場景可直接登入
- 流程順暢
- 測試覆蓋完成
- 可上線

