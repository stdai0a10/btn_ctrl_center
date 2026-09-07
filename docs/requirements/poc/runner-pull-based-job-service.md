# Runner Pull-Based Job Service 架構規劃

## 目的

設計一個類似 GitLab Runner 的服務架構，使 Runner 可以主動向 Server 註冊、換取短效憑證、詢問工作、執行工作、回報進度與完成狀態。

此服務採用 **Runner 主動向 Server 輪詢** 的模式，而不是 Server 主動連線到 Runner。

---

## 核心設計原則

- Runner 必須先向 Server 註冊。
- Runner 註冊後取得一組長效 token。
- 長效 token 只可用來申請短效 token。
- Runner 必須使用短效 token 呼叫一般執行期 API。
- Runner 會定時向 Server 詢問是否有工作。
- Server 保存 Runner 狀態。
- Server 會準備工作給指定 Runner。
- Runner 詢問工作時，Server 從該 Runner 的工作佇列中取出最早建立的工作派送。
- 工作派送順序採 FIFO。
- Runner 執行工作途中會回報進度。
- Runner 完成工作時會回報完成結果。
- Runner 完成工作時不會同時請求新工作。
- Server 收到 progress 或 complete 回報時，也要更新 Runner 狀態。
- 工作派送與狀態更新需要避免重複派工與競態問題。

---

## 整體流程

```text
Runner                           Server
  |                                |
  | POST /api/runners/register     |
  |------------------------------->|
  | runner_id + long-lived token   |
  |<-------------------------------|
  |                                |
  | POST /api/runners/{id}/access-tokens
  | Authorization: Bearer long token
  |------------------------------->|
  | revoke old short token         |
  | create new short token         |
  |<-------------------------------|
  | short-lived access token       |
  |                                |
  | POST /api/runners/{id}/poll    |
  | Authorization: Bearer short token
  |------------------------------->|
  | update runner status           |
  | assign oldest queued job       |
  |<-------------------------------|
  | job payload / no job           |
  |                                |
  | POST /api/jobs/{id}/progress   |
  | Authorization: Bearer short token
  |------------------------------->|
  | save progress                  |
  | update runner status           |
  |<-------------------------------|
  | 200 OK                         |
  |                                |
  | POST /api/jobs/{id}/complete   |
  | Authorization: Bearer short token
  |------------------------------->|
  | save result                    |
  | update runner status = idle    |
  |<-------------------------------|
  | 200 OK                         |
```

---

## Token 設計

### Token 類型

| Token | 用途 | 有效期 | 可呼叫 API |
|---|---|---:|---|
| 長效 token | 換取短效 token | 長期 | `/api/runners/{id}/access-tokens` |
| 短效 token | Runner 執行期操作 | 短期 | `poll`、`progress`、`complete` |

---

## 長效 Token

Runner 註冊成功後，Server 回傳長效 token。

長效 token 只允許用來申請短效 token，不允許直接執行以下操作：

- 詢問工作
- 回報進度
- 回報完成
- 修改 Runner 執行狀態
- 存取 Job payload

### 安全建議

- 長效 token 只在註冊成功時顯示一次。
- Server 端只保存 token hash，不保存明文。
- 長效 token 外洩時，管理端應可以撤銷或重新產生。
- 長效 token 必須只透過 HTTPS 傳輸。

---

## 短效 Token

Runner 必須先用長效 token 申請短效 token，才能呼叫執行期 API。

短效 token 建議有效期：

```text
5 分鐘 ~ 30 分鐘
```

初期可設定為：

```text
15 分鐘
```

### 短效 Token 輪替規則

當 Runner 申請新的短效 token 時：

1. Server 驗證長效 token。
2. Server 鎖定 Runner 資料。
3. Server 作廢該 Runner 目前有效的短效 token。
4. Server 建立新的短效 token。
5. Server 更新 `runners.current_access_token_id`。
6. Server 回傳新的短效 token。

同一個 Runner 同一時間只允許一個有效短效 token。

---

## 建議 Token 格式

建議使用 opaque random token，不建議一開始使用純 JWT。

範例格式：

```text
rat_<token_id>.<random_secret>
```

例如：

```text
rat_01HXYZ123456abcdef.secret_random_value
```

其中：

| 部分 | 說明 |
|---|---|
| `rat_01HXYZ123456abcdef` | token id，用於快速查資料庫 |
| `secret_random_value` | 真正的 secret，只能用來 hash 驗證 |

