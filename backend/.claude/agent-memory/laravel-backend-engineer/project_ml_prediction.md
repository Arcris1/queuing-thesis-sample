---
name: project-ml-prediction
description: AI wait-time prediction pipeline (tasks 022-024) — pure-PHP regression, model.json artifact, shared WaitTimePredictor seam, storage path gotcha.
metadata:
  type: project
---

The AI wait-time prediction (plan §10, tasks 022-024) is implemented entirely in
pure PHP — NO Python microservice (the defensible, self-contained thesis choice).

**Why:** prompt explicitly forbade a Python service; coefficients are computed
and stored in Laravel so the whole pipeline is reproducible and defensible.

**How to apply:** keep all ML logic in PHP. The pieces:
- `app/Support/LinearRegression.php` — stateless OLS via normal equations +
  Gauss-Jordan with partial pivoting and a tiny ridge (collinear one-hot safety).
- `app/Support/WaitTimeModel.php` — the artifact + feature encoding. Bundles the
  category vocabularies (office/queue_group/service ids) WITH coefficients so
  train-time and inference-time `encode()` are identical. Two kinds: `linear`
  and `fallback` (per-service averages for cold-start). Design vector order:
  [intercept, avg_service_minutes, hour, day, active_windows, one-hot offices…,
  one-hot queue_groups…, one-hot services…]. Target = per-row SERVICE minutes.
- `app/Services/WaitTimeTrainer.php` — reads `service_history`, deterministic
  seeded shuffle (mt_srand(2026)) for a reproducible holdout, reports R²/RMSE.
  Cold-start floor `prediction.min_training_rows` (50) → stores fallback.
- `app/Services/WaitTimePredictor.php` — THE shared seam. Memoizes the loaded
  model per request. `predictForTicket()` and `predict()` apply the formula
  `(people_ahead × predicted_service_minutes) ÷ max(active_windows, 1)` (plan's
  (8×4)/2=16). Confidence maps holdout R² → [min, 1.0] (config-documented).

**Shared seam (status / estimate / push all one code path):** `WaitTimePredictor`
is used by (1) `QueueService::estimateFor()` → GET /api/queue/estimate, (2)
`QueueService::statusForTicket()` populates `TicketStatusData->eta` →
QueueTicketResource `eta` object, (3) `NotificationService::estimateMinutesForTicket()`.
The old naive `NotificationService::estimateMinutes(int)` is retained as the
window-unaware baseline (still tested) — the model path is the `*ForTicket` method.

**Capture (task 022):** `RoutingService::serve()` writes one `service_history`
row INSIDE the transaction via `recordServiceHistory()` (duration from
called_at→served_at floored at 1 min; active_windows = open windows on the group
+1 for the just-served window). Replaced the old hook comment.

**Commands:** `php artisan ml:seed-history {count=1500}` (wraps
`ServiceHistorySeeder` — CLEARLY-SYNTHETIC, peak-hour/Monday/weekend/window
effects + gaussian noise; NOT in DatabaseSeeder by default). `php artisan ml:train`.

**STORAGE PATH GOTCHA:** the `local` disk roots at `storage/app/private/` in this
Laravel version, so the artifact is at `storage/app/private/ml/model.json` (config
`prediction.model_path` = `ml/model.json`). Use
`Storage::disk('local')->path(...)` to print the real path — do NOT hardcode
`storage/app/`. Config lives under `queue_system.prediction.*`.

Enum `App\Enums\PredictionBasis` (model|fallback). DTOs: `WaitTimeFeatures`,
`WaitTimePrediction`. Resource: `QueueEstimateResource`. See also
[[project-routing-engine]] and [[project-api-conventions]].
