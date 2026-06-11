# AI Summarizer — Task Breakdown (3-day plan)

Timeboxed plan. Each day ends with something demoable. Cut stretch items first if behind.

## Day 0 — prerequisites (before you start, ~30 min)

- [ ] Create an Anthropic API key in the Console with billing + a low spend limit.
      (Pro on claude.ai does **not** include API access.)
- [ ] AWS account ready; decide region.
- [ ] Repos/monorepo created; `.env.example` for API and frontend.

## Day 1 — foundation & API skeleton

Backend:
- [ ] `docker-compose`: Laravel (PHP 8.3) + PostgreSQL. App boots, DB connects.
- [ ] Auth with Sanctum: register, login, token issuance; `role` on users.
- [ ] Migrations + Eloquent models: `users`, `summaries` (per ERD).
- [ ] Endpoints (return stubbed/sync data first): `POST /api/summaries`,
      `GET /api/summaries`, `GET /api/summaries/{id}`, `DELETE /api/summaries/{id}`.
- [ ] Request validation (url XOR text, style enum, text length cap).
- [ ] Wire `QUEUE_CONNECTION` (local: `database` driver or LocalStack).

Frontend:
- [ ] Next.js app + TS, auth pages (login/register), typed API client.

**End of day:** can register, log in, and create/list summaries (not yet AI-processed).

## Day 2 — AI pipeline & async

Backend:
- [ ] Content extractor service: fetch URL → readable text, with **SSRF guards**
      (scheme allowlist, private/link-local IP block, timeout, max size, redirect checks).
- [ ] Anthropic client: call Haiku 4.5, per-style prompt templates, capped `max_tokens`,
      read `usage` tokens, compute `cost_usd` from config rates.
- [ ] `ProcessSummaryJob`: status `pending→processing→completed/failed`, persist result +
      tokens + cost + model; store `error_message` on failure.
- [ ] `POST /api/summaries` dispatches the job to the queue (202 response).
- [ ] Long-input handling: truncate to token budget (note in `metadata`).

Frontend:
- [ ] Submit form (URL or text + style selector).
- [ ] Result card with **polling** of `GET /api/summaries/{id}` while pending/processing.
- [ ] History list with status badges; delete action.

**End of day:** full async loop works locally end to end.

## Day 3 — admin, hardening, deploy

Backend / admin:
- [ ] Admin middleware (`role=admin`) + endpoints: `/api/admin/summaries`,
      `/api/admin/users`, `/api/admin/stats` (token/cost aggregates).
- [ ] Rate limiting (per-user throttle, 429).
- [ ] Error handling polish; consistent error schema.

Frontend:
- [ ] `/admin` views: all summaries, users list, stats (totals + by status).

Deploy (AWS):
- [ ] RDS PostgreSQL; SQS standard queue + DLQ (`maxReceiveCount` ~3).
- [ ] Secrets in Secrets Manager / SSM (Anthropic key, DB creds).
- [ ] API on ECS Fargate behind ALB; Worker as a second Fargate service
      (`queue:work sqs`). (Or Lightsail/EC2 + supervisor as the quick alternative.)
- [ ] Frontend on Amplify Hosting (or Fargate). CORS locked to frontend origin.
- [ ] IAM task roles: API → `sqs:SendMessage`; Worker → receive/delete/visibility.
- [ ] Smoke test the full flow in prod; check CloudWatch logs + DLQ is empty.
- [ ] Finalize README + record a short demo.

**End of day:** deployed, working demo with admin stats and cost tracking.

## Stretch (only if ahead)

- Map-reduce summarization for very long inputs.
- SSE/WebSocket status push instead of polling.
- Re-run / model selector (Haiku ↔ Sonnet) in the UI.
- Basic CloudWatch alarms (queue depth, DLQ count).
