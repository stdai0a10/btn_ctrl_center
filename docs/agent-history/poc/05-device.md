# Story-Device-Codex

## step 0

請分析專案的程式碼，並和下列開發規劃文件內容進行比對，找出現在程式情況和原本規劃的差異，以 markdown 的格式輸出到 `.data/POC-1.md`

1. .data/Story-Auth.md
2. .data/Story-Room.md
3. .data/Story-Service-Manager.md
4. .data/Story-System-Admin.md

## step 1.1

幫我擬一個關於設備的開發文件，並儲存成 .data/Plan-Device.md。附上一些需求:

- 服務管理員可以透過服務後台看到資料庫裡所有 devices
  - 服務管理員可以看到 room 有哪些 devices
  - 服務管理員可以看到 device 在哪間 room 或是沒被加入
- 系統管理員才可以透過服務後台新增 device 到資料庫
  - 需要輸入序號和隱碼
  - 剛手動新增的 device 不屬於任何 room，也不存在持有主
- 客戶新增設備到房間時，要檢查是否存在資料庫
- 客戶可以看到他加入的 room 有哪些 devices

## step 1.2

依照 `.data\Plan-Device.md` 的內容製作簡易版規劃，並儲存在`.data\Plan-Device-Simple.md`

## step 1.3

在這裡告訴我規劃裡關於device相關開發的摘要

## step 1.4

告訴我你建議的phase分割的摘要

## step 1.5

關於「補強角色權限、敏感資料、重複序號、移轉及 audit 測試」，告訴我你規劃的具體修正方式

## step 1.6

如果我把 .data/Plan-Device.md 交給新的AI SESSION開發，能確保符合預期嗎？

## step 1.7

關於你提出的這些模糊點，你有什麼建議？

## step 1.8

我接受你對模糊點的建議，現在把這些建議加入  .data/Plan-Device.md

## step 2

現在請依照文件 .data\Plan-Device.md 進行開發

## step 3.1

幫我為後台的每個詳細資料頁的右上角增加返回清單的按鈕

## step 3.2

幫我修改客戶介面：客戶為設備的重新命名部分，幫我把操作流程改成和帳號資料裡的修改顯示名稱一樣

## step 3.3

幫我修改一般用戶的介面：房間設備的四個按鈕(改名、啟用停用、上鎖解鎖、移除)

1. 改成使用彈出菜單，按出菜單才能選擇這四項功能
2. 用戶按下移除以外的按鈕時不要刷新整個頁面

## step 3.4

幫我修改一般用戶的房間設備介面

1. 加入設備改成先只顯示按鈕，按下後再彈出輸入介面，輸入完畢按下確認後彈出成功或是失敗資訊
2. 用戶按下移除按鈕後要經過再次確認才可移除
