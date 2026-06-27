# PulseDesk — Forge 2 · Edition 1

A multi-tenant support-desk SaaS, built solo in one day by orchestrating
two AI agents — **Hermes** (orchestrator / product owner) and **OpenClaw**
(coder) — over Slack, following the agile-with-bots loop in
[`agent-log.md`](./agent-log.md).

> **What it is.** Think a focused mini-Zendesk: organizations (tenants) run
> their own help desk, with admins/agents managing tickets raised by
> customers, full conversation threading (public replies vs internal
> notes), SLA timers, and dashboard metrics.

## Stack

| Layer | Tech |
|---|---|
| Backend | PHP 8.2, Laravel 11 (REST API), Laravel Sanctum (auth) |
| Database | MySQL 8 (migrations + seeders) |
| Frontend | React 19 + Vite, Tailwind CSS |
| Tests | Pest / PHPUnit (feature tests on tenancy + ticket flows) |
| CI | GitHub Actions — installs, migrates, runs tests on every PR |
| Agents | Hermes (orchestrator) + OpenClaw (coder), via EastRouter |

## Models used (via EastRouter)

| Role | Model | Why |
|---|---|---|
| Hermes — planning / architecture | `deepseek/deepseek-v4-pro` | Strong reasoning for sprint planning and cross-dependency calls |
| OpenClaw — implementation | `z-ai/glm-5.1` | Recommended default — strong all-round coding + planning, 200K context |
| Fallback | `z-ai/glm-4.5-air` | Cheap, fast iterations for small repetitive edits |

All model calls went through EastRouter (`https://api.eastrouter.com`) —
see [`agents/hermes/hermes-config.yaml`](./agents/hermes/hermes-config.yaml)
and [`agents/openclaw/openclaw.json`](./agents/openclaw/openclaw.json) for
the real (secret-redacted) configs.

## Run it from a fresh clone

### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate

# Create a MySQL database matching .env (default: `pulsedesk`), then:
php artisan migrate --seed

php artisan serve   # http://127.0.0.1:8000
```

### Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev          # http://localhost:5173, proxies /api to :8000
```

### Tests

```bash
cd backend
php artisan test
```

### Seeded demo data

The seeder creates **two organizations** (to prove tenant isolation),
each with an admin, 2 agents, 2 customers, and a spread of tickets across
every status/priority:

| Organization | Slug | Admin login | Password |
|---|---|---|---|
| Acme Support (primary, 12 tickets) | `acme` | `admin@acme.test` | `password` |
| Globex Helpdesk (secondary, 6 tickets) | `globex` | `admin@globex.test` | `password` |

Agent/customer accounts follow the same password and an email derived
from their first name (e.g. `sam@acme.test`) — see
[`database/seeders/DatabaseSeeder.php`](./backend/database/seeders/DatabaseSeeder.php)
for the full list.

## Feature tier reached

- ✅ **Must** — multi-tenancy, auth & roles, ticket CRUD, threaded replies
  (public/internal), list/filter/search, REST API + React frontend, seeded
  demo data.
- ✅ **Should** — SLA policies & live breach timers, queues & claim flow,
  activity log / audit trail, dashboard metrics, in-app notifications.
- ⬜ **Stretch** — not attempted this round (canned responses, ticket
  merge, CSAT, customer portal, real-time updates, full-text search, CSV
  export).

## Repo layout

See [`ARCHITECTURE.md`](./ARCHITECTURE.md) for the data model, API routes,
and key decisions, and [`SUBMISSION.md`](./SUBMISSION.md) for the
submission checklist with in-repo evidence paths.

## Judgment calls

- Built a fresh empty Laravel + React scaffold at sprint start, per the
  handbook ("don't bring a pre-built app") — features were implemented
  live during the sprint loop documented in `agent-log.md`.
- Chose to model SLA state as a computed attribute (`Ticket::sla_response`
  / `sla_resolution`) rather than a scheduled job, since it's always
  accurate on read and the dataset is small — see `ARCHITECTURE.md` for
  the full rationale.
- Customers can edit their own ticket's subject/description but not
  status/priority/assignee — only staff can do that. This wasn't fully
  spelled out in the brief; treated it as the sensible default for a
  support desk.
