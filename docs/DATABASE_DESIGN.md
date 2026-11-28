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

## Tables built in M2

### Programmes
| Table | Purpose |
| --- | --- |
| award_types, study_modes, programme_categories | institution-defined lists; `code` fixed, `active` flag |
| programmes | code, name, owning org unit, award, category, normal/min/max duration (CHECK min ≤ normal ≤ max), capacity, accreditation, status |
| programme_study_modes | modes a programme runs in, optional duration override |
| programme_entry_levels | levels new students can enter at (100, 200 for direct entry, …) |

### Staff
| Table | Purpose |
| --- | --- |
| staff | one row per person employed: staff number, org unit, category, rank, employment status, source (`manual`, `demo`, later `hr`) |

### Curriculum
| Table | Purpose |
| --- | --- |
| courses | catalogue: code (`CSC 301`, unique), title, units, level, usual semester, owning department, type, practical hours, status |
| course_requisites | prerequisite or co-requisite pairs; CHECK not self; loops refused in `CourseService` |
| curriculum_versions | per programme, numbered; first entry session, rules, status (draft, in_review, returned, approved, published, retired, rejected), `based_on_id` |
| elective_groups | named groups with minimum and maximum units per level and semester |
| curriculum_courses | course placement in a version: level, semester, category, units copied at mapping time, optional elective group |

Trigger `curriculum_locked_guard` rejects inserts, updates and deletes on `curriculum_courses` and
`elective_groups` unless the parent version is `draft` or `returned`, so a submitted or published
curriculum cannot change even outside the application.

### Offerings
| Table | Purpose |
| --- | --- |
| course_offerings | a course in a semester and group (unique together); delivering unit, capacity, registration window override, delivery mode, status, LMS short name (unique, set on publish) |
| course_offering_programmes | programme cohorts (programme + level) taking the offering |
| course_offering_staff | staff roles on an offering with effective dates; ended rows keep `end_reason` and `ended_by`; `flagged_at` marks assignments to reassign. Partial unique index on active (offering, staff, role) |

Settings used: `teaching_eligible_categories` (default `["academic"]`), `lms_shortname_format`
(default `{code}-{session}-{semester}{group}`).

## Planned (later milestones)

The rest of the lifecycle spine (applications, students,
invoices, payments, registrations, results, Moodle mappings, outbox) is specified in the
architecture review and is added module by module, each with its migration and this file updated.