Server 端保存：

```text
token_hash = hash(secret_random_value)
```

不要保存完整 token 明文。

---

## 為什麼不建議一開始使用 JWT

此系統有一個重要需求：

```text
申請新的短效 token 時，舊的短效 token 隨即作廢。
```

如果使用無狀態 JWT，舊 token 在過期前仍可能有效。

除非額外加入：

- jti blacklist
- token version
- server-side session record
- current token id 檢查

但如果已經需要 Server 端保存 token 狀態，使用 opaque token 會更直接且更容易撤銷。

---

## API 設計

## 1. 註冊 Runner

```http
POST /api/runners/register
```

### Request

```json
{
  "name": "runner-001",
  "hostname": "worker-host-1",
  "version": "1.0.0",
  "capabilities": ["shell", "docker"]
}
```

### Response

```json
{
  "runner_id": "runner_abc123",
  "runner_token": "long-lived-secret-token"
}
```

### Server 行為

- 建立 Runner。
- 產生長效 token。
- 保存長效 token hash。
- Runner 初始狀態可設為 `registered` 或 `idle`。
- 回傳 `runner_id` 與長效 token。

---

## 2. 申請短效 Token

```http
POST /api/runners/{runner_id}/access-tokens
Authorization: Bearer <long-lived-token>
```

### Response

```json
{
  "access_token": "short-lived-secret-token",
  "token_type": "Bearer",
  "expires_in": 900,
  "expires_at": "2026-06-23T12:30:00+08:00"
}
```

### Server 行為

- 驗證長效 token。
- 檢查 Runner 是否存在。
- 檢查 Runner 是否被停用。
- 作廢舊短效 token。
- 建立新短效 token。
- 更新 Runner 的 `current_access_token_id`。
- 更新 Runner 的 `last_seen_at`。

---

## 3. Runner 詢問工作

```http
POST /api/runners/{runner_id}/poll
Authorization: Bearer <short-lived-token>
```

### Request

```json
{
  "status": "idle",
  "current_job_id": null
}
```

### 沒有工作

```http
204 No Content
```

### 有工作

```http
200 OK
```

```json
{
  "job_id": "job_001",
  "type": "backup_database",
  "payload": {
    "database": "main",
    "target": "s3://backup-bucket/main.sql"
  }
}
```

### Server 行為

- 驗證短效 token。
- 更新 Runner 的 `last_seen_at`。
- 檢查 Runner 是否 disabled。
- 檢查 Runner 是否已經有執行中的工作。
- 從該 Runner 的 queued jobs 中取出最早建立的工作。
- 將 Job 狀態改為 `running`。
- 設定 `locked_by_runner_id`。
- 設定 `lease_expires_at`。
- 更新 Runner 狀態為 `running`。
- 回傳 Job payload。

---

## 4. Runner 回報進度

```http
POST /api/jobs/{job_id}/progress
Authorization: Bearer <short-lived-token>
```

### Request

```json
{
  "runner_id": "runner_abc123",
  "progress": 45,
  "message": "Processing",
  "status": "running"
}
```

### Response

```json
{
  "status": "ok"
}
```

### Server 行為

- 驗證短效 token。
- 檢查 token 是否屬於該 Runner。
- 檢查 Job 是否存在。
- 檢查 Job 是否由該 Runner 鎖定。
- 檢查 Job 是否尚未完成。
- 更新 Job progress。
- 更新 Job `last_progress_at`。
- 更新 Runner `last_seen_at`。
- 更新 Runner 狀態為 `running`。
- 延長或刷新 Job lease。

---

## 5. Runner 回報完成

```http
POST /api/jobs/{job_id}/complete
Authorization: Bearer <short-lived-token>
```

### Request

```json
{
  "runner_id": "runner_abc123",
  "status": "succeeded",
  "result": {
    "duration_seconds": 120
  }
}
```

失敗範例：

```json
{
  "runner_id": "runner_abc123",
  "status": "failed",
  "error_message": "Command exited with code 1"
}
```

### Response

```json
{
  "status": "ok"
}
```

### Server 行為

- 驗證短效 token。
- 檢查 token 是否屬於該 Runner。
- 檢查 Job 是否存在。
- 檢查 Job 是否由該 Runner 鎖定。
- 將 Job 狀態更新為 `succeeded` 或 `failed`。
- 寫入 Job result 或 error message。
- 設定 `finished_at`。
- 更新 Runner 狀態為 `idle`。
- 清除 Runner 的 `current_job_id`。
- 更新 Runner `last_seen_at`。

