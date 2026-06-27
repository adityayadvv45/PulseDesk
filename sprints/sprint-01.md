# Sprint 01

**Goal:** Land the Must-tier core — multi-tenancy, auth, ticket CRUD,
conversation threading, list/filter/search, seeded demo data.

## Backlog (issues Hermes assigned to OpenClaw)

- [ ] Scaffold Laravel 11 + Sanctum auth, organizations/users schema
- [ ] Ticket model + migration (status, priority, requester, assignee)
- [ ] Tenant isolation: `organization_id` scope on every query
- [ ] Ticket CRUD API (`/api/v1/tickets`)
- [ ] Comments (public reply vs internal note)
- [ ] Seeder: 2 orgs, admins, agents, customers, ~12+6 tickets
- [ ] React scaffold + auth pages (login/register)
- [ ] Ticket list + detail view wired to the API

## Outcome

_Fill in after the sprint: what shipped, what's left, what slipped._

## Notes

_Real notes from the actual run go here — model routing decisions, blockers,
anything Hermes or OpenClaw flagged in Slack._
