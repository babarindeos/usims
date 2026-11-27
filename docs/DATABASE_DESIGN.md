# Database design

PostgreSQL 16. Bigint primary keys internally, UUIDs on records exposed through the API, foreign
keys on every relation. Migrations live with their module in `backend/app/Modules/*/Database/Migrations`.

## History strategy

| Pattern | Used for |
| --- | --- |
| Effective dates (`starts_at`/`ends_at`, `effective_from`/`effective_to`) | role grants, org units; later programme membership, staff assignments |
| Supersede, never overwrite | `person_identifiers` (matric number corrections keep the old value) |
| Append-only rows | `audit_logs` (enforced by trigger), `workflow_actions`, `login_histories`, `access_logs` |
| Immutable published versions | later: curricula, fee structures, grading scales |

JSON columns hold only configuration (`workflow_definitions.conditions`, `settings.value`) and audit
snapshots.

## Tables built in M1

### Identity

| Table | Purpose | Key constraints |
| --- | --- | --- |
| persons | one row per human for life | `person_no` unique; lower(email) and phone unique among unmerged rows (partial indexes); `merged_into_person_id` for duplicate merges |
| person_identifiers | PER, APP, STU, MATRIC, STAFF, ALU, JAMB numbers with history | unique (type, value); one active per (person, type) via partial unique index |
| identifier_sequences | counters for generated numbers | unique key; incremented under `SELECT ... FOR UPDATE` |
| users | sign-in accounts linked to a person | unique person_id, email, oidc_subject; status active/suspended/disabled |
| login_histories | every sign-in attempt | indexed (user_id, created_at) |
| permissions | catalogue from `config/permissions.php` | unique code |
| roles, permission_role | named permission sets | unique code |
| role_grants | user + role + scope + window | CHECK global has no unit, org_unit has one; revoked rows kept |

### Institution

| Table | Purpose | Key constraints |
| --- | --- | --- |
| org_unit_types | university, college, faculty, department, directorate ... | unique code |
| org_units | the configurable hierarchy | materialised `path` with `varchar_pattern_ops` index for prefix scope checks; code unique among active units |
| academic_sessions | e.g. 2026/2027 with six independent process statuses | one current session (partial unique index); ends_on > starts_on |
| semesters | periods inside a session with registration, add/drop, exam and result dates | unique (session, code) and (session, sequence) |
| calendar_events | dated events, university-wide or for one unit | indexed (session, starts_on) |
| settings | branding and other institution settings | unique key |

### Audit, workflow, notifications

| Table | Purpose |
| --- | --- |
| audit_logs | actor, action, record, before/after, reason, IP, request id; trigger blocks UPDATE and DELETE |
| access_logs | reads of sensitive records |
| workflow_definitions, workflow_stages | configurable approval chains; stage carries the approver permission |
| workflow_instances | one per submission; reference `WF-YYYY-NNNNNN`; status in_progress/returned/approved/rejected/cancelled |
| workflow_actions | every decision with user, role, remarks, from/to status, unique reference |
| notification_templates | per event code and channel, with `{{ placeholders }}` |
| user_notifications | in-app notifications |
| notification_deliveries | mail and SMS delivery log with attempts and errors |
| notification_preferences | opt-outs for non-mandatory notifications |

## Planned (later milestones)

The lifecycle spine (programmes, curriculum versions, course offerings, applications, students,
invoices, payments, registrations, results, Moodle mappings, outbox) is specified in the
architecture review and is added module by module, each with its migration and this file updated.