---

## Runner 狀態設計

| 狀態 | 說明 |
|---|---|
| `registered` | 已註冊，但尚未開始正常輪詢 |
| `idle` | 在線，沒有執行工作 |
| `running` | 正在執行工作 |
| `offline` | 太久沒有 poll、progress 或 complete |
| `disabled` | 被管理員停用 |
| `error` | Runner 自身異常 |

---

## Job 狀態設計

| 狀態 | 說明 |
|---|---|
| `queued` | 等待派送 |
| `running` | 已派送且執行中 |
| `succeeded` | 執行成功 |
| `failed` | 執行失敗 |
| `canceled` | 被取消 |
| `expired` | Runner 太久未回報，工作租約過期 |
| `requeued` | 已重新排隊 |

---

## 資料表建議

## runners

```text
runners
- id
- name
- hostname
- version
- capabilities
- long_token_hash
- long_token_created_at
- long_token_revoked_at
- current_access_token_id
- status
- current_job_id
- last_seen_at
- registered_at
- disabled_at
- created_at
- updated_at
```

### 欄位說明

| 欄位 | 說明 |
|---|---|
| `id` | Runner ID |
| `name` | Runner 名稱 |
| `hostname` | Runner 所在主機名稱 |
| `version` | Runner client 版本 |
| `capabilities` | Runner 能力，例如 shell、docker |
| `long_token_hash` | 長效 token hash |
| `long_token_created_at` | 長效 token 建立時間 |
| `long_token_revoked_at` | 長效 token 撤銷時間 |
| `current_access_token_id` | 目前有效的短效 token ID |
| `status` | Runner 狀態 |
| `current_job_id` | 目前執行中的 Job |
| `last_seen_at` | 最近一次與 Server 互動時間 |
| `registered_at` | 註冊時間 |
| `disabled_at` | 停用時間 |

---

## runner_access_tokens

```text
runner_access_tokens
- id
- runner_id
- token_hash
- issued_at
- expires_at
- revoked_at
- last_used_at
- created_at
- updated_at
```

### 欄位說明

| 欄位 | 說明 |
|---|---|
| `id` | 短效 token ID |
| `runner_id` | 所屬 Runner |
| `token_hash` | 短效 token secret hash |
| `issued_at` | 發行時間 |
| `expires_at` | 過期時間 |
| `revoked_at` | 作廢時間 |
| `last_used_at` | 最近使用時間 |

---

## jobs

```text
jobs
- id
- runner_id
- status
- type
- payload
- progress
- result
- error_message
- created_at
- started_at
- finished_at
- last_progress_at
- locked_by_runner_id
- lease_expires_at
- updated_at
```

### 欄位說明

| 欄位 | 說明 |
|---|---|
| `id` | Job ID |
| `runner_id` | 指定執行此 Job 的 Runner |
| `status` | Job 狀態 |
| `type` | Job 類型 |
| `payload` | Job 執行參數 |
| `progress` | 執行進度 |
| `result` | 執行結果 |
| `error_message` | 錯誤訊息 |
| `started_at` | 開始時間 |
| `finished_at` | 完成時間 |
| `last_progress_at` | 最近一次進度回報時間 |
| `locked_by_runner_id` | 實際取得此 Job 的 Runner |
| `lease_expires_at` | Job 租約過期時間 |

---

## FIFO 派工設計

Server 派工時，應從指定 Runner 的工作中選出最早建立的 queued job。

概念 SQL：

```sql
BEGIN;

SELECT *
FROM jobs
WHERE runner_id = :runner_id
  AND status = 'queued'
ORDER BY created_at ASC, id ASC
LIMIT 1
FOR UPDATE;

UPDATE jobs
SET
  status = 'running',
  started_at = NOW(),
  locked_by_runner_id = :runner_id,
  lease_expires_at = NOW() + INTERVAL '10 minutes'
WHERE id = :job_id;

UPDATE runners
SET
  status = 'running',
  current_job_id = :job_id,
  last_seen_at = NOW()
WHERE id = :runner_id;

COMMIT;
```

### 重點

- 派送 Job 時必須使用 transaction。
- 選到的 Job 必須立即從 `queued` 改成 `running`。
- 必須記錄 `locked_by_runner_id`。
- 多個 Server instance 同時派工時，不能讓同一個 Job 被派送兩次。
- FIFO 排序建議使用 `created_at ASC, id ASC`。

