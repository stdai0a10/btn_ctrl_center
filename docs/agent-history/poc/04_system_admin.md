# Codex

## step 1

依照文件 `.data\Story-System-Admin.md` 裡的規格、建議，進行「管理功能」的擴充開發:

- 此專案後端使用 Laravel 12、前端使用 Inertia + React
- 部分資料須參考 `.data\Story-Service-Manager.md` 和已存在的程式碼
- 依照 `Phase 分割建議` 進行階段性開發，每完成一個階段就進行一次提交
  - 提交開頭要用 `Phase N:`

## step 2

你把原本好的改壞了，請修正

- 錯誤：管理後台登入狀態失效時，使用者應被導回 `/manage/login`。

## step 3.1

1. 服務管理員列表的操作欄位值改成使用 dropdown menu
2. 服務管理員詳細資料裡，把使用者資料的授予/撤除按鈕高度調整到和 `span.status-pill` 一樣

完成後不要進行 commit

## step 3.2

1. 服務管理員列表的操作欄位值改成 '3 dots dropdown menu'
2. 服務管理員列表的批次操作按鈕高度調整到和旁邊的 `span.status-pill` 一樣

完成後不要進行 commit

## step 3.3

1. 按下服務管理員列表的操作欄位的按鈕後，彈出的菜單被包在 `div.table-wrap` 的可視範圍裡，對使用者產生操作上的困難，必須要滾動 `div.table-wrap` 才能看到菜單。請改良

完成後不要進行 commit
