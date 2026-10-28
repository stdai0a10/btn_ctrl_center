# 管理後台 key frames — 2026-10-28

直接以瀏覽器開啟 [index.html](index.html)。本目錄包含 **18 個 React 頁面來源、51 張 HTML key frames**，每張畫面均可獨立開啟。

## 範圍與讀法

- 包含管理登入、儀表板、使用者、房間、產品、設備、runtime、服務管理員與審計。
- 預設為 system_admin 權限，另有 service_manager 導覽與 runtime 只讀狀態。實際介面依權限決定。

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
| `resources/js/pages/Manage/Login.jsx` | `/manage/login` | [預設](manage-login.html)、[管理登入失敗](manage-login--error.html) |
| `resources/js/pages/Manage/Index.jsx` | `/manage` | [預設](manage-index.html)、[服務管理員權限](manage-index--service-manager.html)、[側欄收合](manage-index--collapsed.html) |
| `resources/js/pages/Manage/Users/Index.jsx` | `/manage/users` | [預設](manage-users-index.html)、[資料載入中](manage-users-index--loading.html)、[資料載入失敗](manage-users-index--load-error.html)、[篩選無結果](manage-users-index--empty.html) |
| `resources/js/pages/Manage/Users/Show.jsx` | `/manage/users/{user_public_id}` | [預設](manage-users-show.html) |
| `resources/js/pages/Manage/Rooms/Index.jsx` | `/manage/rooms` | [預設](manage-rooms-index.html)、[篩選無結果](manage-rooms-index--empty.html) |
| `resources/js/pages/Manage/Rooms/Show.jsx` | `/manage/rooms/{room_public_id}` | [預設](manage-rooms-show.html) |
| `resources/js/pages/Manage/Products/Index.jsx` | `/manage/products` | [預設](manage-products-index.html)、[篩選無結果](manage-products-index--empty.html) |
| `resources/js/pages/Manage/Products/Create.jsx` | `/manage/products/create` | [預設](manage-products-create.html) |
| `resources/js/pages/Manage/Products/Show.jsx` | `/manage/products/{product_public_id}` | [預設](manage-products-show.html)、[產品已鎖定](manage-products-show--locked.html)、[產品功能操作選單](manage-products-show--function-menu.html)、[修改功能說明](manage-products-show--edit-function.html)、[刪除產品功能](manage-products-show--delete-function.html)、[鎖定產品](manage-products-show--lock-confirm.html) |
| `resources/js/pages/Manage/Devices/Index.jsx` | `/manage/devices` | [預設](manage-devices-index.html)、[篩選無結果](manage-devices-index--empty.html) |
| `resources/js/pages/Manage/Devices/Create.jsx` | `/manage/devices/create` | [預設](manage-devices-create.html)、[產品型號建議清單](manage-devices-create--suggestions.html)、[產品搜尋無結果](manage-devices-create--no-product.html)、[建立設備驗證失敗](manage-devices-create--validation.html) |
| `resources/js/pages/Manage/Devices/Show.jsx` | `/manage/devices/{serial_number}` | [預設](manage-devices-show.html)、[設備未加入房間](manage-devices-show--unassigned.html) |
| `resources/js/pages/Manage/DeviceRuntime/Index.jsx` | `/manage/device-runtime` | [預設](manage-deviceruntime-index.html)、[Runtime 已停用](manage-deviceruntime-index--disabled.html)、[JWT 已撤銷](manage-deviceruntime-index--revoked.html)、[任務已取消](manage-deviceruntime-index--canceled.html)、[有查閱權限、無操作權限](manage-deviceruntime-index--readonly.html)、[停用設備 runtime](manage-deviceruntime-index--disable-confirm.html)、[撤銷設備 JWT](manage-deviceruntime-index--revoke-confirm.html)、[取消按鈕任務](manage-deviceruntime-index--cancel-confirm.html) |
| `resources/js/pages/Manage/ServiceManagers/Index.jsx` | `/manage/service-managers` | [預設](manage-servicemanagers-index.html)、[選取後批次操作](manage-servicemanagers-index--selected.html)、[使用者操作選單](manage-servicemanagers-index--row-menu.html)、[授權完成](manage-servicemanagers-index--granted.html)、[撤銷完成](manage-servicemanagers-index--revoked.html)、[篩選無結果](manage-servicemanagers-index--empty.html) |
| `resources/js/pages/Manage/ServiceManagers/Show.jsx` | `/manage/service-managers/{user_public_id}` | [預設](manage-servicemanagers-show.html)、[尚未授權／已撤銷](manage-servicemanagers-show--ungranted.html) |
| `resources/js/pages/Manage/Audit/Index.jsx` | `/manage/audit` | [預設](manage-audit-index.html) |
| `resources/js/pages/Manage/Audit/LoginFailures.jsx` | `/manage/audit/login-failures` | [預設](manage-audit-loginfailures.html)、[展開紀錄詳細](manage-audit-loginfailures--expanded.html) |
| `resources/js/pages/Manage/Audit/ManageActions.jsx` | `/manage/audit/manage-actions` | [預設](manage-audit-manageactions.html)、[展開紀錄詳細](manage-audit-manageactions--expanded.html) |