---

## 短效 Token 發行邏輯

概念 SQL：

```sql
BEGIN;

SELECT *
FROM runners
WHERE id = :runner_id
FOR UPDATE;

UPDATE runner_access_tokens
SET revoked_at = NOW()
WHERE runner_id = :runner_id
  AND revoked_at IS NULL;

INSERT INTO runner_access_tokens (
  runner_id,
  token_hash,
  issued_at,
  expires_at,
  revoked_at
)
VALUES (
  :runner_id,
  :new_token_hash,
  NOW(),
  NOW() + INTERVAL '15 minutes',
  NULL
);

UPDATE runners
SET
  current_access_token_id = :new_access_token_id,
  last_seen_at = NOW()
WHERE id = :runner_id;

COMMIT;
```

---

## 短效 Token 驗證邏輯

```pseudo
function authenticate_access_token(token):
    token_id, secret = parse(token)

    access_token = find_access_token_by_id(token_id)

    if access_token is null:
        reject 401

    if access_token.revoked_at is not null:
        reject 401

    if access_token.expires_at <= now:
        reject 401

    if !hash_verify(secret, access_token.token_hash):
        reject 401

    runner = find_runner(access_token.runner_id)

    if runner.disabled_at is not null:
        reject 403

    if runner.current_access_token_id != access_token.id:
        reject 401

    update access_token.last_used_at = now
    update runner.last_seen_at = now

    return runner
```

---

## Runner Client 行為範例

```pseudo
runner_id, long_token = load_runner_credentials()

access_token = request_access_token(runner_id, long_token)

while true:
    if access_token will expire soon:
        access_token = request_access_token(runner_id, long_token)

    job = poll_server(runner_id, access_token)

    if job is null:
        sleep(5)
        continue

    try:
        report_progress(job.id, access_token, 0, "started")

        execute_job(job, on_progress = function(percent, message) {
            if access_token will expire soon:
                access_token = request_access_token(runner_id, long_token)

            report_progress(job.id, access_token, percent, message)
        })

        if access_token will expire soon:
            access_token = request_access_token(runner_id, long_token)

        report_complete(job.id, access_token, "succeeded")

    catch error:
        if access_token will expire soon:
            access_token = request_access_token(runner_id, long_token)

        report_complete(job.id, access_token, "failed", error.message)

    // complete 不順便取得下一個工作
    // 下一輪 while 才會再次 poll
```

---

## Runner 回報與狀態更新規則

| API | 使用 Token | Runner 狀態更新 |
| --- | --- | --- |
| `register` | 無或註冊憑證 | `registered` |
| `issue access token` | 長效 token | 更新 `last_seen_at` |
| `poll` | 短效 token | `idle` 或 `running`，更新 `last_seen_at` |
| `progress` | 短效 token | `running`，更新 `current_job_id` 與 `last_seen_at` |
| `complete` | 短效 token | `idle`，清空 `current_job_id`，更新 `last_seen_at` |

---

## Job Lease / Timeout

Runner 取得 Job 後，可能因為斷線、當機或網路異常而無法回報完成。

因此 Job 應該有租約機制：

```text
lease_expires_at
```

### 建議規則

- Runner poll 成功取得工作時，Server 設定 `lease_expires_at`。
- Runner progress 時，Server 可以延長 `lease_expires_at`。
- 如果超過 `lease_expires_at` 仍沒有回報，Server 可將 Job 標記為 `expired` 或重新排隊。
- 如果 Runner 太久沒有任何互動，Server 可將 Runner 標記為 `offline`。

### 範例

```text
progress / heartbeat interval: 30 秒
job lease duration: 10 分鐘
runner offline timeout: 5 分鐘
```

實際數值需依 Job 平均執行時間調整。

---

## Idempotency 設計

以下 API 建議支援重複呼叫：

- `progress`
- `complete`

### 為什麼需要

可能發生：

```text
Runner 送出 complete
Server 已經保存成功
但 response 在網路中斷掉
Runner 以為失敗
Runner 重新送出 complete
```

因此 Server 應允許同一 Runner 對同一 Job 重複回報完成。

### 建議規則

- 同一 Runner 對同一 Job 重複回報相同完成結果時，回傳 `200 OK`。
- 如果 Job 已經由其他 Runner 完成，拒絕。
- 如果 Job 已經是 `succeeded`，再次收到相同 `succeeded` 可直接回 `200 OK`。
- 如果 Job 已經是 `failed`，再次收到相同 `failed` 可直接回 `200 OK`。
- 如果完成狀態衝突，例如已成功後又回報失敗，應回傳 `409 Conflict`。

