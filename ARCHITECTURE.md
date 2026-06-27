# Architecture — PulseDesk

## Overview

PulseDesk is a multi-tenant support-desk SaaS. Every organization (tenant)
has its own users, tickets, SLA policies, tags, and activity log, fully
isolated from every other organization.

```
React (Vite, Tailwind)  ──HTTP/JSON──▶  Laravel 11 REST API  ──▶  MySQL 8
        │                                      │
   Bearer token (Sanctum)              Organization-scoped
   from /auth/login|register           via global Eloquent scope
```

## Data model

```
organizations
  id, name, slug

users
  id, organization_id, name, email, role(admin|agent|customer), password

tickets
  id, organization_id, subject, description,
  status(open|pending|resolved|closed), priority(low|medium|high|urgent),
  requester_id -> users, assignee_id -> users (nullable),
  response_due_at, resolution_due_at, first_responded_at, resolved_at

comments
  id, organization_id, ticket_id, user_id, body, is_internal

tags / ticket_tag (many-to-many)
  id, organization_id, name, color

sla_policies
  id, organization_id, priority, response_minutes, resolution_minutes
  (unique per organization+priority)

activity_logs
  id, organization_id, ticket_id, user_id, action, meta(json)

app_notifications
  id, organization_id, user_id, ticket_id, type, message, read_at
```

Relationships: an organization has many users and tickets; a ticket
belongs to a requester and (optionally) an assignee, has many comments,
many tags (through `ticket_tag`), and many activity log entries.

## Multi-tenancy approach

Tenant isolation is enforced at three layers so a user from Org A can
never see Org B's data, even if a client sends a foreign id:

1. **`TenantContext`** (`app/Support/TenantContext.php`) — a static holder
   for the current organization id, set once per request by
   `SetTenant` middleware **from the authenticated user**, never from a
   header, query param, or request body.
2. **`OrganizationScope`** (`app/Scopes/OrganizationScope.php`) — a global
   Eloquent scope applied to every tenant-owned model via the
   `BelongsToOrganization` trait. Every query is automatically constrained
   to `organization_id = TenantContext::id()`. A cross-tenant `show`
   request 404s (record genuinely not found in scope) rather than 403ing
   — it never leaks that the record exists elsewhere.
3. **Policies** (`TicketPolicy`, `CommentPolicy`) — defense in depth.
   Even though the global scope already filters by org, policies re-assert
   `organization_id` matches and apply role rules (customers only see/edit
   their own tickets; only staff can change status/priority/assignee or
   write internal notes).

On create, `BelongsToOrganization::bootBelongsToOrganization()` stamps
`organization_id` from `TenantContext`, so it's never settable by the
client.

## API routes (`routes/api.php`, prefix `/api/v1`)

| Method | Route | Purpose |
|---|---|---|
| POST | `/auth/register` | Create org (admin) or join existing org by slug (customer) |
| POST | `/auth/login` | Issue Sanctum token |
| GET | `/auth/me` | Current user + organization |
| POST | `/auth/logout` | Revoke current token |
| GET | `/tickets` | List, filtered by status/priority/assignee/tag/search, paginated |
| POST | `/tickets` | Create ticket (customers always become the requester) |
| GET | `/tickets/{id}` | Ticket + requester/assignee/tags/comments/activity |
| PATCH | `/tickets/{id}` | Update fields; staff-only fields gated server-side |
| POST | `/tickets/{id}/assign` | Assign/unassign (staff only) |
| POST | `/tickets/{id}/claim` | Self-assign an unassigned ticket |
| DELETE | `/tickets/{id}` | Delete (admin only) |
| POST | `/tickets/{id}/comments` | Public reply or internal note |
| GET/POST | `/tags` | List / create tags |
| GET | `/users`, `/agents` | Staff directory for assignee dropdowns |
| GET/PATCH | `/sla-policies` | View / edit per-priority targets (admin edits) |
| GET | `/dashboard/metrics` | Aggregate counts, SLA breach rate, 14-day series |
| GET/POST | `/notifications` | In-app notification centre |

## SLA mechanics

`SlaService::applyTo($ticket)` reads the org's `SlaPolicy` for the
ticket's priority and stamps `response_due_at` / `resolution_due_at` at
creation and whenever priority changes. `Ticket::getSlaResponseAttribute()`
/ `getSlaResolutionAttribute()` compute a live `{due_at, met, breached,
minutes_remaining}` object on every read — this is what powers the
"time remaining / breached" indicator in the UI without a background job.

## Frontend structure

```
frontend/src/
  components/   Layout (sidebar/topbar), ProtectedRoute, ui.jsx (badges, avatar, SLA pill)
  context/      AuthContext (token storage, login/register/logout, /auth/me on load)
  lib/          api.js (axios instance, bearer header, 401 redirect), format.js
  pages/        Login, Register, Tickets (list+detail split view, reused for
                all/mine/unassigned via a `scope` prop), Dashboard, SlaSettings
  styles/       app.css (component styles, CSS custom properties for theme)
```

`Tickets.jsx` is one component parameterized by `scope` (`all` / `mine` /
`unassigned`) rather than three near-duplicate pages, since the list +
detail interaction is identical and only the base query and an optional
banner differ.

## Key decisions

- **Why a global scope over manual `where()` calls everywhere?** A missed
  `where('organization_id', ...)` is the single most common multi-tenant
  bug. A global scope makes the safe behavior the default; you'd have to
  explicitly opt out (`withoutGlobalScopes()`, used only in `login()` to
  look up a user by email before we know their tenant) to leak data.
- **Why 404 instead of 403 on cross-tenant access?** Returning 403 confirms
  the record exists in another tenant. The scope hides it from route-model
  binding entirely, so it 404s like it was never there.
- **Why compute SLA state on read instead of a cron job?** The dataset is
  small enough (ticket-scoped) that computing `breached`/`minutes_remaining`
  on every fetch is cheap and always accurate, with no stale-cache risk.
- **Why MySQL for the real build but SQLite for tests?** `phpunit.xml` runs
  the test suite against an in-memory SQLite database for speed and
  isolation — standard Laravel practice. The actual application (local
  dev, CI integration job, and judging) runs MySQL 8, per the required
  stack.
