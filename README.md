# AI Summarizer

Turn a long article (by URL) or pasted text into a concise summary using the Anthropic API.
Summaries are generated **asynchronously** via AWS SQS + a Laravel queue worker, stored in
PostgreSQL, and surfaced through a Next.js UI with a per-user history and an admin panel.

- **Frontend + admin:** Next.js (App Router) + TypeScript
- **Backend API:** Laravel + Sanctum
- **Queue:** AWS SQS (+ DLQ), worker via `php artisan queue:work sqs`
- **DB:** PostgreSQL (RDS in prod)
- **LLM:** Anthropic — Claude Haiku 4.5 (`claude-haiku-4-5-20251001`)

See `docs/` for the full spec:
- `01-product-scope.md` — scope, user stories, requirements
- `02-technical-design.md` — architecture, data flow, ERD, AWS, security, prompts
- `03-openapi.yaml` — API contract
- `04-task-breakdown.md` — 3-day plan

## Prerequisites

- Docker + Docker Compose
- Node.js 20+ and PHP 8.3 (if running outside containers)
- An **Anthropic API key** from console.anthropic.com with billing enabled.
  > A Claude Pro subscription on claude.ai does **not** include API access — the API is
  > billed separately, per token. Set a low spend limit in the Console for development.

## Environment variables

Backend (`api/.env`):

```
APP_KEY=                      # php artisan key:generate
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=summarizer
DB_USERNAME=summarizer
DB_PASSWORD=secret

# Queue: 'database' (local) or 'sqs' (prod)
QUEUE_CONNECTION=database
# When using SQS:
# QUEUE_CONNECTION=sqs
# AWS_ACCESS_KEY_ID=...        # prefer IAM task roles in prod (no static keys)
# AWS_SECRET_ACCESS_KEY=...
# AWS_DEFAULT_REGION=eu-central-1
# SQS_PREFIX=https://sqs.eu-central-1.amazonaws.com/<account-id>
# SQS_QUEUE=summaries

# LLM (provider-agnostic client)
LLM_PROVIDER=anthropic         # anthropic | fake | gemini | ...
                               # 'fake' returns canned summaries — runs the full
                               # async pipeline locally with NO API key or cost.
ANTHROPIC_API_KEY=             # from Anthropic Console (NOT a claude.ai Pro account)
ANTHROPIC_MODEL=claude-haiku-4-5-20251001
# Optional free-tier fallback:
# GEMINI_API_KEY=...
# GEMINI_MODEL=gemini-2.5-flash

# Guardrails
MAX_INPUT_TOKENS=12000
RATE_LIMIT_PER_HOUR=20
FETCH_TIMEOUT_SECONDS=10
FETCH_MAX_BYTES=2000000
```

Frontend (`web/.env.local`):

```
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000
```

> Never put the Anthropic API key in the frontend. It is server-side only.

## Local development

```bash
# 1. Start api + postgres + worker (the worker runs `queue:work` as its own service)
cp api/.env.example api/.env          # then set APP_KEY (step 2) and any secrets
docker compose up -d --build

# 2. Backend setup (deps are baked into the image; key+migrate are still needed)
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed

# 3. Frontend
cp web/.env.local.example web/.env.local
cd web && npm install && npm run dev
```

App: http://localhost:3000 · API: http://localhost:8000

> The `worker` container runs `php artisan queue:work` automatically. After changing
> `api/.env`, recreate the containers (`docker compose up -d --force-recreate`) so the
> new env is picked up — `restart` alone keeps the old env. To run a worker manually
> instead, use `docker compose exec api php artisan queue:work`.

Seeded logins: `admin@example.com` / `password` (admin) and `test@example.com` / `password`.
With `LLM_PROVIDER=fake` (the keyless default for local dev) summaries complete instantly
with canned text; set `LLM_PROVIDER=anthropic` + `ANTHROPIC_API_KEY` for real summaries.

## Production (AWS) — high level

API and Worker run as two ECS Fargate services (the worker runs `queue:work sqs`),
PostgreSQL on RDS, SQS standard queue + DLQ, secrets in Secrets Manager / SSM, frontend
on Amplify Hosting. See `docs/02-technical-design.md` §8 for the full layout and IAM notes.
A quicker alternative for a demo: a single Lightsail/EC2 box with `supervisor` managing the
worker, still using real SQS + RDS.
