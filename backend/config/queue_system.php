<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Geofencing
    |--------------------------------------------------------------------------
    |
    | Default proximity radius (in meters) a student must be within to be
    | eligible for service. Each office may override this via its own
    | `geofence_radius_m` column; this is the fallback / seed default.
    |
    */
    'geofence_radius_m' => 15,

    /*
    |--------------------------------------------------------------------------
    | Geofence policy (plan §8)
    |--------------------------------------------------------------------------
    |
    | The routing engine treats a waiting ticket as geofence-eligible only when
    | the user's latest location_log for that ticket is both within range AND
    | recent enough (no older than `max_age_seconds`). A stale "within range"
    | sample is treated as no signal, not as proof of presence.
    |
    | `require_location` is the single policy switch for tickets that have NO
    | location log yet: when false (default, pre-strict), a ticket with no
    | sample is treated as eligible (best-effort, so the engine works before the
    | mobile app reliably reports GPS); flip to true to require an in-range
    | sample before any ticket can be assigned.
    |
    */
    'geofence' => [
        'max_age_seconds' => 120,
        'require_location' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence detection (plan §9 / §15)
    |--------------------------------------------------------------------------
    |
    | Heartbeat interval and the Active → Away → Offline → Removed thresholds,
    | all expressed in seconds. The presence service derives a PresenceStatus
    | from the delta between now and a ticket's last heartbeat.
    |
    */
    'heartbeat_interval_seconds' => 30,

    'presence' => [
        'away_after_seconds' => 2 * 60,
        'offline_after_seconds' => 5 * 60,
        'removed_after_seconds' => 10 * 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing & reconnect (plan §9 / §15)
    |--------------------------------------------------------------------------
    */
    'location_check_trigger' => 5,
    'reconnect_grace_seconds' => 2 * 60,

    /*
    |--------------------------------------------------------------------------
    | Notifications (plan §12 / task 020)
    |--------------------------------------------------------------------------
    |
    | `position_milestones` are the people-ahead thresholds that trigger a
    | "you are now #N" push as a student advances (debounced — we only notify
    | when crossing one). `avg_service_minutes` is the PLACEHOLDER per-person
    | service time used for the ETA estimate (eta ≈ people_ahead × this) until
    | task 024 supplies the real regression prediction.
    |
    */
    'notifications' => [
        'position_milestones' => [10, 5, 3, 1],
        'avg_service_minutes' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI wait-time prediction (plan §10 / tasks 022–024)
    |--------------------------------------------------------------------------
    |
    | The wait-time model is a multiple Linear Regression trained in pure PHP
    | (no Python microservice — the defensible, self-contained thesis approach).
    | Its learned coefficients + feature metadata + holdout quality metrics are
    | exported to a JSON artifact (`storage_path('app/ml/model.json')` by
    | default) which the WaitTimePredictor loads at inference time.
    |
    |   target = predicted SERVICE duration (minutes) for a given context
    |   estimate = (people_ahead × target) ÷ max(active_windows, 1)   [plan §10]
    |
    | `min_training_rows` is the cold-start floor: below it, training stores a
    | per-service-average fallback model instead of fitting the regression.
    | `confidence` maps the holdout R² onto a [floor, 1.0] score so the API
    | always returns a usable confidence even for a weak model.
    |
    */
    'prediction' => [
        'model_path' => 'ml/model.json',
        'min_training_rows' => 50,
        'holdout_fraction' => 0.2,
        'min_duration_minutes' => 1,
        'confidence' => [
            // R² floor below which we report the minimum confidence, and the
            // minimum confidence itself (a model is never reported as 0% sure).
            'r2_floor' => 0.0,
            'min' => 0.3,
            // The fallback (no-model / per-service-average) basis reports this
            // fixed, deliberately modest confidence.
            'fallback' => 0.4,
        ],
    ],

];
