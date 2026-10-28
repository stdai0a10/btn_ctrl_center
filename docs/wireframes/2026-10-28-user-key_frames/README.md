# 一般用戶 key frames — 2026-10-28

直接以瀏覽器開啟 [index.html](index.html)。本目錄包含 **15 個 React 頁面來源、87 張 HTML key frames**，每張畫面均可獨立開啟。

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
| `resources/js/pages/Home.jsx` | `/` | [預設](home.html) |
| `resources/js/pages/Auth/Login.jsx` | `/login` | [預設](auth-login.html)、[登入錯誤](auth-login--error.html)、[登入中](auth-login--processing.html)、[LINE 外部流程](auth-login--line-redirect.html) |
| `resources/js/pages/Auth/Register.jsx` | `/register` | [預設](auth-register.html)、[驗證信寄出](auth-register--sent.html)、[欄位驗證失敗](auth-register--validation.html) |
| `resources/js/pages/Auth/ForgotPassword.jsx` | `/forgot-password` | [預設](auth-forgotpassword.html)、[寄送結果](auth-forgotpassword--sent.html) |
| `resources/js/pages/Auth/ResetPassword.jsx` | `/reset-password?token=…` | [預設](auth-resetpassword.html)、[檢查連結中](auth-resetpassword--loading.html)、[連結失效](auth-resetpassword--invalid.html)、[變更成功](auth-resetpassword--success.html) |
| `resources/js/pages/Auth/VerifyEmailResult.jsx` | `/verify-email/result?token=…` | [驗證成功](auth-verifyemailresult.html)、[驗證中](auth-verifyemailresult--loading.html)、[驗證失敗](auth-verifyemailresult--invalid.html) |
| `resources/js/pages/Account/Profile.jsx` | `/account/profile` | [預設](account-profile.html)、[更新名字](account-profile--editing.html) |
| `resources/js/pages/Account/Email.jsx` | `/account/email` | [預設](account-email.html)、[新 EMAIL 待驗證](account-email--pending.html)、[敏感操作需重新驗證](account-email--reauth-required.html) |
| `resources/js/pages/Account/Security.jsx` | `/account/security` | [預設](account-security.html)、[首次設定密碼](account-security--no-password.html) |
| `resources/js/pages/Account/Providers.jsx` | `/account/providers` | [已綁定](account-providers.html)、[未綁定](account-providers--unbound.html)、[解除綁定確認](account-providers--unbind-confirm.html) |
| `resources/js/pages/Account/Reauth.jsx` | `/reauth?back=…` | [預設](account-reauth.html)、[驗證失敗](account-reauth--error.html) |
| `resources/js/pages/Rooms/Index.jsx` | `/rooms` | [預設](rooms-index.html)、[尚無房間](rooms-index--empty.html)、[資料載入中](rooms-index--loading.html)、[資料載入失敗](rooms-index--load-error.html) |
| `resources/js/pages/Rooms/Show.jsx` | `/rooms/{room}` | [房主／成員](rooms-show.html)、[住戶](rooms-show--resident.html)、[加入申請分頁](rooms-show--requests.html)、[申請已忽略](rooms-show--request-ignored.html)、[邀請分頁](rooms-show--invitations.html)、[多位房主](rooms-show--promoted.html)、[設備操作選單](rooms-show--device-menu.html)、[住戶設備選單](rooms-show--resident-device-menu.html)、[設備改名](rooms-show--rename-device.html)、[設備已停用](rooms-show--device-disabled.html)、[設備已上鎖](rooms-show--device-locked.html)、[非成員](rooms-show--restricted.html)、[加入申請審核中](rooms-show--join-pending.html)、[加入申請已取消](rooms-show--join-cancelled.html)、[加入設備](rooms-show--add-device.html)、[設備加入成功](rooms-show--add-success.html)、[設備加入失敗](rooms-show--add-error.html)、[確認移除設備](rooms-show--remove-device.html)、[確認移除成員](rooms-show--remove-member.html)、[設備資訊](rooms-show--device-info.html)、[退出房間](rooms-show--leave-confirm.html)、[刪除房間](rooms-show--delete-confirm.html)、[沒有設備](rooms-show--devices-empty.html) |
| `resources/js/pages/Rooms/Invitations.jsx` | `/room-invitations` | [預設](rooms-invitations.html)、[沒有邀請與申請](rooms-invitations--empty.html)、[忽略後可恢復](rooms-invitations--ignored.html) |
| `resources/js/pages/Buttons/Index.jsx` | `/buttons` | [預設](buttons-index.html)、[沒有分頁](buttons-index--empty.html)、[分頁沒有按鈕](buttons-index--empty-buttons.html)、[編輯分頁](buttons-index--editing.html)、[按鈕操作選單](buttons-index--button-menu.html)、[按鈕不可操作](buttons-index--unavailable.html)、[切換分頁](buttons-index--other-page.html)、[分頁選單](buttons-index--page-menu.html)、[選擇分頁](buttons-index--page-picker.html)、[新增分頁](buttons-index--create-page.html)、[加入按鈕](buttons-index--add-button.html)、[修改按鈕](buttons-index--edit-button.html)、[按鈕資訊](buttons-index--button-info.html)、[排序分頁](buttons-index--order-pages.html)、[分頁順序調整](buttons-index--order-moved.html)、[刪除分頁](buttons-index--delete-page.html)、[刪除按鈕](buttons-index--delete-button.html)、[任務：等待設備取得任務](buttons-index--job-queued.html)、[任務：設備執行中](buttons-index--job-running.html)、[任務：執行成功](buttons-index--job-succeeded.html)、[任務：執行失敗](buttons-index--job-failed.html)、[任務：設備已離線](buttons-index--job-device_offline.html)、[任務：任務已逾時](buttons-index--job-timed_out.html)、[任務：無權限](buttons-index--job-unauthorized.html)、[任務：已取消](buttons-index--job-canceled.html)、[任務：前端等待逾時](buttons-index--job-frontend-timeout.html)、[資料載入中](buttons-index--loading.html)、[資料載入失敗](buttons-index--load-error.html) |
