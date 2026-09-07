# POC-1：程式現況與開發規劃差異分析

分析日期：2026-06-18

比對文件：

- `docs/requirements/poc/01-auth.md`
- `docs/requirements/poc/02-room.md`
- `docs/requirements/poc/03-service_manager.md`
- `docs/requirements/poc/04-system_admin.md`

分析範圍包含 migrations、models、services、controllers、middleware、routes、React/Inertia 頁面、console commands 與 feature tests。

## 驗證結果

- `php artisan test`：46 tests、390 assertions，全數通過。
- `npm run build`：成功。

測試通過代表目前測試所描述的行為一致，不代表所有規劃項目都已完成；下列差異多數是尚未被現有測試涵蓋的規格落差。

## 整體結論

|領域|目前狀態|結論|
|---|---|---|
|帳號註冊登入|大部分完成，但安全規格仍有明顯缺口|主要流程可運作；限流週期、無密碼帳號 re-auth、驗證信重寄目的與風控紀錄未完全符合規劃|
|房間設備管理|核心後端與主要 UI 已完成|缺少設備啟用狀態、使用者發起加入申請的前端入口，穩定化測試覆蓋也不足|
|服務管理後台|接近規劃完成|登入、RBAC、使用者與房間查詢均已實作；稽核範圍與安全測試仍可補強|
|系統管理員|主要 Phase 已完成|CLI、服務管理員管理、audit 頁面/API 均存在；CLI no-op 稽核與 system actor 支援仍不完整|

## 一、帳號註冊登入

### 已符合規劃的主要項目

- Laravel 12、React、Inertia、Sanctum session/cookie 架構已建立。
- 帳號與登入方式分離：
  - `users`
  - `user_emails`
  - `user_auth_providers`
  - verification/reset/reauth/attempt log tables
- Email 正規化、唯一 Email、公開 ID、nullable password、密碼 hash 已實作。
- Email 註冊、驗證、登入、登出、`/me`、忘記密碼、重設密碼均可運作。
- 驗證與重設 token 使用 hash、有效期限、單次使用及舊 token 失效機制。
- 重設密碼後會刪除該使用者的 database sessions，並寄送通知。
- 帳號資料、名稱、Email 變更、密碼設定/變更與 LINE 綁定頁面均已建立。
- LINE OAuth 登入、LINE 首次註冊、既有 LINE 身分登入及 LIFF access token 登入已實作。
- LINE provider 的全域唯一限制、解除最後登入方式的防護已實作。
- 排程每小時執行 `auth:cleanup-expired`，清除過期驗證資料及未完成註冊帳號。

### 與規劃不一致或未完成

#### 高優先

1. 無密碼帳號無法完成敏感操作的重新驗證。

   規劃要求：有密碼時使用密碼 re-auth；未設定密碼時，應透過既有登入方式完成 re-auth。

   現況：

   - `ReauthenticationService` 只支援 `passWithPassword()`。
   - `/api/account/reauth` 強制要求 `password`。
   - 前端 `/reauth` 也只有密碼欄位。

   影響：只有 LINE 登入方式的使用者無法正常進行 Email 變更、密碼設定、LINE 綁定管理等敏感操作。

2. Email 驗證信重寄會把所有未驗證 Email 當成「註冊驗證」。

   `EmailVerificationService::resend()` 找到未驗證 Email 後，固定呼叫：

   ```php
   createRequest($user, $email, 'register', $ip)
   ```

   若該 Email 實際上是 `change_email` 申請，重寄後 token 會走註冊驗證流程，而不是 Email 變更流程。這可能造成舊 primary Email 未正確取消、同一使用者出現多個 primary Email 等資料不一致。

3. Auth rate limit 的時間規則與文件不同。

   |流程|規劃|目前實作|
   |---|---|---|
   |Email 註冊|同 IP 5 分鐘 5 次，超過封鎖 1 小時|同 IP 1 小時最多 5 次，沒有分離「觀察窗」與「封鎖期」|
   |驗證信重寄|同 Email/IP 每小時 5 次，超過封鎖 24 小時|同 Email/IP 24 小時最多 5 次|
   |忘記密碼|同 Email/IP 每小時 5 次，超過封鎖 24 小時|同 Email/IP 24 小時最多 5 次|
   |重設密碼頁|同 IP 每小時 5 次，超過封鎖 24 小時|沒有此 IP 限制|

   現有 `RateLimitService` 只有單一 Laravel RateLimiter decay window，無法表達「一小時內超量後封鎖 24 小時」的兩階段規則。

4. 風控與安全事件紀錄不完整。

   - `auth_attempt_logs` 目前只有 Email/password login 會寫入。
   - register、verify email、forgot password、reset password、LINE login、reauth 等入口未寫入。
   - 現有欄位也沒有 failure reason、locked until、user-agent 等可供完整稽核的資訊。

