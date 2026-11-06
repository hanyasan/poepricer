# ТЗ: сбор метрик колебаний цен (exchange)

Источник: `pricing.md`. Здесь зафиксированы принятые решения. Сигналы (превышение порога) не анализируются, собираются только сырые метрики.

## 1. Область

- Только свои данные: `price_checks` → `price_check_cores` → `price_check_core_types` + `exchange_item_prices`. `compact_*` не используются.
- Jobs и расписание не создаются. Холодный старт делает автор.
- Данные хранятся без очистки.
- Метрики считаются по каждой лиге и каждой валюте метрики отдельно.

## 2. Валюты метрик

- PoE1: divine и chaos. PoE2: divine и exalted.
- Связка «лига + валюта» хранится в `exchange_metrics_items`:
  - `poe_league_id`, `exchange_item_id` (предмет-валюта лиги), `min_price` (порог стоимости в этой валюте, `>= min_price`), unique (`poe_league_id`, `exchange_item_id`).
- Все собранные метрики привязаны к `exchange_metrics_item_id`.
- Цена предмета в валюте C = `exchange_item_prices.rate` × `price_check_core_types.rate` валюты C из того же core (`exchange_item_trade_id` = `trade_id` валюты). Основная валюта учёта — та, у которой rate = 1. Если в core нет курса валюты C, цена этого core пропускается.
- Сама валюта метрики в корзину и метрики предметов не входит.

## 3. Корзины времени

- Шаг 12 часов, границы по UTC (00:00, 12:00). Корзина `[start, start + 12h)`.
- Цена предмета в корзине = медиана цен в валюте C по снимкам (`price_checks.created_at`) внутри корзины.
- `log_return` = ln(цена корзины / цена предыдущей корзины). Обе цены считаются из сырых снимков.

## 4. Состав корзины (basket)

- Предмет входит в корзину B, если у него есть цена в B и:
  1. он был в корзине B−1 (есть строка `exchange_item_metrics` за B−1), даже если цена ушла ниже порога; или
  2. его не было в B−1, и цена в B `>= min_price`.
- Строка `exchange_item_metrics` существует ⇔ предмет в корзине этой корзины времени.

## 5. Метрики

Таблицы (одна таблица на метрику для всех лиг, с `poe_league_id` где нужно):

- `exchange_item_metrics`: `exchange_item_id`, `exchange_metrics_item_id`, `bucket_start`, `price`, `log_return` (nullable, нет предыдущей цены), `residual` (nullable), `residual_mad` (nullable), `observations`. Unique (`exchange_item_id`, `exchange_metrics_item_id`, `bucket_start`).
- `exchange_market_metrics`: `poe_league_id`, `exchange_metrics_item_id`, `bucket_start`, `market_return`, `basket_size`. Unique (`exchange_metrics_item_id`, `bucket_start`).
- `exchange_item_betas`: `exchange_item_id`, `exchange_metrics_item_id`, `calculated_at`, `beta`, `raw_beta`, `r_squared` (nullable), `observations`. История, не перезаписывается.

Формулы:

- `market_return` = медиана `log_return` предметов корзины, у которых он есть.
- `residual` = `log_return` − β × `market_return`. β берётся из последней записи `exchange_item_betas` с `calculated_at <= bucket_start`. Если записи нет, β = 1.
- `residual_mad` = 1.4826 × медиана(|x − медиана(x)|) по остаткам предмета за последние 14 корзин (7 суток), включая текущую. `observations` = число остатков в окне. Если их меньше `min_residual_observations`, `residual_mad` = null.
- β (раз в сутки): окно 7 суток до момента расчёта, пары (рыночный и предметный `log_return`) из таблиц метрик.
  - `raw_beta` = оценка Тейла–Сена (медиана попарных наклонов).
  - `beta` = w × `raw_beta` + (1 − w), w = n / (n + `beta_prior_weight`), n = число пар.
  - `r_squared` = квадрат корреляции Пирсона предмета и рынка в окне.
  - Предметы с n < `min_beta_observations` пропускаются.

Параметры в `config/poe.php` → `metrics`: `bucket_hours` = 12, `window_buckets` = 14, `min_residual_observations`, `min_beta_observations`, `beta_prior_weight` = 14.

## 6. Дополнительно

- `exchange_item_prices.volume` (nullable float) = `volumePrimaryValue` из poe.ninja. Сохраняется при импорте, старые строки не заполняются.
- `poe_leagues.started_at` (nullable `dateTimeTz`).

## 7. Код

- Сервис `App\Services\DataServices\Metrics\ExchangeMetricsService` (контракт + реализация), методы:
  - `collectBucketMetrics(int $leagueId, CarbonImmutable $bucketStart): void` (раз в 12 часов);
  - `recalculateBetas(int $leagueId, CarbonImmutable $asOf): void` (раз в сутки).
- Репозитории и DTO по соглашениям проекта (`Contract`, `final readonly`, маппер Valinor). Регистрация в `AppServiceProvider`.
- Статистика (медиана, MAD, Тейл–Сен, R²) в отдельном классе без зависимостей от фреймворка.
- Для новых моделей: фабрики. Тесты: feature для репозиториев и сервиса, unit для статистики.
