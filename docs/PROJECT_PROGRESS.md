# Project progress

Current milestone: **M2 Academic setup, in review** (M1 also awaiting merge). Next: M3 Admissions to matriculation.

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

## Tests
- Backend: 62 feature and unit tests (M1 plus programmes, courses, curricula, offerings, staff, permission seeding)
- Frontend: design-system and curriculum-editor tests; end-to-end browser walkthrough run manually (draft curriculum, submit, HOD and Planning approval, publish, generate offerings, assign a lecturer, publish an offering)

## Known gaps carried to later milestones
- Keycloak OIDC sign-in and MFA enforcement (M5, with Moodle SSO); local passwords work now
- Workflow escalation and due-date reminders (scheduler job) not yet built
- Notification template editing screen not yet built (templates seeded)
- Org-unit edit and calendar edit screens: API done, UI covers create and list only
- Moodle course creation for published offerings waits for M5; `OfferingPublished` and staff assignment events are already raised for it
- Staff records are entered by hand until the HR module (M6) takes over; programme and course edit forms cover status changes, other edits are API-only for now

## Next tasks (M3)
1. Admission exercises and applicant portal with biodata, JAMB and O'Level records, document uploads
2. Configurable screening rules and processing, approvals through the workflow engine, offers and acceptance
3. Onboarding and clearance, student record creation, matric numbers from a configurable format, status history
