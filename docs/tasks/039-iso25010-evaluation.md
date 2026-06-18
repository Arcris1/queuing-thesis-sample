---
id: 039
title: ISO 25010 evaluation instrument + data collection
status: Todo
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

- [ ] ISO 25010 instrument covers all five characteristics for both respondent groups
- [ ] Feedback capture (API or documented external plan) exists with export
- [ ] Objective metrics from task 025 can be paired with subjective scores
- [ ] Analysis plan maps outcomes to the dependent variables (wait time, satisfaction, efficiency,
      crowd reduction)
- [ ] If API: follows backend conventions and has a feature test

## Verification

```
# if API implemented:
php artisan test --filter=FeedbackTest
curl -X POST localhost:8000/api/feedback -d '{...}' -H 'Content-Type: application/json'
# otherwise: review docs/ instrument + export procedure
```
