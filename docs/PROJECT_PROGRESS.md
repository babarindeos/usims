# Project progress

Current milestone: **M1 Core platform, in review.** Next: M2 Academic setup.

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

## Tests
- Backend: 40 feature tests (auth, scoped access, persons, org units, sessions, audit, workflow)
- Frontend: design-system tests; end-to-end browser check run manually for sign-in, structure, grants, roles, audit and sessions

## Known gaps carried to later milestones
- Keycloak OIDC sign-in and MFA enforcement (M5, with Moodle SSO); local passwords work now
- Workflow escalation and due-date reminders (scheduler job) not yet built
- Notification template editing screen not yet built (templates seeded)
- Org-unit edit and calendar edit screens: API done, UI covers create and list only

## Next tasks (M2)
1. Programmes, award types, study modes, programme categories
2. Course master with prerequisites and co-requisites
3. Curriculum versions with approval through the workflow engine; published versions immutable
4. Course offerings per session and semester; staff allocation against a minimal staff table
