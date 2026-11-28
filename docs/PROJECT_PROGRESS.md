# Project progress

Current milestone: **M3 Admissions to matriculation, in review** (M1 and M2 also awaiting merge). Next: M4 Fees and payments.

## Done

### M0 Setup
- Monorepo: `backend/` (Laravel 13, PostgreSQL), `frontend/` (React, TypeScript, Vite, Tailwind 4)
- `docker-compose.yml`: PostgreSQL, Redis, Mailpit, Keycloak (profile `sso`)
- CI: backend style and tests on PostgreSQL; frontend type check, lint, tests, build
- Design system tokens and components; responsive app shell

### M1 Core platform
- Person master with permanent numbers, identifier history, duplicate detection
- Users, session sign-in with rate limiting and login history, account suspension
- Roles, permission catalogue, scoped and time-boxed grants with revocation history
- Org-unit tree with materialised paths; moves re-path subtrees
- Academic sessions with six independent process statuses, one current session, closing locks
- Semesters inside session dates; academic calendar with unit-scoped events
- Branding settings applied to the SPA
- Append-only audit log with reasons, access log for person views
- Workflow engine: conditional definitions, scoped stages, maker-checker, return and resubmit
- Notification service: templates, in-app, queued mail with retries, preferences; SMS recorded as not configured
- Screens: sign-in, dashboard, structure, sessions, calendar, users and grants, roles, persons, approvals, audit trail, notifications, branding

### M2 Academic setup
- Award types, study modes and programme categories as institution-edited lists (codes fixed, entries deactivated not deleted)
- Programmes owned by a department: award, category, durations (normal, minimum, maximum), capacity, accreditation, study modes with per-mode durations, entry levels
- Course catalogue: normalised codes (`CSC 301`), credit units, level, usual semester, owning department, type, practical; prerequisites with loop detection and co-requisites
- Curriculum versions per programme: rules (required units, semester load), courses mapped by level and semester with categories, elective groups; new versions copy an earlier one
- Curriculum approval through the workflow engine (default: HOD endorsement, then Academic Planning approval; editable in workflow definitions). Versions are editable only while draft or returned; a PostgreSQL trigger rejects changes to a submitted version's courses or groups. Approved versions are published, then retired with a reason
- Minimal staff table (staff number, unit, category, rank, status) linked to the person master; status changes need a reason and raise `StaffStatusChanged`
- Course offerings per semester and group: generated from published curricula (only for departments the user manages), linked to programme cohorts, published with an LMS short name from the `lms_shortname_format` setting, cancelled with a reason
- Staff allocation by role with eligibility from the `teaching_eligible_categories` setting; staff who leave have their live assignments flagged for reassignment, and flags clear if they return
- New permissions and default role assignments; the permission seeder now adds new permissions to existing system roles without undoing institution edits
- Screens: programmes, programme detail with curriculum versions, course catalogue and detail with requisites, curriculum editor, offerings with generation, offering detail with cohorts and staff, staff records, academic configuration
- Demo data: 2026/2027 session with two semesters, B.Sc. Computer Science with a draft curriculum, six courses, four staff

### M3 Admissions to matriculation
- Configurable admission categories, O'Level bodies and grade scales, subjects, document types, clearance offices, matric number formats and the admission letter template
- Admission exercises: session, category, window, fees, capacity, acceptance window, instructions, programmes with entry levels, capacities and screening rules, required documents per stage; status changes need a reason
- Applicant self-registration with email verification; applicant portal for biodata, programme choices, next of kin, JAMB details, O'Level sittings, previous education, document uploads, bank payment recording, and submission with a list of anything missing
- Screening engine with per-programme rules; outcomes eligible, ineligible, awaiting verification, manual review; every run kept
- Processing workspace scoped to the officer's departments: filters, CSV export, document verification, rescreen, recommend, hold, reject, change programme, corrections window; every view written to the access log
- Bursary verification queue for application and acceptance fees (manual, against the bank statement; no gateway yet)
- Admission lists approved through the workflow engine (committee, then approving officer); approval issues offers with stored letters; offers expire after the acceptance window
- Acceptance with a declaration, acceptance fee, onboarding form and documents, clearance by each office with correction and resubmission
- Matriculation (bulk, or automatic when `auto_matriculate` is on): student and matric numbers, identifiers, curriculum in force, append-only status history; student records with status changes
- Approval pages link to the item being approved (admission list or curriculum)
- Demo data: open 2026/2027 UTME exercise for B.Sc. Computer Science, committee, management and clearance officers

## Tests
- Backend: 76 feature and unit tests (adds exercises, screening, admissions access scoping, and the full journey from registration to matriculation)
- Frontend: design-system, curriculum-editor and applicant-form tests; end-to-end browser walkthroughs run manually. M3: register, verify email, apply, Bursary verifies the fee, Registry verifies documents and recommends, list approved by committee and management, applicant accepts and pays, onboarding, four clearance offices approve, Registry matriculates (2026/CSC/0001)

## Known gaps carried to later milestones
- Keycloak OIDC sign-in and MFA enforcement (M5, with Moodle SSO); local passwords work now
- Workflow escalation and due-date reminders (scheduler job) not yet built
- Notification template editing screen not yet built (templates seeded)
- Org-unit edit and calendar edit screens: API done, UI covers create and list only
- Moodle course creation for published offerings waits for M5; `OfferingPublished` and staff assignment events are already raised for it
- Staff records are entered by hand until the HR module (M6) takes over; programme and course edit forms cover status changes, other edits are API-only for now
- Admissions: no matric-number preview screen yet; letter template is plain text; JAMB data is entered by the applicant (no JAMB/CAPS integration); password reset not yet built; the queue worker (`php artisan queue:work`) must run for verification emails to send

## Next tasks (M4)
1. Fee items and schedules by programme, level, category and session; invoices and student ledger
2. Payment gateway integration through the outbox pattern, with webhook verification; admission fees move onto it
3. Bursary reconciliation, waivers and refunds with approval
