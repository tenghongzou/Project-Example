# Symfony 8 開發環境（FrankenPHP）

Symfony 8 + FrankenPHP (PHP 8.4) + PostgreSQL 18 + Redis 8 + Jaeger（OpenTelemetry 鏈路追蹤）
的 Docker 開發環境，採**模組化單體**架構，為未來拆分微服務鋪路 —— 詳見 [ARCHITECTURE.md](ARCHITECTURE.md)。

## 服務一覽

| 服務 | 位址 | 說明 |
|---|---|---|
| 應用程式 | http://localhost:8088 | FrankenPHP（Caddy 內嵌 PHP 8.4） |
| Messenger worker | — | 消費 `async` transport（Redis）的獨立容器 |
| Jaeger UI | http://localhost:16686 | 鏈路追蹤查詢介面 |
| Temporal UI | http://localhost:8233 | 工作流引擎查詢介面（server gRPC 在 7233） |
| Temporal worker | — | RoadRunner 宿主的 PHP workflow/activity worker |
| PostgreSQL 18 | localhost:5432 | 帳密/DB：`app` / `app` / `app` |
| Redis 8 | localhost:6379 | Symfony cache + Messenger transport |

## 常用指令

```bash
# 啟動 / 停止
docker compose up -d
docker compose down

# 進入 PHP 容器
docker compose exec app sh

# Symfony console / Composer
docker compose exec app bin/console about
docker compose exec app composer require <package>

# Doctrine
docker compose exec app bin/console doctrine:migrations:migrate

# 只跑測試
docker compose exec app composer test

# 品質關卡（PHPStan + CS 檢查 + 模組邊界檢查 + PHPUnit）
docker compose exec app composer qa

# 看 worker 消費紀錄
docker compose logs -f worker
```

## 驗證環境

1. `GET http://localhost:8088/health` — 查 PostgreSQL、寫 Redis 快取
2. `GET http://localhost:8088/ping` — 派送 `PingMessage` 到 Redis，由 worker 非同步消費
3. 開 http://localhost:16686 ，service 選 `symfony-app` / `symfony-worker` 看 trace

## EventManage API（活動管理 + pub/sub）

| Method | Path | 說明 |
|---|---|---|
| POST | `/api/events` | 建立活動（draft），發佈 `EventCreated` |
| GET | `/api/events` | 活動列表 |
| GET | `/api/events/{id}` | 單筆（404 if missing） |
| POST | `/api/events/{id}/publish` | draft → published，發佈 `EventPublished`（非法轉換 409） |
| POST | `/api/events/{id}/cancel` | → cancelled，發佈 `EventCancelled` |

## FlowEngine API（流程引擎）

| Method | Path | 說明 |
|---|---|---|
| POST | `/api/flows` | 建立流程定義（步驟 type 會 fail-fast 驗證，最多 50 步） |
| GET | `/api/flows` / `/api/flows/{id}` | 定義列表 / 單筆 |
| POST | `/api/flows/{id}/instances` | 啟動實例，**202 Accepted**（worker 逐步非同步執行） |
| GET | `/api/flow-instances/{id}` | 實例狀態（status / current_step_index / context / error） |

執行模型：每個步驟一則 `ExecuteNextStep` 訊息（帶 stepIndex 作 step 級冪等 key），
worker 執行後推進並派送下一步；完成/失敗發佈 `FlowInstanceCompleted` / `FlowInstanceFailed`。
內建步驟 type：`log`、`set`（寫 context）、`fail`（測試用）；
實作 `StepExecutor` 介面即自動註冊新 type（tagged service）。

範例：
```bash
curl -X POST localhost:8088/api/flows -H 'Content-Type: application/json' \
  -d '{"name":"Demo","steps":[{"type":"set","params":{"key":"a","value":1}},{"type":"log","params":{}}]}'
curl -X POST localhost:8088/api/flows/<id>/instances -H 'Content-Type: application/json' -d '{"context":{}}'
curl localhost:8088/api/flow-instances/<instance_id>
```

## Temporal（durable workflow）

`Orchestration` 模組示範 Temporal 整合：活動 publish 後（`EventPublished` 事件）
自動排一個 **durable timer** 提醒工作流，睡到活動開始時間執行提醒 activity——
timer 由 Temporal 持久化，worker 重啟/部署都不會遺失，這是自建 FlowEngine 做不到的。

- Application 層只依賴 `ReminderScheduler` port；Temporal SDK 隔離在 Infrastructure
- workflow id 用 `event-reminder-{eventId}` 去重，事件重複投遞天然冪等
- 驗證：publish 一個 `scheduledAt` 在近未來的活動 → Temporal UI（localhost:8233）
  看到 workflow 與 timer → 到點後 `docker compose logs temporal-worker` 出現提醒 log
- dev 用 `temporalio/auto-setup`（schema 建在共用 PG 的 `temporal` 資料庫）；
  正式環境建議獨立部署 Temporal cluster 或用 Temporal Cloud

整合事件經 Redis 送出，`Notification` 模組的 handler 訂閱消費（見 worker log，
log 內 `trace_id` 可在 Jaeger 對照完整鏈路）。首次啟動記得跑 migration：
`docker compose exec app bin/console doctrine:migrations:migrate -n`；
跑功能測試前先建測試庫：`doctrine:database:create --env=test` + `migrations:migrate --env=test`。

## 鏈路追蹤

PHP 映像內建 `opentelemetry` 擴充，透過套件自動 instrument（設定都在
`docker-compose.yml` 的 `OTEL_*` 環境變數，程式碼零侵入）：

- `opentelemetry-auto-symfony` — HTTP 請求 / console 指令的 root span
- `opentelemetry-auto-pdo` — 所有 SQL 查詢

Trace 以 OTLP (http/protobuf) 送到 Jaeger 4318 埠；`traceparent` 會跨服務傳遞，
未來拆微服務後鏈路自動串起來。

## UAT / Prod 映像

CI 會自動打包 production-grade 映像（`docker/frankenphp/Dockerfile` 的 `prod` target：
code 打包進 image、`composer --no-dev`、cache 預熱、opcache 關閉時間戳檢查）推到 GHCR：

| 觸發 | 映像標籤 |
|---|---|
| push 到 `main` | `ghcr.io/<repo>:uat`、`uat-<sha>` |
| 打 `v*` tag（如 `v1.0.0`） | `ghcr.io/<repo>:prod`、`1.0.0`、`latest` |

UAT 與 prod 是**同一個 artifact**，環境差異（DB 位址、OTel endpoint 等）
一律用執行期環境變數注入，不重新建置。發佈 prod 版本：

```bash
git tag v1.0.0 && git push origin v1.0.0
```

## 目錄結構

```
├── app/                    # Symfony 8 專案（src/ 依模組劃分，見 ARCHITECTURE.md）
├── docker/
│   └── frankenphp/         # FrankenPHP 映像 + Caddyfile + php.ini
├── docker-compose.yml
└── ARCHITECTURE.md         # 模組規範與微服務演進路線圖
```
