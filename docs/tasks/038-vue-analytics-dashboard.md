---
id: 038
title: Vue analytics dashboard w/ charts
status: Done
owner: vue-frontend-designer
plan_ref: "Phase 8 / §12"
depends_on: [25, 34]
---

## Objective

Build the analytics view visualizing queue performance: average waiting time, peak hours, students
served, missed/skip count, service duration, and AI prediction accuracy.

## Context

Analytics data is task 025 (`GET /api/admin/analytics`), including prediction accuracy from the model
metrics (task 023). §12 lists the metrics. Tailwind + a charting library; accessible, responsive.

## Scope

**In scope**
- Analytics view with summary cards (avg wait, served, missed, avg service duration, prediction
  accuracy) and charts (peak hours by hour/day, waiting-time trend).
- Date-range + office filters driving the API query.
- Loading/empty/error states; responsive, accessible chart alternatives (data tables/labels).

**Out of scope**
- The analytics computation (task 025); live board (task 036).

## Implementation notes

Pick a maintained chart library (e.g. Chart.js via vue-chartjs or ECharts). Provide accessible
fallbacks (aria labels, data table) since charts alone aren't screen-reader friendly. Debounce filter
changes. Reuse card/table components from task 034.

## API / contract (if applicable)

- `GET /api/admin/analytics?office_id=&from=&to=` →
  `{ avg_wait_min, peak_hours, served, missed, avg_service_min, prediction_accuracy }` (task 025).

## Acceptance criteria

- [ ] Summary cards and charts render the §12 metrics
- [ ] Office + date-range filters update the data
- [ ] Prediction-accuracy metric displayed
- [ ] Accessible (labels/data-table fallback), responsive
- [ ] Loading/empty/error states handled
- [ ] Component test covers rendering with mocked analytics data

## Verification

```
npm run test
npm run dev    # with seeded history, view analytics; change filters and confirm updates
```
