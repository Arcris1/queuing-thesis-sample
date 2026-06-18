# ISO/IEC 25010 Evaluation Instrument & Data-Collection Plan

**System under evaluation:** AI-Powered Smart Queue Management System with Geofencing, Presence
Detection, and Predictive Waiting Time Estimation for University Administrative Offices
(Registrar, Accounting, Cashier).

**Standard:** ISO/IEC 25010:2011 — *Systems and software Quality Requirements and Evaluation
(SQuaRE) — System and software quality models* (product quality model).

**Plan reference:** [`../plans/smart-queue-system-plan.md`](../plans/smart-queue-system-plan.md) §14
(Research Components), §8 (Geofencing), §9 (Presence), §10 (AI prediction), §5 (Service routing),
§7/§12 (analytics endpoints).

**Task reference:** [`../tasks/039-iso25010-evaluation.md`](../tasks/039-iso25010-evaluation.md).

**Status:** Defense-ready instrument. This document defines *how* the system is evaluated; it does
not report results (the results belong in the thesis manuscript, Chapter 4).

---

## 1. Purpose & Research Questions

### 1.1 Purpose

The purpose of this evaluation is to determine, in a defensible and reproducible way, whether the
AI-Powered Smart Queue Management System (a) meets acceptable software-product quality under the
ISO/IEC 25010 model and (b) produces measurable improvement in the thesis's dependent variables when
deployed in three real university administrative offices.

The evaluation is **mixed-method and convergent**:

