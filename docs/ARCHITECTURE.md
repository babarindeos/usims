# Architecture

USIMS is one Laravel application split into domain modules, plus one React SPA. The full Phase 0
review (decisions, ERD, workflows, risks) is in the project's architecture document; this file
records what is built.

## Modules (backend/app/Modules)

| Module | Owns | Status |
| --- | --- | --- |
| Core | module wiring, shared contracts (`BelongsToOrgUnit`), UUID trait, error format | M1 |
| Identity | persons, identifiers, users, roles, permissions, role grants, sign-in | M1 |
| Institution | org-unit tree, sessions, semesters, calendar, settings and branding | M1 |
| Audit | append-only audit log, access log, request audit context | M1 |
| Workflow | definitions, stages, instances, actions; the approval engine | M1 |
| Notifications | templates, in-app notifications, mail deliveries, preferences | M1 |
| Programmes, Curriculum, Offerings, Admissions, Registry, Finance, Registration, Results, Staff, Graduation, Integrations, Reporting | later milestones | planned |

Each module holds `Models`, `Services`, `Http/{Controllers,Requests,Resources}`, `Events`,
`Database/Migrations` and a `routes.php` mounted under `/api/v1` by
`Core/Providers/ModuleServiceProvider`.

## Identity

- `persons` is the single permanent record of a human (`PER-000123`). Applicant, student, staff and
  alumni records will point to it.
- `person_identifiers` stores every human-readable number with issue and supersede dates; one
  active value per type, enforced by a partial unique index.
- `IdentifierService::next()` hands out sequence numbers under a row lock, so concurrent requests
  never get the same number (used for person, workflow and later matric numbers).
- New persons are checked for possible duplicates (email, phone, or names plus date of birth). The
  API answers `409` with the matches until the officer confirms.

## Access control

A grant is user + role + scope (`global` or one org unit) + validity window, never deleted
(revoked grants are end-dated). `AccessService::allows($user, 'permission', $target)` returns true
when an effective grant's role has the permission and the grant's unit path is a prefix of the
target's unit path. Org units store a materialised path (`/1/4/17/`), so "this department and
everything below it" is a string-prefix check, and moving a unit re-paths its subtree.

`Gate::before` routes every ability shaped like `module.action` to `AccessService`, so controllers
call `Gate::authorize('org_units.manage', $unit)`. Records implement `BelongsToOrgUnit` to say which
unit owns them. Suspended users hold no permissions. Nobody can grant a role to themselves, and the
System Administrator role carries no academic or financial approval permissions by default.

## Workflow engine

- A definition belongs to a process (`course_registration`, `fee_structure_approval` ...), has
  optional conditions (`{"department": "CSC"}`) and ordered stages. The most specific matching
  active definition is used.
- Each stage names the permission an approver needs; it is checked against the subject record's
  org unit, so "HOD of this department" is a scoped grant, not code.
- Actions: approve, reject and return (reason required), resubmit and cancel (initiator only).
- Maker-checker: the initiator cannot approve their own submission unless the definition allows it.
- One open instance per record. Every action is stored with user, role, remarks and a reference.
- `WorkflowCompleted` is dispatched on approval or rejection; modules listen for their process.

## Audit

Models with the `Auditable` trait write `created`, `updated` (only changed fields, before and after)
and `deleted` rows. The API reads an optional `X-Audit-Reason` header into the row. Passwords and
tokens are never written. A PostgreSQL trigger rejects any update or delete on `audit_logs`.
Viewing a person record writes an `access_logs` row.

## API conventions

- Versioned paths `/api/v1/...`, JSON, UUIDs for records exposed outside the system.
- Errors use RFC 9457 problem details: `{type, title, status, detail, request_id, errors?}` with
  `Content-Type: application/problem+json`. Every response carries `X-Request-Id`.
- SPA authentication is a Sanctum session cookie with CSRF protection; sign-in is rate limited
  (5 per minute per email and IP) and every attempt is logged.

## Front end (frontend/src)

- `lib/api.ts` — fetch client (cookies, XSRF, audit reason header, problem details to `ApiError`)
- `app/` — auth context (`can()` drives navigation only), branding, navigation
- `design-system/` — tokens in `index.css`, form fields, buttons, cards, data table, badges
- `layout/AppShell.tsx` — sidebar built from the user's permissions, responsive to phone width
- `modules/<area>/` — pages per backend module

## Integration pattern (from M4)

Outbound integrations (payment gateways, Moodle, SMS) will write an outbox row in the same
transaction as the business change; a queued job delivers it idempotently with retries. No
integration is simulated: unconfigured channels are recorded as skipped (see SMS in
`NotificationService`).
