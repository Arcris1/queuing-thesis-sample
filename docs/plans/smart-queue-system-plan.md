# AI-Powered Smart Queue Management System — Implementation Plan

**Thesis title:** AI-Powered Smart Queue Management System with Geofencing, Presence Detection, and Predictive Waiting Time Estimation for University Administrative Offices

**Offices covered:** Registrar, Accounting, Cashier

**Date:** 2026-06-18

---

## 1. Overview

A mobile-first queueing system for university administrative offices that lets students join a
queue remotely, monitor progress in real time, and only be served once they are physically near
the office. The system combines four research contributions:

1. **Service-level queueing with dynamic window routing** — students queue by *service*, not by
   office. Tickets flow into shared **queue groups**, and a routing engine assigns the oldest
   eligible ticket to any **window** capable of handling that group, preventing idle windows and the
   "wrong line" problem. (See §5.)
2. **Geofencing validation** — a configurable proximity radius (default **15 meters**) verifies the
   student is physically present before service, computed on the **phone's GPS** using the Haversine
   formula against each office's stored coordinates.
3. **Presence detection** — a periodic heartbeat tags students as Active / Away / Offline / Removed
   so abandoned slots are reclaimed automatically.
4. **AI waiting-time prediction** — a lightweight regression model predicts wait time per queue
   group, accounting for the number of active windows, far more accurately than the naive
   `people_ahead × avg_service_time`.

**Architecture principle:** everything is **API-first**. The Flutter app and the admin dashboard are
pure clients of a REST API. No business logic lives in the clients.

---

## 2. Problem Statement

Students currently:
- Wait in long physical lines and crowd offices.
- Leave the area and lose their turn.
- Have no visibility into queue status or realistic wait time.
- Pick the *wrong line* — joining a window queue that turns out to be slow or unable to handle their
  request — while a capable window sits idle.

The system lets students join a queue from their phone by the *service* they need, wait elsewhere,
and be guided back only when their turn is near — while preventing "ghost queueing" (reserving a slot
from far away) and keeping every window productive.

---

## 3. System Architecture

```
   Flutter Mobile App            Admin Dashboard (Web)
           |                              |
           +-------------+----------------+
                         |
                    REST API (JWT)
                         |
                  Laravel 13 Backend
                         |
        +----------------+-----------------+
        |                |                 |
  PostgreSQL / MySQL  Queue Routing   ML Prediction
                       Engine         (regression)
```

- **Frontend (student):** Flutter — queue ticket, push notifications, GPS tracking, QR scanning.
- **Frontend (staff/admin):** Web dashboard — real-time queue, presence states, window control,
  analytics.
- **Backend:** Laravel 13 REST API with JWT authentication.
- **Queue routing engine:** server-side service that maps available windows to the oldest eligible
  waiting ticket in their queue groups (see §5).
- **Database:** PostgreSQL or MySQL.
- **ML:** Linear/Gradient regression model for wait-time estimation (small, defensible, no LLM).
- **Realtime:** WebSockets (Laravel Reverb / Pusher) or polling fallback for live updates.

---

## 4. Technology Stack

| Layer | Technology |
|-------|-----------|
| Mobile app | Flutter |
| Backend API | Laravel 13 (REST) |
| Auth | JWT |
| Database | PostgreSQL or MySQL |
| Realtime | Laravel Reverb / Pusher / WebSockets |
| Push notifications | Firebase Cloud Messaging (FCM) |
| ML model | scikit-learn (Linear Regression / Gradient Boosting) |
| Distance | Haversine formula (computed server-side from phone GPS) |

---

## 5. Queue Model — Office → Queue Group → Service → Windows

The core design decision: **do not queue by office, and do not create one rigid queue per service.**
Queue by service, group related services into a shared **queue group**, and let a routing engine
assign tickets to any **window** that serves that group.

### 5.1 Three-level hierarchy

```
Office
  └─ Queue Group   (a shared waiting line, with a ticket prefix)
        └─ Service (what the student actually selects)
```

Proposed grouping for the three offices:

```
Accounting
  ├─ General Transactions Queue (prefix A)
  │     ├─ Assessment
  │     └─ Payment Verification
  └─ Refund Queue (prefix R)
        └─ Refund Requests

Registrar
  ├─ General Services Queue (prefix RG)
  │     ├─ Enrollment Concerns
  │     ├─ Document Requests
  │     └─ Grades Verification
  └─ Transcript Queue (prefix T)
        └─ Transcript Requests        (split out: longer, different processing)

Cashier
  └─ Payments Queue (prefix C)
        ├─ Tuition Payment
        ├─ Miscellaneous Fees
        └─ Official Receipts
```

