# CLAUDE.md

Context for Claude Code working in this repository. Read this first, then consult
`docs/` for detail. Keep changes aligned with the decisions below — if something here
conflicts with a request, flag it before proceeding.

## Project

AI Summarizer: turns a URL or pasted text into a concise summary via an LLM.
Generation is **asynchronous** (queued, processed by a worker, fetched by polling).
Per-user history + an admin panel with token/cost stats. Target deploy: AWS.

Full spec lives in:
- `docs/01-product-scope.md` — scope, user stories, requirements
- `docs/02-technical-design.md` — architecture, data flow, ERD, AWS, security, prompts
- `docs/03-openapi.yaml` — API contract (source of truth for endpoints)
- `docs/04-task-breakdown.md` — 3-day plan

## Stack

- **Frontend + admin:** Next.js (App Router) + TypeScript — in `web/`
- **Backend API:** Laravel (PHP 8.3) + Sanctum — in `api/`
- **Worker:** Laravel queue worker (`php artisan queue:work`) — same codebase as API
- **Queue:** local `database` driver / LocalStack; prod `sqs` (+ DLQ)
- **DB:** PostgreSQL (Docker locally, RDS in prod)
- **LLM:** provider-agnostic `LlmClient`; default Anthropic Haiku 4.5

## Repo layout

```
api/        Laravel API + queue worker
web/        Next.js frontend + /admin
docs/       Specs (see above)
docker-compose.yml
```

## Architecture rules (do not drift)

- **Async via queue.** `POST /api/summaries` validates, inserts a row with
  `status=pending`, dispatches `ProcessSummaryJob`, returns **202**. The worker does
  extract → summarize → persist, moving status `pending→processing→completed|failed`.
  Job state lives on `summaries.status` — no separate jobs table.
- **Polling, not WebSockets/SSE.** UI polls `GET /api/summaries/{id}` while pending.
- **Provider-agnostic LLM.** Never call a vendor SDK directly from the job. Go through
  the `LlmClient` interface + per-vendor adapters, selected by `LLM_PROVIDER`. Prompt
  building + style logic (`tldr`/`bullets`/`short`) live once in `SummarizerService`;
  adapters only translate to/from vendor wire formats and return `{text, model,
  inputTokens, outputTokens}`.
- **Endpoints follow `docs/03-openapi.yaml`.** Update the spec when the API changes.

## Hard constraints (security & cost — non-negotiable)

- **SSRF:** URLs are fetched server-side only. Allow `http`/`https` only; block private
  & link-local ranges (incl. `169.254.169.254`); enforce timeout, max response size, and
  redirect re-validation. See `docs/02-technical-design.md` §7.
- **Secrets:** `ANTHROPIC_API_KEY` / DB creds are server-side only — never in the Next.js
  bundle or any client code. Local: `.env`. Prod: Secrets Manager / SSM. Never commit secrets.
- **Cost guardrails:** default model Haiku 4.5; cap output via `max_tokens` per style;
  truncate input to `MAX_INPUT_TOKENS`; rate-limit per user (`429` when exceeded).
- **Anthropic API ≠ Claude Pro.** The API needs a Console key with its own billing; a
  claude.ai Pro subscription does not grant API access. New Console accounts get ~$5
  starter credits — plenty for this project on Haiku.

## Conventions

- TypeScript on the frontend; typed API client generated/derived from the OpenAPI spec.
- Laravel: Form Requests for validation, API Resources for responses, service classes for
  business logic (`SummarizerService`, content extractor, `LlmClient` adapters).
- Keep the API response shape consistent with the OpenAPI schemas (incl. the `Error` shape).
- Don't introduce new dependencies without noting why.

## Common commands

```bash
docker compose up -d                                  # start api + postgres
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed
docker compose exec api php artisan queue:work        # run the worker (separate process)
docker compose exec api php artisan test              # backend tests

cd web && npm install && npm run dev                  # frontend
cd web && npm run lint && npm run build               # checks
```

## Definition of done (per change)

- Matches the OpenAPI contract and the architecture rules above.
- Validation + error handling in place; no secrets in client code or commits.
- For LLM/queue work: status transitions correct, token usage + `cost_usd` recorded.
- Stays within MVP scope (`docs/01-product-scope.md` §2–§3) — flag scope creep, don't absorb it.
