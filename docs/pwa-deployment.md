# PWA 部署與驗收

## 部署條件與標頭

正式環境必須使用可信任憑證的 HTTPS。localhost 的安全環境例外僅供本機開發，不能作為實機驗收。設定實際 APP_URL、代理轉送的 HTTPS scheme 與可信任代理；Email 登入連結及 LINE callback 必須使用實際部署的 HTTPS 網域並符合供應商設定。

Nginx 的 document root 為 `public`。根目錄 `/sw.js` 維持根 scope；`/manifest.webmanifest`、`/offline.html`、`/sw.js`、`/icons/` 使用 `Cache-Control: no-cache, must-revalidate`，允許儲存但每次使用前須重新驗證。MIME 分別為 `application/manifest+json`、`text/html`、`application/javascript`、`image/png`（SVG 草稿為 `image/svg+xml`）。保留 ETag/Last-Modified，未修改時可回 304。

若有前置反向代理或 CDN，必須對以上 URL 套用等價規則、取消強制長 TTL 或 stale serving，部署後清除舊快取並檢查最外層回應。不可對已登入 HTML、API 或管理後台設定公共快取。Service Worker 不儲存個人資料，不提供背景重送；管理後台請求直通網路。

發布完整 Vite build 及 public PWA 資產，避免發布途中混用版本。修改預先快取內容時同步更新 worker 的快取版本。更新版本與回滾版本都要驗證 worker 及離線頁；勿依賴強制重新整理取代正常更新測試。

## 本機程式檢查

- `node --test tests/pwa/*.test.mjs`
- `npm run build`
- 在測試用 APP_KEY 及 SQLite/必要 PHP extension 可用的環境執行 `php artisan test`。
- `git diff --check`
- 用暫時 Nginx container 掛載 `public` 至 `/var/www/app/public`、`nginx.d/default.conf` 至 `/etc/nginx/conf.d/default.conf`，只公開 localhost 測試埠。執行 `nginx -t`，執行 `node tests/pwa/nginx-headers.mjs http://127.0.0.1:18083` 逐一 GET manifest、worker、offline、所有 icons，檢查 200、上述 MIME、Cache-Control、ETag，帶 If-None-Match 再請求應回 304。此項不驗證 Laravel/FPM 或登入。

## 使用者回報的安裝檢查（2026-10-29）

| 平台 | 瀏覽器 | 安裝提示 | 完成安裝 |
| --- | --- | --- | --- |
| PC | Chrome | 有 | 未回報 |
| PC | Edge | 有 | 未回報 |
| PC | Firefox | 有 | 未回報 |
| Android 16 | Samsung 瀏覽器 | 有 | 未回報 |
| Android 16 | Firefox | 有 | 未回報 |
| Android 16 | Chrome | 有 | 成功 |
| Android 16 | Edge | 有 | 未回報 |

以上為使用者回報，回報日期為 2026-10-29。部署 URL、瀏覽器版本、提示類型（瀏覽器安裝提示或網站內安裝說明）及由安裝圖示啟動的結果尚未記錄。其餘登入、離線與更新情境仍依下表驗收。

## 安裝與實際 HTTPS 驗收程序

測試前記錄：部署 URL、commit、部署時間、最外層代理/CDN 規則與實際標頭、裝置/OS/瀏覽器版本、Email 與 LINE 測試帳號。下表依目前回報更新；未回報的測項維持待驗收，後續逐格填入結果、證據及缺陷連結。最終門檻是原訂桌面 Chrome/Edge、Android Chrome、iOS Safari 各項驗收通過。

桌面 Chrome/Edge 使用網址列或瀏覽器選單的安裝功能，兩個瀏覽器分別測試；Android Chrome 使用「安裝應用程式／加到主畫面」；iOS Safari 使用分享選單「加入主畫面」。每次由新安裝圖示啟動後應進入 `/buttons`，確認 standalone 顯示與正確圖示。

| 項目 | 桌面 Chrome | 桌面 Edge | Android Chrome | iOS Safari |
| --- | --- | --- | --- | --- |
| 裝置/OS/瀏覽器/HTTPS URL/版本記錄 | PC；其餘未提供 | PC；其餘未提供 | Android 16；其餘未提供 | 未實測 |
| 出現安裝提示 | 有（使用者回報） | 有（使用者回報） | 有（使用者回報） | 未實測 |
| 完成安裝／加入主畫面 | 未回報 | 未回報 | 成功（使用者回報） | 未實測 |
| 由圖示啟動 `/buttons`、standalone 顯示與正確圖示 | 未實測 | 未實測 | 未實測 | 未實測 |
| Email 登入連結完成後回到 PWA，登入態正確 | 未實測 | 未實測 | 未實測 | 未實測 |
| LINE 登入 callback 回到 PWA，登入態正確 | 未實測 | 未實測 | 未實測 | 未實測 |
| 已登入與未登入深連結、登入後返回原頁 | 未實測 | 未實測 | 未實測 | 未實測 |
| 線上首次載入後斷網，新開頁僅顯示靜態離線頁 | 未實測 | 未實測 | 未實測 | 未實測 |
| 已開頁斷線保留資料但顯示過期提示、停用修改 | 未實測 | 未實測 | 未實測 | 未實測 |
| POST 送出後遺失回應，重開仍可查證且不重送 | 未實測 | 未實測 | 未實測 | 未實測 |
| 離線登出明確提示無法完成，恢復連線可登出 | 未實測 | 未實測 | 未實測 | 未實測 |
| A 登出後切換 B，不顯示 A 資料／待查證操作 | 未實測 | 未實測 | 未實測 | 未實測 |
| 新版部署後正常關閉重開取得新 worker／資產 | 未實測 | 未實測 | 未實測 | 未實測 |

POST 結果不明測試應讓伺服器收到請求後才中斷回應，記錄 request_id；重開後先查證、不得自動重送。只在送出前斷網不能證明此情境。Email 與 LINE 均須從已安裝 PWA 開始測試，記錄外部瀏覽器/應用程式跳轉與返回行為。帳號切換需包含另一個分頁及重新啟動後觀察。

更新測試先安裝版本 A，部署版本 B，保持正常瀏覽與關閉重開，確認 worker 控制權、靜態離線頁版本與新 Vite 資產；記錄 DevTools 的 worker URL、版本及網路回應標頭。若更新失敗，先比對 CDN 與 origin，檢查快取規則與舊 worker，修正後重新跑所有相關項目。

目前狀態：本機程式檢查與暫時 Nginx HTTP 檢查已通過；使用者已回報上述安裝提示及 Android 16 Chrome 安裝成功。實際部署 URL、反向代理/CDN 設定及其餘平台情境仍待補充驗收。
