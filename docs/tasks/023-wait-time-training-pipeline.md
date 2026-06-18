---
id: 023
title: Wait-time regression training pipeline
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 7 / §10"
depends_on: [22]
---

## Objective

Train a lightweight regression model that predicts waiting time from queue features, and make the
trained model available to the API for inference.

## Context

§10: a small model (Linear Regression baseline, optionally Gradient Boosting), no LLM, that is
**queue-group and window aware**. Features: people ahead **in the queue group**, selected service type
and its avg duration, **number of active windows serving the group**, office, time of day, day of week,
recent service speed. The key relationship the model must learn is that more windows clear a shared
group faster (`(people × avg) ÷ windows`). Two serving options in the plan: (1) Python microservice
(FastAPI + scikit-learn) called by Laravel, or (2) export coefficients and compute in Laravel. Choose
one and document it.

## Scope

**In scope**
- A training script reading `service_history` (task 022) and fitting the model with one-hot/encoded
  categorical features (office, queue_group, service) + numeric (people_ahead, active_windows, hour,
  day, service avg duration).
- Output an evaluation (MAE/RMSE, R²) and a confidence estimate approach.
- Persisted artifact: either a deployed Python service endpoint OR exported coefficients/JSON the
  Laravel side can load.
- A documented retrain command/process.

**Out of scope**
- The `/api/queue/estimate` endpoint that consumes the model (task 024).

## Implementation notes

Start with Linear Regression for explainability (easy to defend). Record metrics so the dashboard
(task 038) can show "prediction accuracy." If using option 2, export coefficients + feature encoding
so Laravel can compute deterministically. Keep training reproducible (fixed split).

## API / contract (if applicable)

- If Python microservice: `POST /predict { office_id, queue_group_id, service_id, people_ahead,
  active_windows, hour, day }` → `{ minutes, confidence }`. Document host/port and auth.
- If in-Laravel: a `PredictionService::predict(features): {minutes, confidence}` contract.

## Acceptance criteria

- [x] Model trains from `service_history` and reports MAE/RMSE/R²
- [x] Chosen serving approach (microservice vs exported coefficients) implemented + documented
- [x] Categorical features encoded consistently between training and inference
- [x] A confidence value is produced alongside the prediction
- [x] Retrain process documented and repeatable

## Verification

```
# option 1:
python train.py && python -m pytest
curl -X POST localhost:8001/predict -d '{...}'
# option 2:
php artisan test --filter=PredictionServiceTest
```