#### 中優先

5. 弱密碼檢查只有提示，沒有實際阻擋。

   規劃寫明前端禁止常見弱密碼；目前前端僅顯示「請避免常見弱密碼」，沒有弱密碼字典或檢查邏輯。後端只檢查最少 12 碼。

6. Email 變更的重複提示未依驗證狀態區分。

   規劃要求已驗證 Email 與保留中的未驗證 Email 使用不同提示；目前統一回覆「已被其他帳號使用或仍處於保留有效期間內」。

7. 寄信仍是同步執行。

   規劃建議透過 Laravel Queue 寄送 Email/通知；目前直接使用 `Mail::raw()`，沒有 Mail Job、Mailable queue 或 notification queue。

8. 預設基礎設施設定與規劃不同。

   規劃建議 MySQL + Redis；目前 `.env.example` 預設為 SQLite、database cache、database queue、database session，mail 預設寫 log。這適合本機 POC，但尚非規劃中的 staging/production 架構。

9. LINE OAuth 與綁定流程的端點級測試不足。

   目前主要測試 `LineAuthService` 與 LIFF；沒有完整覆蓋 OAuth redirect/callback、state 失效、綁定 callback、無密碼 LINE re-auth 等流程。

### Phase 判定

|Phase|判定|說明|
|---|---|---|
|0 專案初始化|大致完成|環境可啟動，但預設不是 MySQL/Redis，寄信未 queue|
|1 核心帳號模型|完成|資料表、模型、關聯、factory 均存在|
|2 Email 註冊與驗證|部分完成|主流程完成；重寄 purpose 有缺陷，限流不符|
|3 登入/登出/me|完成|含 session regenerate 與基本登入限流|
|4 忘記/重設密碼|部分完成|主流程完成；重設頁 IP 限流及精確封鎖規則缺少|
|5 帳號設定|大致完成|Email、password、profile 可操作|
|6 Re-auth|部分完成|只有 password，缺 LINE re-auth|
|7 LINE 登入/註冊|大致完成|OAuth/LIFF 已實作，端點安全測試不足|
|8 LINE 綁定管理|部分完成|綁定與解除存在，但無密碼使用者無法 re-auth|
|9 安全與風控|未完整|限流規則與安全紀錄未補齊|
|10 整合與優化|部分完成|LIFF 與 UI 已有，缺 E2E、queued mail、完整安全驗收|

## 二、房間設備管理

### 已符合規劃的主要項目

- `rooms` 使用不可變的 ULID public ID、unique index 與 soft delete。
- 建立房間時會在同一 transaction 將建立者設為 owner。
- `room_user` 有 `(room_id, user_id)` unique constraint，角色為 room scope。
- 邀請與加入申請支援 pending、accepted、ignored、cancelled。
- 邀請與加入申請不會自動過期。
- 房主升降角色、移除成員、自行退出與最後房主保護已實作。
- 最後一位且唯一成員的房主退出時會刪除房間並解除設備。
- 設備序號唯一、隱碼只存 hash 且不會由 API 回傳。
- 設備加入、命名、鎖定、解鎖、移除及跨房間移轉已實作。
- 鎖定設備不能移除或移轉；移轉使用 `lockForUpdate()` 並記錄 transfer log。
- Policy、Service、transaction 與 API error code 架構已建立。
- 房間列表、房間詳細、邀請/申請、成員與設備管理 UI 已建立。

### 與規劃不一致或未完成

#### 高優先

1. 缺少「設備啟用狀態」。

   規格明確要求房主可以管理設備的啟用狀態，但目前：

   - `devices` 沒有 `is_enabled` 或等價欄位。
   - 沒有啟用/停用 API。
   - 沒有前端操作。
   - 沒有相關權限與測試。

2. 使用者端缺少發起加入房間申請的操作入口。

   後端已有 `POST /api/rooms/{room}/join-requests`，但現有 React 頁面只會顯示已送出/收到的申請，沒有輸入房間 public ID、搜尋房間或送出申請的 UI。

#### 中優先

3. 測試數量不足以覆蓋文件列出的穩定化條件。

   現有房間領域只有 4 個整合測試，尚未獨立驗證：

   - 一般住戶不得邀請、升降角色、移除成員或管理設備。
   - owner 不可透過移除成員 API 移除自己。
   - 多 owner 情境下可合法降級或退出。
   - 邀請/申請 ignore、cancel、重複 pending 與越權操作。
   - 設備移轉後，原房間成員不可再操作。
   - 直接刪除房間時，包含鎖定設備在內全部解除。
   - race condition 或重複接受邀請/申請。

