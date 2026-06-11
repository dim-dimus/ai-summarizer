# AI Summarizer — Product & Scope

> Status: MVP spec · Timebox: 3 days · Owner: Dmytro

## 1. Vision

A web app that turns a long article (by URL) or pasted text into a concise summary
using the Anthropic API. Summaries are generated **asynchronously** (the request is
queued, processed by a worker, and the result is fetched when ready), and every
generation is stored in the user's history. An admin panel exposes all summaries,
users, and token/cost usage.

**Problem it solves:** reading long content is slow; users want a fast, reliable
TL;DR with a choice of format.

**Definition of success (for the demo):**

- User submits a URL or text and reliably gets a stored summary within ~30s.
- The async pipeline (API → SQS → worker → DB) works end to end on AWS.
- Token usage and cost are tracked per request and visible in the admin panel.
- Secrets (Anthropic API key, DB creds) live only server-side.

## 2. Scope (MVP)

In scope:

- Email/password auth (single user role + admin role).
- Create a summary from **either** a URL **or** pasted raw text.
- Choose a summary **style**: `tldr` (one paragraph), `bullets` (key points), `short` (3–4 sentences).
- Asynchronous processing via AWS SQS + a Laravel queue worker.
- Summary status lifecycle: `pending → processing → completed | failed`.
- Per-user history: list, view one, delete one.
- Admin panel: list all summaries, list users, aggregate token/cost stats.
- Token + cost accounting per summary.

## 3. Out of scope (explicit — protects the timebox)

- No team/organization accounts, sharing, or collaboration.
- No billing, payments, or subscription logic in the app.
- No multi-language UI (UI in EN; summaries follow the source language).
- No file uploads (PDF/DOCX) — URL or text only.
- No browser extension or public API for third parties.
- No re-run / versioning of a summary (delete + create again instead).
- No real-time token streaming to the UI (async/queue model uses polling for status).
- No advanced auth (OAuth, SSO, 2FA) — keep email/password only.

## 4. User stories

Authenticated user:

1. As a user I can register and log in so my summaries are private to me.
2. As a user I can submit a URL and get it summarized.
3. As a user I can paste raw text and get it summarized.
4. As a user I can choose the summary style before submitting.
5. As a user I can see the live status of a pending summary (polling).
6. As a user I can read a completed summary and see which model produced it.
7. As a user I can see a list of all my past summaries.
8. As a user I can delete a summary from my history.

Admin:

9. As an admin I can see every summary across all users.
10. As an admin I can see the list of users.
11. As an admin I can see total/aggregate token usage and estimated cost.

## 5. Functional requirements

- **FR-1** `POST /api/summaries` accepts `{ source_type, url | text, style }`, validates
  input, creates a record with status `pending`, dispatches an SQS job, returns `202`
  with the created resource (including `id` and `status`).
- **FR-2** A worker consumes the SQS message, sets status `processing`, extracts content
  (for URLs), calls the Anthropic API, stores the result + token usage, sets status
  `completed`. On any failure it sets status `failed` with an `error_message`.
- **FR-3** `GET /api/summaries/{id}` returns current status and, when completed, the result.
- **FR-4** `GET /api/summaries` returns the authenticated user's summaries (paginated).
- **FR-5** `DELETE /api/summaries/{id}` removes a summary owned by the user.
- **FR-6** Admin endpoints return all summaries, all users, and aggregate stats.
- **FR-7** URL inputs are fetched server-side only, with SSRF protection (see technical design §7).

## 6. Non-functional requirements

These matter because this is an AI + external-fetch app, not plain CRUD.

- **NFR-1 (Cost control):** default model is **Claude Haiku 4.5** (cheapest current model,
  good for summarization). Output is capped via `max_tokens`. Input is truncated/chunked
  to a hard token budget. See technical design §6.
- **NFR-2 (Rate limiting):** per-user request limit (e.g. 20 summaries/hour) to protect the
  API budget. Reject with `429` when exceeded.
- **NFR-3 (Input limits):** max raw text length and max fetched-page size enforced before
  any LLM call.
- **NFR-4 (Resilience):** Anthropic 429/5xx and URL-fetch failures are caught; the job retries
  a bounded number of times, then lands in a dead-letter queue (DLQ) and the summary is marked
  `failed`.
- **NFR-5 (Latency expectation):** typical end-to-end completion under ~30s; the UI shows a
  pending state and polls for status.
- **NFR-6 (Security):** Anthropic API key and DB credentials are never exposed to the frontend;
  they live in AWS Secrets Manager / SSM Parameter Store.
- **NFR-7 (Observability):** every summary stores `model`, `input_tokens`, `output_tokens`,
  and `cost_usd` for the admin stats view.

## 7. Pre-requisite the user must arrange

- An **Anthropic API key** from the Anthropic Console (console.anthropic.com) with billing
  enabled. **A Claude Pro subscription on claude.ai does NOT grant API access** — API usage
  is billed separately, usage-based, per token. Set a low spend limit in the Console for the demo.
