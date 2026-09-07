# Plan - Product

## 產品

products

- 產品 ID: unique
- 產品型號: r/w
- 產品名稱: r/w

product_functions

- 功能 ID: unique
- 產品 ID: foreign
- 代碼: unique random str
- 功能說明: r/w

## 設備

devices

- 設備 ID
- 產品 ID <- 新增
- 設備 SN
- ...