4. 房主不變條件只由 Service 保護，資料庫本身不保證。

   `room_user.role` 沒有 DB check constraint，資料庫也無法阻止直接 SQL 將最後 owner 改成 resident。應用程式正常路徑有 transaction 與 lock 保護，但 DB 層仍可產生非法狀態。

5. 裝置移轉前端缺少規劃中的風險提示。

   設備加入表單沒有提示「可能觸發移轉、上鎖設備不可移轉、移轉會清除原設定」。後端規則正確，但使用者在送出前無法確認影響。

6. 刪除房間與最後房主退出的邏輯重複。

   `RoomService::delete()` 與 `RoomMemberService::leave()` 各自處理設備解除、邀請取消及申請取消。兩份邏輯日後容易產生行為分歧，較符合原規劃的做法是讓最後房主退出共用 `RoomService` 的刪除流程。

### Phase 判定

|Phase|判定|說明|
|---|---|---|
|1 核心資料與房間管理|完成|資料表、model、API、policy 已存在|
|2 成員加入流程|後端完成、前端部分完成|缺少使用者送出加入申請入口|
|3 設備管理|部分完成|缺設備啟用狀態|
|4 前端管理介面|大致完成|主要頁面存在，但加入申請與移轉提示不足|
|5 穩定化|部分完成|有 API 文件與測試，但安全邊界和競態測試不足|

## 三、服務管理後台

### 已符合規劃的主要項目

- `service_manager` 與 `system_admin` 使用一般 user 帳號及 Spatie RBAC。
- 後台只能使用 Email/password 登入，並檢查 active、password 與 `manage.access`。
- 管理登入成功後 regenerate session，記錄：
  - `manage_authenticated_at`
  - `manage_authenticated_user_id`
- `EnsureManageAuthenticated` 正確檢查使用者、session user ID 及固定 30 分鐘期限，不因操作延長。
- 管理後台登出只清除管理登入狀態，不登出前台帳號。
- 後台登入有 account/IP limit，成功與失敗均寫入 `manage_login_logs`。
- dashboard、使用者列表/詳細、房間列表/詳細 API 與頁面均已建立。
- 搜尋、狀態篩選、分頁及細部 permissions 已實作。
- 管理 API 未回傳 password、token、LINE access token 等敏感欄位。
- 使用者詳細頁會顯示權限與加入房間；房間詳細頁會顯示成員與 room role。

### 與規劃不一致或未完成

1. 管理操作紀錄只涵蓋部分讀取行為。

   `LogManageAction` 目前只記錄：

   - `users.detail.view`
   - `rooms.detail.view`
   - `audit.login_failures.view`
   - `audit.manage_actions.view`

   dashboard、使用者列表、房間列表、服務管理員列表/詳細等讀取行為不會由 middleware 記錄。若「管理後台操作紀錄」預期涵蓋所有重要後台存取，現況仍不完整。

2. 後台登入限流與登入紀錄缺少專門的行為測試。

   程式已有 account/IP 5 次、15 分鐘鎖定與 login log，但現有測試沒有驗證：

   - 第 5/6 次失敗的實際鎖定邊界。
   - account 與 IP 是否獨立。
   - `locked_until` 是否正確。
   - 成功登入是否清除兩個 limiter。
   - 無管理權限但帳密正確時的模糊錯誤與紀錄。

3. 「最近登入管理後台時間」在 dashboard 顯示的是目前管理驗證時間。

   `DashboardController` 回傳 session 的 `manage_authenticated_at`，不是從 `manage_login_logs` 查詢最近一次成功登入。若規劃中的「最近登入」包含歷史登入，現況語意不同。

4. 使用者狀態模型沒有完整支援文件中的「刪除」。

   users 目前沒有 soft delete；後台 UI 雖有 deleted label，但使用者資料模型實際只以字串 status 表示，沒有正式刪除流程或 deleted_at。

### Phase 判定

|Phase|判定|說明|
|---|---|---|
|1 RBAC 與後台登入|完成|權限、獨立登入狀態、30 分鐘期限均已實作|
|2 後台首頁|完成|統計、快速入口、管理員資訊均存在|
|3 使用者列表/詳細|完成|搜尋、分頁、房間關聯均存在|
|4 房間列表/詳細|完成|搜尋、分頁、成員關聯均存在|
|5 稽核紀錄|部分完成|login/action logs 已有，但記錄範圍與安全測試仍不足|

## 四、系統管理員

### 已符合規劃的主要項目

- `system_admin` role 與文件列出的 13 個 permissions 已建立。
- `service_manager` 與 `system_admin` 可以同時存在於同一 user。
- 後台判斷使用 permission，不直接依賴 role 名稱。
- CLI 已實作：
  - `system-admin:list`
  - `system-admin:add {user_public_id}`
  - `system-admin:remove {user_public_id}`
