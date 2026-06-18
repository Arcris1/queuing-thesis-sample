---
id: 039
title: ISO 25010 evaluation instrument + data collection
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 9 / §14"
depends_on: [25]
---

## Objective

Produce the ISO 25010-based evaluation instrument and a mechanism to collect respondent feedback and
system metrics that demonstrate the thesis's dependent variables.

## Context

§14: evaluate with the ISO 25010 model (functional suitability, usability, reliability, security,
performance efficiency) across students and Registrar/Accounting/Cashier staff. Dependent variables:
waiting time, student satisfaction, queue efficiency, crowd reduction. System metrics come from the
analytics endpoints (task 025).

## Scope

**In scope**
- An ISO 25010 questionnaire (per characteristic) for student and staff respondent groups, stored as a
  document under `docs/` and/or as a simple survey schema.
- A lightweight feedback capture path (e.g. `POST /api/feedback` storing responses) OR a documented
  external survey plan, plus export for analysis.
- A method to pair objective metrics (avg wait, served, missed — from task 025) with subjective scores.
- A short analysis plan mapping results to the dependent variables.

**Out of scope**
- Statistical write-up of actual results (thesis document); UI polish for the survey.

## Implementation notes

Keep the instrument aligned to the five named ISO 25010 characteristics with a clear Likert scale. If
implementing `/api/feedback`, follow backend conventions (Form Request → DTO → Service → Resource).
Provide a CSV/JSON export so the data can be analyzed for the defense.

## API / contract (if applicable)

- (If implemented) `POST /api/feedback` `{ respondent_role, characteristic_scores, comments }` →
  `201`; `GET /api/admin/feedback/export` → CSV/JSON (admin only).
- Otherwise: documented external-survey plan + analytics export from task 025.

## Acceptance criteria

- [x] ISO 25010 instrument covers all five characteristics for both respondent groups
      — see [`../evaluation/iso-25010-evaluation-instrument.md`](../evaluation/iso-25010-evaluation-instrument.md)
      §2 (mapping) and §3 (student + staff Likert questionnaires; Appendix A summary).
- [x] Feedback capture (API or documented external plan) exists with export
      — documented external-survey plan in §8.1 (Google/MS Forms or printed, CSV export); optional
      future `POST /api/feedback` noted but intentionally not implemented (docs-only task).
- [x] Objective metrics from task 025 can be paired with subjective scores
      — §4 objective-metrics plan (O1–O11) sourced from `GET /api/admin/analytics`, `service_history`,
      ML MAE/RMSE, `location_logs`, `heartbeats`, `window_assignments`; §4 pairing table maps each
      objective metric to its subjective items.
- [x] Analysis plan maps outcomes to the dependent variables (wait time, satisfaction, efficiency,
      crowd reduction) — §7 analysis plan + §7.3 dependent-variable outcomes (incl. window utilization);
      per-characteristic acceptability threshold ≥ 4.00 (§7.2) and Cronbach's α note (§3.4).
- [x] If API: follows backend conventions and has a feature test — N/A; no API implemented (this task
      is documentation only; the external-survey plan was chosen).

## Verification

This is a documentation-only deliverable; verify by review:

```
# Review the instrument and its index:
open docs/evaluation/iso-25010-evaluation-instrument.md
open docs/evaluation/README.md
```

Expected: instrument covers all five ISO 25010 characteristics for students and staff, defines the
objective-metrics export plan sourced from existing endpoints/tables, and maps results back to all
five dependent variables. No application code is added (backend/mobile/dashboard untouched).
