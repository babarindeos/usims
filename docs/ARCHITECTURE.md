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
| Programmes | award types, study modes, programme categories, programmes | M2 |
| Curriculum | courses, requisites, curriculum versions, elective groups; curriculum approval | M2 |
| Offerings | course offerings, cohort links, staff assignments | M2 |
| Staff | minimal staff record linked to the person master (HR extends it in M6) | M2 |
| Admissions | admission configuration, exercises, applications, screening, payments recorded for verification, admission lists, offers, onboarding, clearance | M3 |
| Registry | matric number formats, students, student status history; matriculation | M3 |
| Finance, Registration, Results, Graduation, Integrations, Reporting | later milestones | planned |

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

## Academic setup (M2)

- Curriculum versions move draft → in_review → approved → published → retired (or returned,
  rejected). `CurriculumService::submit` starts the `curriculum_approval` workflow; the
  `SyncCurriculumWithWorkflow` listener maps every `WorkflowStageChanged` back onto the version.
  Stages and approvers are data in `workflow_definitions`, seeded with HOD then Academic Planning.
- Edit rules are enforced twice: the service refuses changes outside draft/returned, and a database
  trigger refuses them at the table level.
- Offerings are generated from published curricula for the semester's sequence. Generation is
  scoped per course: a user only creates offerings for departments they manage, and the result
  says how many were left for others.
- Staff eligibility and LMS short names come from settings, not code. Staff status changes raise
  `StaffStatusChanged`; `FlagAssignmentsOnStaffChange` (Offerings) flags live assignments rather than
  ending them, so a person decides who takes over. `OfferingPublished`, `StaffAssigned` and
  `StaffAssignmentEnded` are the hooks the Moodle integration will use in M5.

## Admissions to matriculation (M3)

- Applicants register themselves (`/applicant/register`), verify their email through a signed link
  (redirected to `FRONTEND_URL/login?verified=1`) and then hold only the `applicant` role. They reach
  their own applications only; every other id returns 404. Applicant-only accounts get their own
  navigation in the SPA.
- An exercise fixes session, category, window, fees, acceptance days, programmes with capacities and
  screening rules, and the documents required at application and at onboarding. Status: draft,
  scheduled, open, suspended, closed; `effectiveStatus()` also honours the dates.
- `ScreeningEngine` evaluates the rule keys stored per exercise programme (minimum UTME score,
  required UTME subjects, O'Level credit subjects, credit count, sittings, verified documents, manual
  review). Each check passes, fails, or is pending; the outcome is `eligible`, `ineligible`,
  `pending_verification` or `manual_review`. Screening runs on submission (`ScreenOnSubmission`)
  and on request, and every run is kept in `screening_results`.
- Fees: no gateway is configured yet, so candidates record a bank payment with evidence and Bursary
  verifies it. Nothing is simulated; M4 gateways will settle the same `admission_payments` rows.
- Recommended applicants go on an admission list. `AdmissionListService::submit` checks capacity and
  starts the `admission_approval` workflow (committee recommendation, then approval; editable as
  data). `AdmissionWorkflowListener` syncs the list and, on approval, `OfferService` issues offers
  with letters built from the `admission_letter_template` setting. Issued letters are stored and do
  not change. Offers lapse after the acceptance window (`admissions:expire-offers`, daily).
- After acceptance and onboarding, one clearance item is created per active clearance unit,
  routed to a fixed office or to the candidate's department or faculty. When all are approved,
  `ClearanceCompleted` fires; Registry matriculates in bulk, or automatically when the
  `auto_matriculate` setting is true.
- `MatriculationService` refuses offers with unmet conditions, creates the student, issues STU and
  MATRIC identifiers, attaches the curriculum in force, and starts the append-only status history.
  `MatricNumberService` builds numbers from the most specific active format; tokens are
  `{YEAR} {YY} {DEPT} {FAC} {PROG} {CAT} {LEVEL} {SEQ:n}` and each prefix has its own row-locked
  sequence.

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
