---
name: project-api-envelope
description: Laravel API wraps all responses in a `data` envelope — axios responses need one extra unwrap level
metadata:
  type: project
---

The Laravel API (Resources) wraps every response body in a `data` envelope.

So an axios call returns `response.data.data` for the actual payload. Example: `POST /api/login` returns `{ data: { access_token, token_type, expires_in, user: { id, name, email, role, student_no } } }`, so in code it's `const { data } = await api.post(...); const payload = data.data`.

**Why:** Laravel API Resources default to the `data` key. The original auth store skeleton wrongly read `data.access_token` at the top level — fixed in task 035 to read `data.data.access_token`.

**How to apply:** When consuming any API endpoint via the `src/lib/api.ts` axios instance (baseURL `/api`, JWT bearer interceptor reading localStorage `sq_token`), expect the `{ data: ... }` wrapper. Type responses as `api.get<{ data: T }>(...)`. Validation errors (422) follow Laravel's `{ message, errors: { field: [msg] } }` shape (NOT under `data`).
