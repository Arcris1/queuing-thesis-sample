# Evaluation

Evaluation artifacts for the AI-Powered Smart Queue Management System thesis (Phase 9, plan §14).

| Document | Purpose |
|---|---|
| [`iso-25010-evaluation-instrument.md`](./iso-25010-evaluation-instrument.md) | The defense-ready ISO/IEC 25010 evaluation instrument and data-collection plan: research questions, ISO 25010 characteristic→feature mapping, Likert survey (student + staff), objective-metrics plan, respondents/sampling/ethics, procedure/timeline, and analysis plan. |

## At a glance

- **Standard:** ISO/IEC 25010:2011 product-quality model — **5 of 8** characteristics evaluated:
  Functional Suitability, Usability, Reliability, Security, Performance Efficiency (per plan §14).
- **Design:** convergent mixed-method — a **subjective** Likert questionnaire (students + Registrar/
  Accounting/Cashier staff) triangulated with an **objective** metrics strand pulled from the running
  system (`service_history`, `GET /api/admin/analytics`, ML model MAE/RMSE, `location_logs`,
  `heartbeats`, `window_assignments`).
- **Dependent variables covered:** waiting time, student satisfaction, queue efficiency, crowd
  reduction, window utilization.
- **Acceptability threshold:** per-characteristic combined mean **≥ 4.00**, corroborated by the
  matching objective metric.
- **Data collection:** external survey (Google/MS Forms or printed) + system metric exports — **no
  application code is implemented by this task.**

## Related

- Plan: [`../plans/smart-queue-system-plan.md`](../plans/smart-queue-system-plan.md) §14.
- Task: [`../tasks/039-iso25010-evaluation.md`](../tasks/039-iso25010-evaluation.md).
- Source endpoints/data: tasks 025 (analytics), 022/023 (service history + ML metrics),
  013 (geofence/location logs), 015/016 (heartbeats/presence), 021/041 (window assignments/routing).