A student selects **Office → Service**; the system places the ticket into the service's **queue
group** and issues a number with that group's prefix (e.g. `A-001`, `RG-014`, `T-003`). Several
services can share one queue group (so `A-001` Assessment and `A-002` Payment Verification sit in the
same line), while long or specialized services get a dedicated group.

### 5.2 Windows

A **window** is a physical service counter. Windows are attached to one or more **queue groups** via
the `window_queue_groups` pivot. Example:

```
Window 1 → Accounting General Queue
Window 2 → Accounting General Queue
Window 3 → Refund Queue
```

Windows are mapped to **queue groups**, not directly to services — this is what lets multiple windows
share a single line and what makes dynamic re-assignment a one-row change.

### 5.3 Routing engine

Maintain **per-queue-group** waiting lines (not per-window queues). When a window becomes available:

1. Look up the queue groups attached to the window.
2. Find the **oldest eligible** waiting ticket across those groups — eligible = presence Active and
   (when geofencing requires it) within the office radius.
3. Create a `window_assignments` row, set the ticket to `serving`, and broadcast the call.

Ordering is FIFO by `joined_at` within a group (priority lanes layer on later — see §5.5).

**Worked example (Accounting General served by Window 1 & 2):**

```
A-001 Assessment            Window 1 available → assign A-001
A-002 Payment Verification  Window 2 available → assign A-002
A-003 Assessment            Window 1 free      → assign A-003
A-004 Payment Verification  Window 2 free      → assign A-004
```

### 5.4 Dynamic window enabling (no idle windows)

Because windows attach to queue groups, an idle window can be temporarily widened. If the Refund
Queue is empty, an admin attaches Window 3 to the Accounting General Queue as well; the routing
engine immediately starts feeding it General tickets. Removing the extra attachment reverts it. This
is the key efficiency win and is purely a `window_queue_groups` change — no code change, no redesign.

### 5.5 Why this scales

This three-level model (Office → Queue Group → Service) with window-to-group mapping lets us later
add, without redesigning anything:

- Priority lanes (PWD, Senior Citizen, Faculty) — a `priority` flag on the ticket, honored by the
  routing engine.
- Appointment scheduling.
- Multiple campuses.
- Self-service kiosks.
- AI prediction per service *and* per window.

---

## 6. Data Model

| Table | Key fields |
|-------|-----------|
| `users` | id, name, student_no, role (student/staff/admin), email, password_hash, fcm_token |
| `offices` | id, name (Registrar/Accounting/Cashier), latitude, longitude, geofence_radius_m (default 15) |
| `queues` | id, office_id, date, status (open/closed) — daily session container; unique(office_id, date), drives daily reset of per-group numbering |
| `queue_groups` | id, office_id, name, prefix (e.g. A/R/RG/T/C), status (open/closed) |
| `services` | id, office_id, **queue_group_id**, name, avg_service_minutes |
| `windows` | id, office_id, name (e.g. "Window 1"), status (open/idle/closed) |
| `window_queue_groups` | id, window_id, queue_group_id  *(pivot: which groups a window serves)* |
| `queue_tickets` | id, **queue_group_id**, service_id, user_id, ticket_number, status (waiting/ready/serving/served/skipped/standby), priority (default 0), joined_at, called_at, served_at |
| `window_assignments` | id, window_id, ticket_id, assigned_at, served_at |
| `location_logs` | id, user_id, ticket_id, latitude, longitude, distance_m, recorded_at |
| `heartbeats` | id, user_id, ticket_id, last_seen, battery_level, network_status |
| `notifications` | id, user_id, type, message, sent_at, read_at |
| `service_history` | id, office_id, queue_group_id, service_id, window_id, served_at, duration_minutes, day_of_week, hour_of_day, active_windows (for ML training) |

Notes:
- A ticket belongs to a **queue group** (the line it waits in) and references the **service** the
  student picked (drives `avg_service_minutes` and analytics).
- `window_assignments` is the source of truth for who served what — it feeds both the routing engine
  and `service_history`.

---

## 7. API Endpoints

