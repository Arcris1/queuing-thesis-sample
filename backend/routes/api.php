<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\LiveBoardController;
use App\Http\Controllers\Admin\WindowQueueGroupController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\WindowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication (JWT)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/fcm-token', [AuthController::class, 'fcmToken']);
});

/*
|--------------------------------------------------------------------------
| Catalog (public — the mobile app shows the catalog pre-login)
|--------------------------------------------------------------------------
*/
Route::get('/offices', [CatalogController::class, 'offices']);
Route::get('/offices/{office}/services', [CatalogController::class, 'services']);

/*
|--------------------------------------------------------------------------
| Queue
|--------------------------------------------------------------------------
*/
// Public display board: current "now serving" number per queue group.
Route::get('/queue/current', [QueueController::class, 'current']);

Route::middleware('auth:api')->group(function (): void {
    Route::post('/queue/join', [QueueController::class, 'join']);
    Route::post('/queue/leave', [QueueController::class, 'leave']);
    Route::get('/queue/status', [QueueController::class, 'status']);
    Route::get('/queue/estimate', [QueueController::class, 'estimate']);
});

/*
|--------------------------------------------------------------------------
| Geofencing (plan §8 — distance/eligibility decided server-side)
|--------------------------------------------------------------------------
| The client sends only raw lat/lng; the API computes distance via the
| Haversine service and decides within-radius eligibility (tasks 013, 014).
*/
Route::middleware('auth:api')->group(function (): void {
    Route::post('/location/update', [LocationController::class, 'update']);
    Route::post('/checkin', [LocationController::class, 'checkin']);
});

/*
|--------------------------------------------------------------------------
| Presence detection (plan §9 — 30s heartbeat → Active/Away/Offline/Removed)
|--------------------------------------------------------------------------
| The app pings every 30 s. The server timestamps last_seen and derives the
| presence status; a heartbeat carrying GPS doubles as a location ping
| (tasks 015/016).
*/
Route::middleware('auth:api')->group(function (): void {
    Route::post('/heartbeat', [HeartbeatController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Window / staff controls (staff & admin only — gated in the Form Requests)
|--------------------------------------------------------------------------
| The routing engine (task 041) selects the oldest eligible ticket; these
| endpoints (task 021) just drive a window through it.
*/
Route::middleware('auth:api')->group(function (): void {
    Route::post('/windows/{window}/available', [WindowController::class, 'available']);
    Route::post('/windows/{window}/serve', [WindowController::class, 'serve']);
    Route::post('/windows/{window}/skip', [WindowController::class, 'skip']);
    Route::post('/windows/{window}/recall', [WindowController::class, 'recall']);
});

/*
|--------------------------------------------------------------------------
| Admin / dashboard reads (staff & admin) + admin-only window reconfiguration
|--------------------------------------------------------------------------
| Live board + analytics power the Vue dashboard (task 025, plan §7 / §12) and
| are readable by staff and admin. Dynamic queue-group attach/detach (task 043,
| plan §5.4 "no idle windows") is admin-only — gated in the Form Requests.
*/
Route::middleware('auth:api')->prefix('admin')->group(function (): void {
    // Reads — staff or admin (AdminReadRequest).
    Route::get('/queue/{office}/live', [LiveBoardController::class, 'show']);
    Route::get('/analytics', [AnalyticsController::class, 'index']);

    // Dynamic window enabling — admin only (AdminActionRequest).
    Route::post('/windows/{window}/queue-groups', [WindowQueueGroupController::class, 'store']);
    Route::delete('/windows/{window}/queue-groups/{queueGroup}', [WindowQueueGroupController::class, 'destroy']);
});
