# 架構規畫：模組化單體 → 微服務

## 現狀（P0 — 已完成）

單一 Symfony 8 應用跑在 FrankenPHP 上，搭配 PostgreSQL 18、Redis 8、Jaeger：

```
                    ┌──────────────┐
   HTTP :8088 ────► │  FrankenPHP  │────► PostgreSQL 18
                    │ (Symfony 8)  │────► Redis 8（cache）
                    └──────┬───────┘
                           │ dispatch（Messenger / Redis transport）
                    ┌──────▼───────┐
                    │    worker    │  messenger:consume
                    └──────────────┘
        所有服務 ──OTLP──► Jaeger（鏈路追蹤）
```

- HTTP 同步流量與非同步訊息消費（worker）已是**分開的容器**，
  各自有獨立的 `OTEL_SERVICE_NAME`（`symfony-app` / `symfony-worker`），
  在 Jaeger 中可以分別觀察。
- 所有跨程序通訊只走兩種通道：HTTP 與 Messenger 訊息。這是未來拆分的關鍵前提。

## 模組化規範（P1 — 邊界即未來的服務邊界）

`src/` 依 **bounded context** 分模組，模組內依層分目錄：

```
src/
├── Shared/            # 跨模組共用：契約、值物件、健康檢查、logging 等橫切關注點
├── EventManage/       # 活動管理（第一個真實業務模組）
│   ├── Domain/        #   Event 實體＋狀態機、EventRepository 介面、EventStatus
│   ├── Application/   #   EventService（use case）、Message/（發佈的整合事件 = pub/sub 契約）
│   ├── Infrastructure/#   DoctrineEventRepository（PG schema: event_manage）
│   └── Presentation/  #   EventController（REST /api/events）、CreateEventRequest DTO
├── FlowEngine/        # 流程引擎：FlowDefinition（不可變定義）+ FlowInstance（訊息驅動逐步執行）
│   ├── Domain/        #   兩個 entity、狀態機、repository 介面
│   ├── Application/   #   FlowService、ExecuteNextStepHandler（step 級冪等）、StepExecutor 介面
│   ├── Infrastructure/#   Doctrine repositories、內建 executors（log/set/fail，tagged services 可擴充）
│   └── Presentation/  #   /api/flows、/api/flow-instances
├── Orchestration/     # Temporal 整合（durable workflow）：訂閱 EventPublished 排到點提醒
│   ├── Application/   #   ReminderScheduler port + 訂閱 handler（不依賴 Temporal SDK）
│   └── Infrastructure/#   Temporal adapter、workflow/activity 定義（跑在 temporal-worker/RoadRunner）
├── Notification/      # 通知（EventManage 與 FlowEngine 整合事件的訂閱者，只依賴 Message 契約）
│   └── Application/MessageHandler/
├── Demo/              # 範例模組（示範規範，可刪除）
└── <NewContext>/      # 新模組照此格局：Domain / Application / Infrastructure / Presentation
```

**規則（由 `deptrac.yaml` 強制，違反會在 CI 失敗）：**

1. 依賴方向只能往內：`Presentation / Infrastructure → Application → Domain`。
2. 模組之間**不得直接呼叫對方的類別**；只能透過
   （a）Messenger 訊息（非同步為預設），或（b）`Shared/` 內定義的介面契約。
3. 每個模組使用**獨立的 PostgreSQL schema**（Doctrine entity 加
   `#[ORM\Table(schema: 'demo')]`），禁止跨 schema JOIN —— 拆庫時才不會痛。
4. 模組對外的訊息類別視為 **API 契約**：欄位只加不改，變更走版本化。
5. **時間一律以 UTC 儲存**：DB 欄位為 `TIMESTAMP WITHOUT TIME ZONE`，實體在建構時
   負責把輸入正規化成 UTC；PHP 的 `date.timezone` 必須是 UTC（php.ini 已固定），
   任何新的執行環境（cron、獨立 worker）都要維持此設定，否則讀回時間會偏移。

## 演進路線

### P1 品質關卡（工具已就位）
- `composer stan`（PHPStan level 6）、`composer cs`（PHP-CS-Fixer）、
  `composer deptrac`（模組邊界檢查）— 接入 CI 後作為合併門檻。
- 補 PHPUnit + 每模組的功能測試；對訊息契約加序列化快照測試。

### P2 事件驅動深化
- 寫入側導入 **transactional outbox**（Messenger 的 doctrine transport 作 outbox，
  再轉發到 Redis/佇列），保證「DB 寫入」與「事件發佈」的原子性。
- 讀寫分離需求出現時，用事件投影建 read model（仍在同一顆 PG，不同 schema）。
- Redis transport 若吞吐或投遞語義不夠，平移到 RabbitMQ/Kafka——
  Messenger 抽象層不變，程式碼零修改。

### P3 拆分 playbook（strangler 模式，逐模組執行）
1. **先拆 worker**：某模組的 handler 搬到獨立部署的 consumer（compose/K8s 各一份），
   本倉庫已示範 app/worker 分離，拆分只是換個 image。
2. **資料先行**：該模組的 schema 遷到獨立資料庫（前面禁止跨 schema JOIN 的紅利）。
3. **HTTP 面**：FrankenPHP/Caddy 作反向代理，按路徑把該模組的路由
   導到新服務（strangler facade），舊單體逐步縮小。
4. **契約測試**：拆分前後用同一套訊息/HTTP 契約測試保證行為一致。
5. **觀測性**：OTel 的 `traceparent` 已透過 `OTEL_PROPAGATORS` 跨服務傳遞，
   拆分後在 Jaeger 直接看到跨服務的完整鏈路，無須額外工作。

## FrankenPHP 部署形態

| 環境 | 模式 | 說明 |
|---|---|---|
| 開發（現在） | classic | 每請求重建 kernel，改code即生效；程式碼用 volume 掛載 |
| 生產 | classic（暫時） | 程式碼 COPY 進 image、`--no-dev` + cache 預熱、關閉 `opcache.validate_timestamps`。worker mode 需 `runtime/frankenphp-symfony`，該套件尚未支援 Symfony 8——支援後開啟 `Caddyfile.prod` 內的註解即可 |

生產 image 建議 multi-stage：`composer install --no-dev` + `APP_ENV=prod` 預熱 cache，
和本 repo 的開發 Dockerfile 分開維護。