- CLI 可建立第一位及移除最後一位 system admin。
- Web 後台沒有授予或撤銷 `system_admin` 的 API。
- service manager 單筆/批次查詢、授予、撤銷 API 與頁面已完成。
- service manager grant/revoke 為 idempotent，no-op 會記錄 metadata。
- 撤銷 `service_manager` 不影響 `system_admin`。
- `manage_action_logs` 已補強為 actor/target 分離，支援 CLI actor。
- audit API 與頁面支援文件要求的查詢、篩選、分頁及展開詳細資料。
- middleware 與細部 permissions 已套用到新增頁面及 API。
- 已有 system admin、service manager、audit、敏感資料與 CLI 整合測試。

### 與規劃不一致或未完成

#### 高優先

1. system admin CLI 的 no-op 不會寫入 `manage_action_logs`。

   - 對已是 system admin 的使用者執行 `system-admin:add`，只顯示 warning。
   - 對不是 system admin 的使用者執行 `system-admin:remove`，只顯示 warning。

   文件要求操作可追蹤，Phase 3/9 也強調 no-op 行為。現況 service manager no-op 有記錄，但 system admin CLI no-op 沒有，稽核規則不一致。

2. `actor_type = system` 只有資料欄位概念，沒有實際 logger 入口。

   `ManageActionLogger` 只提供 `forManageUser()` 與 `forCli()`；規劃列出的 `system` actor 尚無 `forSystem()` 或實際使用案例。

#### 中優先

3. 批次授予/撤銷不是整批 transaction。

   controller 逐筆呼叫各自的 transaction。若批次中後段遇到 inactive user 或其他錯誤，前面已完成的角色異動不會回滾，API 可能回傳失敗但資料已部分變更。文件未明訂必須 all-or-nothing，但這與「批次操作」常見預期有差異，應明確決定語意。

4. CLI 角色異動沒有 transaction 或 row lock。

   `system-admin:add/remove` 直接檢查後 assign/remove role；併發 CLI 執行時可能重複判定。雖然 pivot unique constraint 可降低重複資料風險，但 no-op 判斷與 audit log 仍可能不一致。

5. system admin 加入時間依賴 audit log 推算。

   `system-admin:list` 使用最近一筆 `system_admin.grant` 的 `created_at`。若角色由既有資料、手動 DB 或舊版本建立而沒有 log，會顯示 `-`，並非真正的角色加入時間。

6. 前端角色操作按鈕未依 grant/revoke permission 個別隱藏。

   頁面本身只要求 view/detail permission，畫面會顯示授予與撤銷控制；API 仍會正確拒絕沒有 grant/revoke permission 的使用者，因此不是後端越權，但 UI 沒有完全依細部 permission 調整。

7. 整合測試尚未覆蓋部分文件條件。

   尚缺：

   - Web 後台不存在任何 system admin 修改入口的明確 route test。
   - system admin CLI no-op audit。
   - 批次操作部分失敗時的資料一致性。
   - `actor_type = system`。
   - 所有新增頁面與 API 的 permission 組合矩陣。

### Phase 判定

|Phase|判定|說明|
|---|---|---|
|1 RBAC 基礎|完成|角色、permission 與多角色已驗證|
|2 system admin CLI|大致完成|三個指令可用；CLI no-op 未記錄|
|3 action log 補強|部分完成|actor/target、CLI、before/after 已有；system actor 未實作|
|4 service manager API|完成|單筆、批次、no-op、權限與 audit 均存在|
|5 service manager 頁面|完成|列表、詳細、搜尋、篩選、批次操作均存在|
|6 audit API|完成|兩類 audit API 與查詢條件均存在|
|7 audit 頁面|完成|首頁、列表、篩選、分頁、展開詳細均存在|
|8 middleware 整合|完成|共同與細部 permissions 已套用|
|9 整合與安全測試|大致完成|已有完整基礎測試，仍缺上述邊界條件|

## 建議修正順序

1. 修正 Email 重寄時錯用 `register` purpose 的資料一致性問題。
2. 為無密碼 LINE 帳號加入 LINE OAuth re-auth。
3. 依文件重做 Auth 的「觀察窗 + 封鎖期」rate limit，並補重設密碼頁 IP 限制。
4. 增加設備啟用狀態的 migration、API、Policy、UI 與測試。
5. 補上使用者發起房間加入申請的前端入口。
6. 擴充 auth/manage audit log 與限流測試。
7. 讓 system admin CLI no-op 也記錄 audit，並補 `system` actor logger。
8. 明確定義批次角色異動是 all-or-nothing 或允許部分成功，再依決策調整 API response 與 transaction。