**Auth**
- `POST /api/login`
- `POST /api/logout`
- `POST /api/register`

**Catalog (for the join screen)**
- `GET  /api/offices` — list offices
- `GET  /api/offices/{office}/services` — services grouped by queue group

**Queue (student)**
- `POST /api/queue/join` — body: `service_id` → ticket created in the service's queue group
- `POST /api/queue/leave`
- `GET  /api/queue/status` — caller's ticket + position within its queue group + ETA
- `GET  /api/queue/current` — public display: current number per queue group
- `GET  /api/queue/estimate` — AI-predicted wait time + confidence for the caller's queue group

**Location & presence**
- `POST /api/location/update` — body: latitude, longitude → server computes distance
- `POST /api/heartbeat` — body: battery_level, network_status, gps_location
- `POST /api/checkin` — QR-based arrival check-in

**Window / staff** (routing-engine driven)
- `POST /api/windows/{window}/available` — mark window free → engine assigns & returns the next ticket
- `POST /api/windows/{window}/serve` — complete current assignment (writes `service_history`)
- `POST /api/windows/{window}/skip` — skip current (away/offline or no-show) → standby/next
- `POST /api/windows/{window}/recall` — re-announce the current number

**Admin**
- `GET  /api/admin/analytics`
- `GET  /api/admin/queue/{office}/live` — live board across queue groups & windows
- `POST /api/admin/windows/{window}/queue-groups` — attach a queue group to a window (dynamic enable)
- `DELETE /api/admin/windows/{window}/queue-groups/{group}` — detach

---

## 8. Distance Computation (Geofencing)

Each office stores fixed coordinates. The phone reports its GPS position; the server computes the
distance with the **Haversine formula** (R = 6,371,000 m):

```
a = sin²((lat2−lat1)/2) + cos(lat1)·cos(lat2)·sin²((lon2−lon1)/2)
distance = 2 · R · asin(√a)
```

**Example:** Office (14.600100, 121.050100) vs student (14.600120, 121.050130) ≈ **3.9 m** →
within 15 m → eligible. (Computed via the Haversine formula above; verified in
`GeofenceServiceTest`.)

- `distance ≤ radius (15 m)` → ticket eligible for assignment by the routing engine.
- `distance > radius` when turn approaches → warning notification + grace period.
- Still outside after grace period → skipped or moved to **standby**; the routing engine moves on to
  the next eligible ticket in the group.

Radius is **configurable per office** (`offices.geofence_radius_m`).

---

## 9. Presence Detection (Away / Offline / Removed)

The app sends a heartbeat **every 30 seconds** to `POST /api/heartbeat`.

| Status | Condition |
|--------|-----------|
| Active | last heartbeat < 2 min |
| Away | last heartbeat > 2 min |
| Offline | last heartbeat > 5 min |
| Removed | last heartbeat > 10 min |

A scheduled job (Laravel scheduler/cron) re-evaluates statuses. Only **Active**, in-range tickets are
eligible for window assignment. When an **Away/Offline** ticket would be next, the student gets a
"2 minutes to reconnect" notification; if still unavailable, the ticket is **skipped** or moved to
**standby** and the engine assigns the next eligible ticket.

---

## 10. AI Waiting-Time Prediction

Replaces naive `people_ahead × avg_minutes` with a trained regression model that is **queue-group
aware and window-aware**.

**Model:** Linear Regression (baseline) → optionally Gradient Boosting for accuracy. Small,
explainable, easy to defend — no LLM required.

**Features (inputs):**
- people ahead in the student's **queue group**
- selected service type (and its avg duration)
- **number of active windows serving that queue group**
- office
- time of day
- day of week
- recent rolling service speed

**Why window count matters:** a shared queue served by more windows clears faster.

```
8 people ahead in Accounting General, avg 4 min, 2 windows serving it
naive:      8 × 4        = 32 min
windowed:  (8 × 4) ÷ 2   = 16 min
```

Even within a shared group the model uses per-service durations (e.g. Assessment ≈ 3 min vs Payment
Verification ≈ 5 min) to refine the estimate.

**Output:** estimated wait time (minutes) + confidence score.

**Training data:** `service_history` (now including `queue_group_id`, `window_id`, `active_windows`),
ideally 3–6 months. Bootstrap with seeded/synthetic data for the defense if real history is thin.

