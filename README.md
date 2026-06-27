# PulseDesk — Forge 2 · Edition 1

A multi-tenant support-desk SaaS built solo in one day by orchestrating two AI agents —
**Hermes** (orchestrator / product owner) and **OpenClaw** (coder) — over Slack, following
the agile-with-bots loop documented in [`agent-log.md`](./agent-log.md).

**Live demo:** https://pulsedeskk.netlify.app
**API (backend):** https://pulsedeskk.onrender.com

> Think a focused mini-Zendesk. Organizations (tenants) run their own help desk —
> admins and agents manage tickets raised by customers, with full conversation threading
> (public replies vs internal notes), SLA timers, activity logs, and dashboard metrics.
> A user from Org A can never see Org B's data.

---

## Stack

| Layer    | Tech                                                         |
|----------|--------------------------------------------------------------|
| Backend  | PHP 8.2 · Laravel 11 (REST API) · Laravel Sanctum (auth)    |
| Database | MySQL 8 — migrations + seeders                               |
| Frontend | React 19 + Vite · Tailwind CSS                               |
| Tests    | Pest / PHPUnit — feature tests on tenancy + ticket flows     |
| CI       | GitHub Actions — installs, migrates, runs tests on every PR  |
| Deploy   | Backend → Render (Docker) · Frontend → Netlify               |
| Agents   | Hermes (orchestrator) + OpenClaw (coder) via EastRouter      |

---

## Models used (via EastRouter)

| Role                              | Model                       | Why                                              |
|-----------------------------------|-----------------------------|--------------------------------------------------|
| Hermes — planning / architecture  | `deepseek/deepseek-v4-pro`  | Strong reasoning for sprint planning (1M ctx)    |
| OpenClaw — implementation         | `z-ai/glm-5.1`              | Strong all-round coding + planning (200K ctx)    |
| Fallback / small edits            | `z-ai/glm-4.5-air`          | Cheap, fast iterations for repetitive changes    |

All model calls went through EastRouter (`https://api.eastrouter.com`).
See [`agents/hermes/hermes-config.yaml`](./agents/hermes/hermes-config.yaml) and
[`agents/openclaw/openclaw.json`](./agents/openclaw/openclaw.json) for the real
(secrets redacted) configs.

---

## Run it from a fresh clone

### Prerequisites

- PHP 8.2+, Composer
- Node 18+, npm
- MySQL 8 running locally

### Backend

```bash
cd backend
cp .env.example .env          # fill in DB_* and APP_KEY
composer install
php artisan key:generate
php artisan migrate --seed    # creates 2 orgs, seeded tickets
php artisan serve             # http://127.0.0.1:8000
```

### Frontend

```bash
cd frontend
cp .env.example .env          # set VITE_API_URL=http://127.0.0.1:8000
npm install
npm run dev                   # http://localhost:5173
```

### Tests

```bash
cd backend
php artisan test
```

### Docker (same as Render)

```bash
docker build -t pulsedesk .
docker run -p 8080:8080 \
  -e DB_HOST=host.docker.internal \
  -e DB_DATABASE=pulsedesk \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=secret \
  pulsedesk
```

---

## Seeded demo accounts

The seeder creates **two organizations** to prove tenant isolation. Log in as either — Org A can never read Org B's data.

| Org                            | Role     | Email                 | Password   |
|--------------------------------|----------|-----------------------|------------|
| Acme Support (12 tickets)      | Admin    | `admin@acme.test`     | `password` |
| Acme Support                   | Agent    | `sam@acme.test`       | `password` |
| Acme Support                   | Customer | `alice@acme.test`     | `password` |
| Globex Helpdesk (6 tickets)    | Admin    | `admin@globex.test`   | `password` |
| Globex Helpdesk                | Agent    | `jay@globex.test`     | `password` |

---

## Feature tier reached

- ✅ **Must** — multi-tenancy (every query scoped by `organization_id`), auth & roles
  (admin / agent / customer via Sanctum), ticket CRUD with all fields, threaded replies
  (public vs internal note), list / filter / search, REST API + React frontend, seeded demo data.
- ✅ **Should** — SLA policies & live breach timers, queues & claim flow, activity log /
  audit trail per ticket, dashboard metrics (open by status/priority, avg first-response
  time, SLA-breach rate, tickets per day), in-app notifications.
- ⬜ **Stretch** — not attempted (canned responses, ticket merge, CSAT, customer portal,
  real-time updates, full-text search, CSV export).

---

## Repo layout

```
PulseDesk/
├── README.md                        # this file
├── ARCHITECTURE.md                  # data model, API routes, key decisions
├── SUBMISSION.md                    # submission checklist with evidence paths
├── agent-log.md                     # real human→Hermes→OpenClaw loop
├── Dockerfile                       # used by Render
├── docker/                          # nginx.conf + start.sh
├── backend/                         # Laravel 11 + MySQL
│   ├── app/Models/
│   ├── app/Http/Controllers/Api/
│   ├── app/Policies/
│   ├── database/migrations/
│   ├── database/seeders/
│   ├── routes/api.php
│   ├── tests/
│   └── .env.example
├── frontend/                        # React 19 + Vite + Tailwind
│   ├── src/
│   └── .env.example
├── agents/
│   ├── hermes/hermes-config.yaml    # real Hermes config (secrets redacted)
│   └── openclaw/openclaw.json       # real OpenClaw config (secrets redacted)
├── sprints/                         # sprint-01.md, sprint-02.md …
├── slack-export/                    # Slack proof (export or screenshots)
└── evidence/screenshots/            # app + agent + CI screenshots
```

See [`ARCHITECTURE.md`](./ARCHITECTURE.md) for the full data model, API route list,
multi-tenancy approach, and key decisions.

---

## Judgment calls

- Started from an empty Laravel + React scaffold at sprint start — no pre-built features
  brought in. Everything in `app/` was implemented during the sprint loop in `agent-log.md`.
- SLA state is a computed attribute on `Ticket` (`sla_response_status` / `sla_resolution_status`)
  rather than a scheduled job — always accurate on read, no cron dependency needed for the
  hackathon scope. Documented in `ARCHITECTURE.md`.
- Customers can edit their own ticket's subject/description but not status, priority, or
  assignee — only staff roles can do that. Not spelled out in the brief; treated as the
  sensible default for a support desk.
- Deployed backend to Render (Docker) and frontend to Netlify with `VITE_API_URL` pointed
  at the Render service. CORS configured in `config/cors.php` to allow the Netlify origin.