- a **subjective** strand — a Likert-scale ISO 25010 questionnaire answered by students and staff, and
- an **objective** strand — quantitative metrics drawn directly from the running system
  (`service_history`, the analytics endpoint, and the ML model's stored metrics).

Both strands are triangulated so that a perceived improvement (e.g. "I waited less") is corroborated
by a measured one (e.g. average wait time dropped from X to Y minutes).

### 1.2 Variables (from plan §14)

| Role in study | Variable |
|---|---|
| **Independent variable** | The geo-aware, AI-assisted, service-routed queue-management system (the artifact as a whole: service-level queueing with dynamic window routing + geofencing + presence detection + predictive ETA). |
| **Dependent variables** | (1) Waiting time, (2) Student satisfaction, (3) Queue efficiency, (4) Crowd reduction, (5) Window utilization. |

The independent variable is **introduced** (the system is deployed); the dependent variables are
**observed** before vs. during deployment (objective strand) and **perceived** by respondents
(subjective strand).

### 1.3 Research Questions (RQ)

- **RQ1 — Functional suitability.** Does the system correctly and completely perform its intended
  queueing functions — service-level joining, oldest-eligible window routing, geofence eligibility,
  presence-driven skip/standby, and ETA prediction?
- **RQ2 — Usability.** Can students and staff learn and operate their respective apps efficiently,
  with low error and high satisfaction?
- **RQ3 — Reliability.** Does the system remain available and recover correctly under presence loss,
  network drops, and abandoned slots (heartbeat → Away/Offline/Removed → reclaim)?
- **RQ4 — Security.** Are authentication (JWT), role-based access (student/staff/admin), and
  server-side trust boundaries (distance decided server-side) enforced?
- **RQ5 — Performance efficiency.** Are response times, ETA-prediction error, and resource behaviour
  within acceptable bounds under realistic load?
- **RQ6 — Outcome / dependent variables.** Does deploying the system reduce average waiting time and
  on-site crowding, and improve queue efficiency, window utilization, and satisfaction, relative to
  the pre-deployment baseline?

RQ1–RQ5 map to the five ISO 25010 characteristics named in plan §14. RQ6 maps the objective metrics
to the dependent variables.

---

## 2. ISO/IEC 25010 Mapping (system-specific)

Plan §14 scopes the evaluation to **five** of the eight ISO 25010 product-quality characteristics:
**Functional Suitability, Usability, Reliability, Security, Performance Efficiency**. (Compatibility,
Maintainability, and Portability are out of scope for this thesis and are noted as such.)

For each characteristic the table below lists the relevant ISO 25010 sub-characteristics and the
**concrete, system-specific evaluation criteria** mapped to real features of this system, plus the
evidence source (S = survey item group, O = objective metric — see §3 and §4).

### 2.1 Functional Suitability

> "Degree to which the product provides functions that meet stated and implied needs."

| Sub-characteristic | System-specific criterion | Evidence |
|---|---|---|
| **Functional completeness** | Student can join by **service** (not by office/window), monitor position + ETA, leave, check in by QR; staff can mark window available, serve, skip, recall; admin can attach/detach queue groups and view analytics. All endpoints in plan §7 are present and reachable. | S-FS1, S-FS2, O1 (endpoint coverage) |
| **Functional correctness** | **Geofence accuracy:** server-side Haversine distance correctly classifies in-range (≤ radius) vs out-of-range. **Routing correctness:** the routing engine assigns the *oldest eligible* (Active + in-range) waiting ticket within the window's queue groups, FIFO by `joined_at`. **Numbering:** per-group prefixed numbering resets daily. | S-FS3, O8 (geofence false accept/reject), O9 (routing-order correctness) |
| **Functional appropriateness** | **AI ETA appropriateness:** the predicted wait is window-aware ( `(people × avg) ÷ active_windows` relationship learned) and demonstrably closer to actual than the naive estimate; presence skip/standby behaves appropriately when a called student is Away/Offline/out-of-range. | S-FS4, O3 (ETA error), O5 (no-show/skip handling) |

### 2.2 Usability

> "Degree to which the product can be used by specified users to achieve goals with effectiveness,
> efficiency and satisfaction."

| Sub-characteristic | System-specific criterion | Evidence |
|---|---|---|
| **Appropriateness recognizability** | A first-time student recognizes how to pick Office → Service and read their ticket/ETA; staff recognize window controls. | S-US1 |
| **Learnability** | A new user completes a first join (student) / first serve cycle (staff) without external help. | S-US2, O (task-completion in pilot) |
| **Operability** | Material 3 student app and Vue staff dashboard expose all needed actions; controls have hover/focus/disabled states; QR scan and live board are operable. | S-US3 |
| **User error protection** | The app prevents invalid actions (e.g. joining two active tickets, checking in outside radius) and gives clear warnings (geofence warning + 2-min grace). | S-US4 |
| **User interface aesthetics** | Clean, consistent, readable UI (Material 3 / Tailwind), light + dark mode. | S-US5 |
| **Accessibility** | WCAG AA contrast, semantic theming, adequate touch targets. | S-US6 |

### 2.3 Reliability

> "Degree to which the system performs specified functions under specified conditions for a specified
> period of time."

| Sub-characteristic | System-specific criterion | Evidence |
|---|---|---|
| **Maturity** | The system runs a full queueing day without functional failure; broadcasts/live board stay consistent with backend state. | S-RE1, O (uptime/error log) |
| **Availability** | API + realtime (Reverb) + push (FCM) are reachable during office hours; polling fallback works if WebSocket drops. | S-RE2, O (availability %) |
| **Fault tolerance** | **Presence/heartbeat:** missing heartbeats correctly degrade Active → Away (>2 min) → Offline (>5 min) → Removed (>10 min) without corrupting the queue; a slow/lost network does not double-assign a ticket. | S-RE3, O7 (presence-state accuracy) |
| **Recoverability** | **Recoverability via grace + reclaim:** an about-to-be-called Away/Offline student gets a 2-min reconnect window; abandoned slots are reclaimed by the scheduled job and the routing engine advances to the next eligible ticket; a student who reconnects within grace is restored. | S-RE4, O5 (missed/reclaim), O7 |

### 2.4 Security

> "Degree to which the product protects information and data so that persons or systems have the
> degree of data access appropriate to their authorization."

| Sub-characteristic | System-specific criterion | Evidence |
|---|---|---|
| **Confidentiality** | A student cannot read another student's ticket/location; staff/admin data is not exposed to students. | S-SE1, O10 (unauthorized-access attempts blocked) |
| **Integrity** | **Server-side trust boundary:** the client sends raw lat/lng only; the **server** computes distance and decides eligibility — a client-spoofed "distance" is never trusted. QR check-in validates ticket + account + location together. | S-SE2, O10 |
| **Non-repudiation** | `window_assignments` + `service_history` record who served what and when (auditable). | O (audit completeness) |
| **Accountability** | Staff actions (serve/skip/recall, attach/detach) are attributable to an authenticated staff/admin account. | O |
| **Authenticity** | **JWT + role gating:** every protected endpoint requires a valid JWT (`api` guard, `jwt` driver); student/staff/admin routes enforce role authorization (Form Request `authorize()` / middleware). | S-SE3, O10 |

### 2.5 Performance Efficiency

> "Performance relative to the amount of resources used under stated conditions."

| Sub-characteristic | System-specific criterion | Evidence |
|---|---|---|
| **Time behaviour** | API responses (join/status/estimate, window available/serve) return within an acceptable latency budget; the live board and ETA refresh promptly; **ETA computation is fast** (in-Laravel coefficients or microservice call). | S-PE1, O2 (response time), O3 (ETA latency) |
| **Resource utilization** | The scheduled presence job, broadcasts, and analytics aggregations run without excessive CPU/memory/DB load; analytics queries avoid N+1. | O (server resource sampling) |
| **Capacity** | The system sustains the realistic concurrent load of three offices' peak hours (many waiting tickets + 30-s heartbeats) without degradation. | S-PE2, O (load-test throughput) |

> **Note — AI ETA accuracy spans two characteristics.** Prediction *appropriateness/correctness*
> (is the estimate the right thing and close to actual?) is **Functional Suitability** (§2.1). Prediction
> *speed* (how fast is the estimate produced?) is **Performance Efficiency** (§2.5). Both are measured
> by objective metric **O3**.

---

## 3. Survey Instrument (Subjective Strand)

### 3.1 Format and scale

A structured questionnaire using a **5-point Likert scale**:

| Score | Label |
|---|---|
| 5 | Strongly Agree |
| 4 | Agree |
| 3 | Neutral |
| 2 | Disagree |
| 1 | Strongly Disagree |

Items are grouped by ISO 25010 characteristic and worded positively (higher = better). The
questionnaire has a **student version** and a **staff version**; some characteristics (Reliability,
Performance) appear in both with role-appropriate wording. Each version closes with a short
**open-ended** section. The instrument is delivered as a Google Form / printed form (external-survey
plan — see §8.1); no application code is required.

Respondents first read the consent statement (§5.4) and provide non-identifying demographics
(role, office most used, frequency of office visits) before the Likert items.

### 3.2 Student questionnaire

**A. Functional Suitability**
- S-FS1. I was able to join the queue for the exact service I needed (e.g. Assessment, Document
  Request) directly from my phone.
- S-FS2. The app let me do everything I needed: join, see my number and position, see the wait
  estimate, and leave.
- S-FS3. The app correctly recognized when I was near the office and when I was too far away.
- S-FS4. The estimated waiting time the app showed was close to how long I actually waited.

**B. Usability**
- S-US1. It was easy to understand how to choose an office and a service.
- S-US2. I was able to use the app to join the queue without needing help.
- S-US3. The buttons and screens (ticket, map/distance, QR scan, notifications) were easy to use.
- S-US4. The app warned me clearly when something was wrong (e.g. I was outside the allowed area, or
  my turn was near).
- S-US5. The app looked clean, consistent, and pleasant to use (including dark mode).
- S-US6. Text and controls were large enough and easy to read.

**C. Reliability**
- S-RE1. The app worked throughout my wait without crashing or freezing.
- S-RE2. Live updates to my queue position and notifications arrived dependably.
- S-RE3. When my phone briefly lost connection, the app handled it gracefully (I was not unfairly
  removed).
- S-RE4. When my turn was near but I had stepped away, I was given a fair chance (a grace/reconnect
  window) before being skipped.

**D. Security**
- S-SE1. I felt my personal information and location were kept private.
- S-SE2. I trust that the app, not just my phone, decided whether I was close enough to be served.
- S-SE3. I had to log in, and the app only showed me my own queue information.

**E. Performance Efficiency**
- S-PE1. The app responded quickly when I joined, refreshed my status, or scanned the QR code.
- S-PE2. The app stayed responsive even when the office was busy.

**Open-ended (student)**
- O-S1. What did you like most about using the queueing app?
- O-S2. What was confusing, frustrating, or in need of improvement?
- O-S3. Did the app reduce the time you spent physically waiting/crowding at the office? Please
  describe.

### 3.3 Staff questionnaire (Registrar / Accounting / Cashier staff & admins)

**A. Functional Suitability**
- T-FS1. The dashboard correctly called the next eligible student (present and nearby) to my window.
- T-FS2. I could perform all needed actions: mark my window available, serve, skip, and recall a
  number.
- T-FS3. Routing fed my window the right students from the queue groups it serves, with no "wrong
  line" mix-ups.
- T-FS4. Attaching an idle window to a busy queue group (to balance load) worked as expected.

**B. Usability**
- T-US1. It was easy to understand the live queue board (queue groups, windows, presence states).
- T-US2. I learned to operate window controls quickly.
- T-US3. The controls (available/serve/skip/recall, attach/detach) were clear and easy to use.
- T-US4. The dashboard prevented mistakes and showed clear states (waiting/active/away/offline/
  standby).
- T-US5. The dashboard layout was clean, readable, and well organized.

**C. Reliability**
- T-RE1. The dashboard ran through a full service day without failures.
- T-RE2. The live board stayed accurate and updated dependably.
- T-RE3. Students who went offline or left were handled automatically without jamming my window.
- T-RE4. Abandoned/no-show slots were reclaimed correctly so my window kept moving.

**D. Security**
- T-SE1. I had to log in with a staff account, and I could only access staff/admin functions.
- T-SE2. Students could not perform staff actions or see other students' private data.
- T-SE3. I trust the record of who was served at which window and when.

**E. Performance Efficiency**
- T-PE1. The dashboard and window actions responded quickly.
- T-PE2. The dashboard stayed responsive during peak hours.

**F. Outcome (staff perception of dependent variables)**
- T-OUT1. Compared with the old manual/physical line, the system reduced crowding at the office.
- T-OUT2. The system kept windows busier (fewer idle counters).
- T-OUT3. Overall, the system made serving students more efficient.

**Open-ended (staff)**
- O-T1. How did the system change crowding and idle-window time at your office?
- O-T2. What worked well in routing and presence handling?
- O-T3. What should be improved before wider rollout?

### 3.4 Scoring and interpretation

- **Per-item mean** — average of the 1–5 responses for each item.
- **Per-characteristic mean** — mean of the item means within a characteristic (per respondent group,
  then combined). This is the headline ISO 25010 score for that characteristic.
- **Per-group means** — report student and staff separately *and* combined, because some
  characteristics (Functional Suitability, Usability) are experienced differently by each role.
- **Weighted overall quality score** — a single descriptive index. By default characteristics are
  weighted equally; if the panel prefers, apply emphasis weights reflecting the thesis focus
  (suggested: Functional Suitability 0.25, Reliability 0.20, Performance 0.20, Usability 0.20,
  Security 0.15 — must sum to 1.0). State the weighting scheme explicitly in the manuscript.
- **Interpretation scale** (applied to per-characteristic and overall means):

  | Mean range | Verbal interpretation |
  |---|---|
  | 4.21 – 5.00 | Excellent / Highly acceptable |
  | 3.41 – 4.20 | Very good / Acceptable |
  | 2.61 – 3.40 | Moderate / Needs improvement |
  | 1.81 – 2.60 | Poor |
  | 1.00 – 1.80 | Very poor |

- **Acceptability threshold:** a characteristic is considered **accepted** when its combined mean is
  **≥ 4.00** (see §7.2).
- **Internal consistency (Cronbach's alpha):** compute Cronbach's α per characteristic scale to
  confirm the items reliably measure one construct. Target **α ≥ 0.70** (acceptable); 0.60–0.69 is
  marginal and reported with a caveat. Report α for each characteristic and for the full instrument.
  Run α on the pilot data first (§6) and refine/drop weak items before full deployment.

---

## 4. Objective Metrics Plan (Quantitative Strand)

These metrics are **read from the running system** — they are *not* new features to build. Every
metric below is already backed by an existing endpoint, table, or the ML model's stored evaluation
(see "Source" column). The evaluator extracts them per office and per date range; no application code
is implemented as part of this task.

| ID | Metric | Definition / how computed | Backs which DV / RQ | Source (already exists) |
|---|---|---|---|---|
| **O1** | Functional coverage | Count of plan §7 endpoints present and returning expected envelopes vs. the spec list. | RQ1 | `php artisan route:list --path=api`; plan §7 |
| **O2** | API response time | p50 / p95 latency of key endpoints (join, status, estimate, window available/serve, analytics). | RQ5 | Server/request logs; load test (§6) |
| **O3** | ETA prediction error | **MAE** and **RMSE** of predicted wait vs **actual** wait (actual = `served_at − joined_at`), plus naive-baseline error for comparison; also ETA compute latency. | DV1 (waiting time), RQ1, RQ5 | ML model stored metrics (task 023: MAE/RMSE/R²); `service_history`; `GET /api/admin/analytics` `prediction_accuracy` |
| **O4** | Average waiting time (before vs after) | Mean wait per office/queue group/service for the deployment window, compared against the pre-deployment manual baseline (§6.1). | DV1, DV3, RQ6 | `GET /api/admin/analytics` `avg_wait_min`, `by_queue_group`; `service_history` |
| **O5** | No-show / missed / skip rate | (skipped + standby + reclaimed tickets) ÷ total tickets; reclaim correctness. | DV3 (efficiency), RQ3 | `GET /api/admin/analytics` `missed`; `queue_tickets.status`; `window_assignments` |
| **O6** | Window utilization & idle time | Busy time ÷ open time per window; idle minutes; effect of dynamic attach/detach. | DV5 (window utilization), DV3, RQ6 | `GET /api/admin/analytics` `window_utilization`, `by_window`; `window_assignments` |
| **O7** | Presence-state accuracy | Agreement between system-assigned state (Active/Away/Offline/Removed) and ground truth from heartbeat timestamps + thresholds (2/5/10 min); rate of incorrect skips/removals. | RQ3 (reliability/recoverability) | `heartbeats` table; `PresenceService` thresholds; presence counts in `GET /api/admin/queue/{office}/live` |
| **O8** | Geofence false accept / false reject | Compare server Haversine classification (≤ radius vs > radius) against known-position test points / measured ground truth; false-accept and false-reject rates. | RQ1 (correctness), DV4 (crowd reduction) | `location_logs` (`distance_m`); `offices.geofence_radius_m`; geofence service (verified in `GeofenceServiceTest`) |
| **O9** | Routing-order correctness | % of assignments that picked the oldest *eligible* ticket (Active + in-range) within the window's groups, FIFO by `joined_at`. | RQ1 | `window_assignments` vs `queue_tickets.joined_at` + eligibility |
| **O10** | Security enforcement | % of unauthorized/role-mismatch/missing-JWT requests correctly rejected (401/403); confirm client-sent distance is never trusted. | RQ4 | Auth/middleware behaviour; Form Request `authorize()`; manual probe set |
| **O11** | Crowd reduction proxy | Peak concurrent **on-site** (in-range, checked-in) students vs total queued — i.e. how many waited remotely instead of physically crowding. | DV4 (crowd reduction), RQ6 | `location_logs` in-range counts; `GET /api/admin/analytics` peak_hours; check-in records |

**Pairing objective ↔ subjective.** Each dependent variable is supported by both strands so findings
converge:

| Dependent variable | Objective evidence | Subjective evidence |
|---|---|---|
| Waiting time | O3, O4 | S-FS4, S-PE1, O-S3 |
| Student satisfaction | (survey-led) | All student items; O-S1/O-S2 |
| Queue efficiency | O5, O6, O9 | T-OUT3, T-RE4 |
| Crowd reduction | O8, O11 | T-OUT1, O-S3, O-T1 |
| Window utilization | O6 | T-OUT2, T-FS4 |

---

## 5. Respondents, Sampling & Ethics

### 5.1 Population and roles

| Group | Population | Why included |
|---|---|---|
| **Students** | Enrolled students who transact with Registrar, Accounting, or Cashier during the deployment window. | Primary users of the mobile app; experience DV1 (wait), DV2 (satisfaction), DV4 (crowd). |
| **Staff / admin** | Window-serving personnel and supervisors of the three offices (Registrar, Accounting, Cashier). | Operate the dashboard/routing; judge DV3 (efficiency), DV5 (utilization), and reliability/security. |

### 5.2 Target sample sizes

- **Students:** target **≥ 100** completed responses (a defensible minimum for descriptive analysis;
  spread across the three offices and across peak/off-peak times). Larger is better; report the actual
  N and response rate.
- **Staff:** target **all available** window staff and supervisors of the three offices — realistically
  **≈ 9–15** respondents (this is a near-census of a small population, so report it as a census, not a
  sample, and do not over-interpret inferential statistics on it).
- **Pilot:** **5–10 students + 2–3 staff** for the pilot run (§6), used to validate clarity, reliability
  (Cronbach's α), and procedure; pilot data is **not** pooled with the main dataset.

### 5.3 Sampling method

- **Students — convenience / availability sampling at point of service** (students who actually used
  the app during deployment). Because participation requires having used the system, purely random
  sampling of the whole student body is not meaningful; document this as a known limitation.
- **Staff — purposive (census)** of the three offices' window personnel.
- To reduce bias, recruit students across all three offices, multiple days, and both peak and off-peak
  hours; record office and visit-frequency demographics so the sample composition is transparent.

### 5.4 Consent & ethics

- **Voluntary & informed:** each respondent reads a consent statement explaining purpose, that
  participation is voluntary, that they may withdraw at any time, and that responses are anonymous and
  used only for academic research. Survey starts only after consent.
- **Anonymity:** the questionnaire collects **no personally identifying information** (no name,
  student number, or contact). Demographics are non-identifying (role, office, frequency).
- **Data privacy (RA 10173 / institutional policy):** system-side objective metrics are reported in
  **aggregate only**; `location_logs` and `heartbeats` are used to compute rates (O7, O8, O11), never
  to expose an individual's movements. Raw location data is not published.
- **Permissions:** secure clearance from the office heads / registrar and, where required, the
  institution's ethics or research committee before deployment and collection.
- **Storage:** anonymized response exports are stored securely and retained only for the duration of
  the thesis defense process.

---

## 6. Procedure & Timeline

The evaluation follows a **pre/post (baseline vs deployment)** design for the objective strand and a
**post-use survey** for the subjective strand.

### 6.1 Baseline (pre-deployment) — ~1 week

Before the system is live, record the **manual** baseline at the three offices for the same metrics it
will later improve: average physical waiting time, observed crowd size at peak, and (where records
exist) no-show/abandonment. This baseline is the comparison point for O4 and O11 (RQ6).

### 6.2 Pilot — ~1 week

- Deploy to a limited group; collect **5–10 student + 2–3 staff** questionnaires.
- Validate the instrument: check item clarity, compute **Cronbach's α** per characteristic, and revise
  or drop weak items (α < 0.70 scales).
- Dry-run objective extraction (analytics export, ML metrics, presence/geofence logs) to confirm every
  O-metric can actually be pulled.

### 6.3 Deployment & collection — ~3–4 weeks

- Run the system in production hours across the three offices.
- Continuously accumulate objective data (`service_history`, analytics, logs, ML metrics).
- Administer the post-use questionnaire to students at/after their transaction and to staff at the end
  of representative service days, until target N is reached.

### 6.4 Analysis & reporting — ~1–2 weeks

- Clean and tabulate responses; compute the statistics in §7.
- Extract and aggregate objective metrics per office and date range.
- Triangulate strands (§4 pairing table) and map every result back to RQ1–RQ6 and the five dependent
  variables.

### Indicative schedule

| Week | Activity |
|---|---|
| 1 | Baseline measurement (manual lines) + ethics/permissions |
| 2 | Pilot deployment, instrument validation (α), objective dry-run |
| 3–6 | Full deployment, survey collection, continuous metric capture |
| 7–8 | Analysis, triangulation, write-up, defense preparation |

---

## 7. Analysis Plan

### 7.1 Descriptive statistics

- **Per item:** mean and standard deviation.
- **Per characteristic:** mean of item means, SD, and verbal interpretation (§3.4 scale), reported per
  respondent group and combined.
- **Overall:** weighted overall quality index (§3.4) with its verbal interpretation.
- **Internal consistency:** Cronbach's α per characteristic and overall (target ≥ 0.70).
- **Objective metrics:** for each O-metric, report the value(s) with units, and for O4/O11 report the
  **before vs after** pair and the absolute and percentage change. Report O3 as MAE/RMSE for the model
  vs the naive baseline.

### 7.2 Acceptability thresholds (per characteristic)

| Characteristic | Subjective threshold | Supporting objective evidence (target direction) |
|---|---|---|
| Functional Suitability | mean ≥ 4.00 | O8 false accept/reject low; O9 routing-order ≥ 95% correct; O3 model error < naive |
| Usability | mean ≥ 4.00 | Pilot task-completion without assistance |
| Reliability | mean ≥ 4.00 | O7 presence accuracy high; O5 reclaim correct; high availability |
| Security | mean ≥ 4.00 | O10 unauthorized requests rejected (≈100%); client distance never trusted |
| Performance Efficiency | mean ≥ 4.00 | O2 p95 within budget; O3 ETA latency low; stable under peak load |

A characteristic **passes** when the subjective mean meets its threshold **and** its supporting
objective evidence trends in the expected direction. Where they disagree (e.g. high perceived
reliability but measured incorrect skips), report the divergence honestly and discuss it.

### 7.3 Dependent-variable outcomes (RQ6)

For each dependent variable, state the result as a triangulated finding, e.g.:

> **Waiting time (DV1).** Average wait fell from *X* min (baseline, O4) to *Y* min (deployment, O4),
> a *Z*% reduction; the AI ETA achieved MAE *m* min vs naive *n* min (O3); students agreed the estimate
> was close to actual (S-FS4 mean = *…*).

Repeat for satisfaction (DV2), efficiency (DV3, via O5/O6/O9 + staff items), crowd reduction (DV4, via
O8/O11 + open-ended), and window utilization (DV5, via O6 + T-OUT2).

### 7.4 Reporting format

- **Tables:** per-characteristic mean/SD/interpretation/α; objective-metric table; before/after table.
- **Charts:** bar chart of per-characteristic means with the 4.00 threshold line; before/after wait and
  crowd; ETA predicted-vs-actual scatter; window-utilization bars.
- **Narrative:** qualitative synthesis of open-ended responses (themes), mapped to characteristics.
- **Mapping table:** RQ → characteristic → evidence (survey items + O-metrics) → result → verdict.

---

## 8. Data-Collection Mechanism (chosen approach)

### 8.1 Subjective strand — external survey (no app code)

Per task 039's "documented external-survey plan" option, the questionnaire is administered via an
**external form** (e.g. Google Forms / Microsoft Forms or a printed form), one form per respondent
group, mirroring §3. Rationale: zero application risk near deployment, easy anonymity, and native
CSV/XLSX export for analysis. Responses are exported to CSV and analyzed in a spreadsheet / Python /
SPSS for §7.

> *Optional future enhancement (out of scope for this task):* an in-app `POST /api/feedback`
> endpoint + `GET /api/admin/feedback/export` could capture the same Likert items, following backend
> conventions (Form Request → DTO → Service → Resource) with a feature test. This document does **not**
> implement it; if later built, it must reproduce the §3 item set exactly so data stays comparable.

### 8.2 Objective strand — system export (no app code)

All objective metrics are pulled from **existing** surfaces:

- `GET /api/admin/analytics?office_id=&from=&to=` → avg wait, peak hours, served, missed, avg duration
  by office/queue group/service/window, window utilization, and (once task 023 metrics are wired)
  prediction accuracy — backs O2-adjacent aggregates, O4, O5, O6.
- `GET /api/admin/queue/{office}/live` → presence counts for O7 spot-checks.
- `service_history` → ETA actual-vs-predicted (O3), durations, before/after wait (O4).
- ML model stored evaluation (task 023: MAE/RMSE/R²) → O3 baseline-vs-model.
- `location_logs` → geofence classification and in-range counts (O8, O11).
- `heartbeats` + presence thresholds → presence-state accuracy (O7).
- `window_assignments` → routing-order correctness (O9), utilization (O6), audit (security).
- `route:list` + auth probes → coverage (O1) and security enforcement (O10).

Exports (CSV/JSON) are timestamped per office and date range and archived with the anonymized survey
exports for reproducibility.

---

## 9. Limitations

- Student sampling is convenience-based (only users of the system); not generalizable to non-users.
- Staff N is a small census — treat staff statistics descriptively, not inferentially.
- The before/after comparison is quasi-experimental (no randomized control group); confounds (time of
  term, office staffing changes) are noted in the discussion.
- Geofence ground-truth (O8) depends on accuracy of reference test points and device GPS quality.
- ETA error (O3) depends on the volume/representativeness of `service_history` available for training.

---

## Appendix A — Instrument summary

| Characteristic | Student items | Staff items |
|---|---|---|
| Functional Suitability | S-FS1…S-FS4 | T-FS1…T-FS4 |
| Usability | S-US1…S-US6 | T-US1…T-US5 |
| Reliability | S-RE1…S-RE4 | T-RE1…T-RE4 |
| Security | S-SE1…S-SE3 | T-SE1…T-SE3 |
| Performance Efficiency | S-PE1…S-PE2 | T-PE1…T-PE2 |
| Outcome (staff only) | — | T-OUT1…T-OUT3 |
| Open-ended | O-S1…O-S3 | O-T1…O-T3 |

ISO 25010 characteristics evaluated: **5 of 8** (Functional Suitability, Usability, Reliability,
Security, Performance Efficiency). Compatibility, Maintainability, and Portability are out of scope
per plan §14.
