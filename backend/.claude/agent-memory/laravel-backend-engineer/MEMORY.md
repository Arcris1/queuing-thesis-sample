# Laravel Backend Engineer — Memory Index

- [Project Stack](project_stack.md) — Laravel 13 / PHP 8.4 / Postgres / JWT (php-open-source-saver); docs say "12" but it's 13.
- [Data Model Conventions](project_data_model.md) — push_notifications rename, routing scope, seeder shape, 10-service deviation.
- [API Conventions](project_api_conventions.md) — JWT api guard, {data} envelope, AuthTokenResource shape, public catalog, JsonResource 201 gotcha.
- [Routing Engine](project_routing_engine.md) — RoutingService eligibility seam, lockForUpdate anti-double-assign, window endpoints, FormRequest window() binding gotcha.
- [Geofencing](project_geofencing.md) — GeofenceService Haversine (plan's 8.4m example is wrong, ~3.92m), location/update + QR checkin, no-log policy switch, Ready-vs-routing deviation.
- [ML Prediction](project_ml_prediction.md) — pure-PHP regression (no Python), WaitTimePredictor shared seam, model.json artifact, storage/app/private path gotcha.