---

## 競態問題與建議處理

## 1. 重複派工

### 問題

多個 Server instance 同時處理同一 Runner poll，可能導致同一個 Job 被派送多次。

### 建議

- 派工時使用 transaction。
- 選 Job 時使用 row lock。
- 派送前檢查 Job 狀態仍為 `queued`。
- 更新為 `running` 後才回傳 Job payload。

---

## 2. Token 換新與 Progress 同時發生

### 問題

Runner 有可能一邊送 progress，一邊申請新短效 token。

流程可能變成：

```text
Thread A 使用舊 token 送 progress
Thread B 申請新 token
Server 作廢舊 token
Thread A 的 progress 到達 Server
Server 拒絕舊 token
```

### 建議

Runner client 應集中管理 token refresh：

- 同一時間只允許一個 refresh 動作。
- refresh 完成後，所有後續 request 都使用新 token。
- 如果 request 收到 `401 Unauthorized`，可以 refresh token 後重試一次。
- 避免多個 thread 各自保存 token。

---

## 3. Complete 回應遺失

### 問題

Server 已成功保存 complete，但 response 遺失。

### 建議

- `complete` API 需要 idempotent。
- Runner 對同一 Job 可以安全重試 complete。
- Server 不應因為重複 complete 造成狀態錯誤。

---

## 4. Runner 死亡造成 Job 卡住

### 問題

Runner 取得 Job 後當機，Job 永遠維持 `running`。

### 建議

- 使用 `lease_expires_at`。
- 定期掃描過期 Job。
- 過期後可標記為 `expired` 或重新排隊。
- 同時將 Runner 標記為 `offline`。

---

## 安全建議

- 所有 API 必須使用 HTTPS。
- token 不應保存明文。
- 長效 token 只顯示一次。
- 短效 token 有效期要短。
- 短效 token 要能立即撤銷。
- Runner 被停用後，所有 token 應失效。
- 回報 progress / complete 時，必須檢查 Job 是否真的屬於該 Runner。
- Job payload 中避免放入長期敏感資料。
- 如果 payload 需要敏感資料，應考慮加密或使用短期憑證。
- 管理端應提供 revoke runner、disable runner、rotate long token 功能。

---

## 管理端建議功能

- 查看 Runner 清單。
- 查看 Runner 狀態。
- 查看 Runner 最近上線時間。
- 停用 Runner。
- 重新產生 Runner 長效 token。
- 撤銷 Runner 所有短效 token。
- 查看指定 Runner 的 Job queue。
- 建立指定 Runner 的 Job。
- 取消 queued Job。
- 取消 running Job。
- 查看 Job 執行結果。
- 查看 Job 進度與歷史紀錄。

---

## 初期實作建議

第一階段建議先實作：

1. Runner 註冊。
2. 長效 token 換短效 token。
3. 短效 token 驗證。
4. 指定 Runner 的 Job queue。
5. FIFO poll 派工。
6. progress 回報。
7. complete 回報。
8. Runner 狀態更新。
9. Job lease timeout。
10. 基本管理端查詢。

暫時不建議一開始就實作 long polling。

---

## 後續可擴充項目

- Long polling。
- Runner capabilities matching。
- Job tags。
- Job priority。
- Retry policy。
- Job cancellation。
- Artifact 上傳。
- Log streaming。
- 多 Runner pool。
- Runner group。
- Runner auto-scaling。
- Job dependency。
- Job timeout policy。
- Webhook notification。
- Audit log。
- Metrics / Prometheus exporter。

---

## 總結

此服務可以整理為以下架構：

```text
Runner registration
+ long-lived token
+ short-lived access token
+ token rotation
+ runner polling
+ server-side FIFO queue
+ job lease
+ progress reporting
+ completion reporting
+ runner status tracking
```

最重要的設計重點：

1. Runner 主動向 Server 輪詢工作。
2. 長效 token 只用於申請短效 token。
3. 短效 token 用於 poll、progress、complete。
4. 申請新短效 token 時，舊短效 token 立即失效。
5. Server 派工必須使用 transaction 與 lock。
6. Job running 後必須有 lease timeout。
7. progress 與 complete API 必須能安全重試。
8. complete 不應同時取得下一個工作。
9. Server 每次收到 poll、progress、complete 都要更新 Runner 狀態。
