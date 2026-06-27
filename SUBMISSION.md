# Submission Checklist

Per the handbook: everything lives in this repo. No Google Drive, Loom,
YouTube, or external links — if it isn't committed here, it isn't counted.

## Repo & process

- [x] Public GitHub repo named `forge2-<yourname>`
- [x] Fresh empty Laravel + React scaffold at sprint start (no pre-built app)
- [ ] Incremental commits across the sprint (no single giant commit) — **commit as you go, don't squash at the end**
- [ ] All agent model calls went through EastRouter (no other paid APIs mixed in)

## Required stack

- [x] PHP 8.2 / Laravel 11 — [`backend/composer.json`](./backend/composer.json)
- [x] MySQL 8 (no SQLite for the final build) — [`backend/.env.example`](./backend/.env.example)
- [x] React 19 + Vite + Tailwind — [`frontend/package.json`](./frontend/package.json)
- [x] Pest/PHPUnit feature tests — [`backend/tests/Feature/`](./backend/tests/Feature/)
- [x] GitHub Actions CI (install, migrate, test on every PR) — [`.github/workflows/ci.yml`](./.github/workflows/ci.yml)

## Agent architecture

- [x] Hermes config (real, secrets redacted) — [`agents/hermes/hermes-config.yaml`](./agents/hermes/hermes-config.yaml)
- [x] OpenClaw config (real, secrets redacted) — [`agents/openclaw/openclaw.json`](./agents/openclaw/openclaw.json)
- [ ] Proof the model is actually used (log/screenshot of an EastRouter model id being hit while OpenClaw answers) — [`evidence/screenshots/`](./evidence/screenshots/)
- [ ] Any custom skill given to an agent — [`agents/skills/`](./agents/skills/)

## The agent loop

- [ ] `agent-log.md` — real human → Hermes → OpenClaw transcript, in order — [`agent-log.md`](./agent-log.md) *(currently a template — fill with your real Slack transcript)*
- [ ] At least 2 real sprints, each with a saved sprint doc, a genuine Hermes→OpenClaw handoff in Slack, a PR you merged, and an OpenClaw report in `#agent-log` — [`sprints/sprint-01.md`](./sprints/sprint-01.md), [`sprints/sprint-02.md`](./sprints/sprint-02.md) *(templates — fill in Outcome/Notes after running)*
- [ ] Human-in-the-loop: you merged every PR to `main` yourself; no bot auto-merged

## Required Slack channels (everything in the open)

- [ ] `#sprint-main` — you ↔ Hermes
- [ ] `#agent-coder` — Hermes ↔ OpenClaw
- [ ] `#agent-log` — OpenClaw's structured reports
- [ ] `#ci-cd` — build/test results
- [ ] `#human-review` — release-candidate approvals

## Evidence — all committed in-repo

- [ ] Slack proof — real export at [`slack-export/`](./slack-export/) **or** per-channel screenshots at [`slack-export/screenshots/`](./slack-export/screenshots/) (`sprint-main-01.png`, `agent-coder-01.png`, `agent-log-01.png`, `ci-cd-01.png`, `human-review-01.png`)
- [ ] App + agents-running + CI screenshots — [`evidence/screenshots/`](./evidence/screenshots/) (e.g. `01-ticket-list.png`, `02-ticket-detail.png`, `03-dashboard.png`, `04-openclaw-gateway.png`, `05-ci-green.png`)
- [x] Architecture diagram (data model, API routes, tenancy approach) — [`ARCHITECTURE.md`](./ARCHITECTURE.md)
- [N/A] Demo video — **none required this year**; live in-person demo instead

## Security

- [x] No real secrets committed — agent configs use `${ENV_VAR}` placeholders
- [x] `.env.example` files only; real `.env` is gitignored in both `backend/` and `frontend/`

## Before you submit

- [ ] `composer install && php artisan migrate --seed` runs clean from a fresh clone
- [ ] `npm install && npm run build` runs clean from a fresh clone
- [ ] `php artisan test` passes
- [ ] CI workflow shows green on the Actions tab
- [ ] Confirm the box on the submission form: *"I confirm this is my own work
  built during the event, my repo is public, all my evidence is committed
  inside the repo, and all model calls went through EastRouter."*
