# BTN_CTRL_CENTER

一套整合網頁控制、多人空間管理與實體裝置執行的 IoT 控制中心。

## 目的

* 以智慧遠端電子鎖作為 POC，驗證可擴充的 IoT 遠端控制架構。
* 訓練自己構想、建立專案
* 練習使用 AI 進行專案開發
* 練習使用 Cloudflare 的各種功能

## 專案特點

* 完整帳號系統：包含 Email 註冊驗證、登入、忘記密碼、敏感操作重新驗證，以及 LINE Login 與帳號綁定。
* 房間與裝置操作：提供房主/住戶角色、成員邀請、加入申請，以及裝置歸屬、轉移、啟停與防轉移鎖定管理。
* 可自訂的控制介面：支援多個按鈕分頁、排序、版面，以及按鈕圖示、形狀、顏色、標籤與目標裝置設定。
* 完整的按鈕到裝置執行流程：按下按鈕後建立工作，裝置輪詢領取、回報進度與成功／失敗結果，清楚顯示工作狀況給用戶。
* 權限化管理後台：可管理使用者、房間、裝置、產品功能與管理員，採細粒度 RBAC，並保留登入失敗與管理操作稽核紀錄。
* 裝置安全與執行可靠性：長效、短效 JWT 分工，並具備冪等請求、單一執行工作、心跳、租約逾時、離線判斷與定時清理機制。
* 分離的 API 文件：一般使用者、實體裝置與管理後台各自擁有獨立的 OpenAPI／Swagger 文件及存取控制。

## 已完成項目

* 電鎖控制電路設計、製作
* 帳號系統
* 用戶系統
  * 帳號管理
  * 房間管理
  * 自訂控制介面
* 中控系統
  * 用戶、房間管理
  * 產品與裝置管理與紀錄
  * 管理後台、角色權限與稽核
* 使用 LINE 作為第三方登入
* 自動化 OpenAPI 文件生成
* 服務部署
* 完成概念驗證 ([poc](https://github.com/stdai0a10/btn_ctrl_center/tree/poc))

## 網站截圖

[網站畫面截圖](./screenshots)

## 關聯專案

* [BTN_CTRL_CENTER](https://stdai0a10.github.io/btn_ctrl_center/) ([Github](https://github.com/stdai0a10/btn_ctrl_center)) (當前專案)  
  一套整合網頁控制、多人空間管理與實體裝置執行的 IoT 控制中心
* [BTN_CTRL_OPS](https://stdai0a10.github.io/btn_ctrl_ops/) ([Github](https://github.com/stdai0a10/btn_ctrl_ops))  
  以 Docker Compose 整合核心專案、Cloudflare Tunnel 與 Traefik，打造易於部署與管理的容器化服務架構
* [RPI_BUTTON_TOUCH](https://stdai0a10.github.io/rpi_button_touch/) ([Github](https://github.com/stdai0a10/rpi_button_touch))  
  Raspberry Pi 裝置端控制服務，整合遠端任務排程與 GPIO 硬體控制，負責接收遠端任務並透過 GPIO 驅動電子鎖
