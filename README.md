# Symfony 8 開發環境（FrankenPHP）

Symfony 8 + FrankenPHP (PHP 8.4) + PostgreSQL 18 + Redis 8 + Jaeger（OpenTelemetry 鏈路追蹤）
的 Docker 開發環境，採**模組化單體**架構，為未來拆分微服務鋪路 —— 詳見 [ARCHITECTURE.md](ARCHITECTURE.md)。

## 服務一覽

| 服務 | 位址 | 說明 |
|---|---|---|
| 應用程式 | http://localhost:8088 | FrankenPHP（Caddy 內嵌 PHP 8.4） |
| Messenger worker | — | 消費 `async` transport（Redis）的獨立容器 |
| Jaeger UI | http://localhost:16686 | 鏈路追蹤查詢介面 |
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