**Serving options:**
1. Python microservice (FastAPI + scikit-learn) called by Laravel, or
2. Export model coefficients and compute prediction directly in Laravel.

---

## 11. Queue Flow

```
Student → Select Office → Select Service
        → Ticket issued in the service's QUEUE GROUP (prefixed number)
        → AI calculates ETA (queue group + active windows)
        → Monitor GPS + presence while waiting
        ↓
Window becomes available
        → Routing engine scans the window's queue groups
        → Picks oldest ELIGIBLE ticket (Active + within 15 m)
        ↓
   eligible?
   ├─ yes → Assign (window_assignments) → Call number → Serve → write service_history
   └─ no (away/offline/out-of-range)
            → Warning → grace period (2 min)
                 ├─ becomes eligible → assign
                 └─ still not → Skip / Standby → engine takes next eligible ticket
```

Parallel: heartbeat monitor tags Away/Offline/Removed and the geofence check gates eligibility so the
routing engine never calls an absent student.

---

## 12. Additional Features

- **QR check-in:** on arrival, student scans office QR; server validates ticket number, account, and
  location together.
- **Smart push notifications:** ETA-based messages — "Estimated wait: 35 min" → "Estimated wait:
  12 min" → "Please proceed to Accounting Window 2 — you are now being called."
- **Real-time staff dashboard:** per queue group and per window — waiting / active / away / offline
  counts, average wait, prediction accuracy; window controls and dynamic queue-group attach/detach.
- **Analytics:** average waiting time, peak hours, students served, missed-queue count, service
  duration per office, queue group, service, and window; window utilization / idle time.

---

## 13. Implementation Phases

**Phase 1 — Backend foundation (API-first)**
- Laravel 13 project, JWT auth, database schema/migrations.
- Schema for offices, **queue_groups, services (with queue_group_id), windows, window_queue_groups**,
  queue_tickets, window_assignments.
- Seeders for offices, queue groups, services, and windows (per §5.1).
- Catalog endpoints + core queue endpoints (join/leave/status/current).

**Phase 2 — Routing engine**
- Window-availability assignment service (oldest eligible ticket per queue group), `window_assignments`
  lifecycle, dynamic queue-group attach/detach, FIFO ordering with a `priority` hook.

**Phase 3 — Geofencing**
- Haversine distance service, `location/update` endpoint, eligibility gating in the routing engine.

**Phase 4 — Presence detection**
- Heartbeat endpoint, status state machine, scheduled re-evaluation job, skip/standby logic feeding
  the routing engine.

**Phase 5 — Realtime + notifications**
- WebSockets for live queue/window board, FCM push integration, smart ETA messages.

**Phase 6 — Flutter mobile app**
- Login, office→service selection, ticket view, GPS + heartbeat background tasks, QR scan,
  notifications.

**Phase 7 — AI prediction**
- Collect/seed history (queue-group + window aware), train regression model, expose
  `/api/queue/estimate`, wire into app + dashboard.

**Phase 8 — Admin dashboard + analytics**
- Live queue-group/window board, presence states, window controls, dynamic enabling, analytics
  charts.

**Phase 9 — Evaluation**
- ISO 25010 evaluation, deployment, data collection with respondents.

---

## 14. Research Components

- **Independent variable:** geo-aware, AI-assisted, service-routed queue management system.
- **Dependent variables:** waiting time, student satisfaction, queue efficiency, crowd reduction,
  window utilization.
- **Evaluation:** ISO 25010 — functional suitability, usability, reliability, security, performance
  efficiency.
- **Respondents:** students; Registrar, Accounting, and Cashier staff.

**Stated contribution:** a proximity-aware, service-routed queueing mechanism that (a) queues by
service into shared queue groups and dynamically routes tickets to capable windows to eliminate idle
counters, (b) validates a student's physical presence within a configurable radius before service,
and (c) manages presence via heartbeats with predictive (non-LLM) waiting-time estimation that
accounts for active windows — elevating the project to a smart-campus solution.

---

## 15. Configurable Parameters (defaults)

| Parameter | Default |
|-----------|---------|
| Geofence radius | 15 m |
| Heartbeat interval | 30 s |
| Away threshold | 2 min |
| Offline threshold | 5 min |
| Removed threshold | 10 min |
| Location-check trigger | 5 remaining before turn |
| Reconnect grace period | 2 min |
| Routing order | FIFO by joined_at within queue group (priority-aware) |
