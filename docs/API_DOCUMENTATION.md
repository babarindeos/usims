# API

Base path `/api/v1`. JSON in and out. The SPA authenticates with a Sanctum session cookie: call
`GET /sanctum/csrf-cookie`, then send `X-XSRF-TOKEN` on writes. Optional `X-Audit-Reason` header
is stored on audit rows.

Errors are RFC 9457 problem details:

```json
{ "type": "about:blank", "title": "Validation failed", "status": 422,
  "detail": "The code has already been taken.", "request_id": "…", "errors": { "code": ["…"] } }
```

Lists are paginated (`?page=`, `?per_page=` up to 100) except where noted.

## Auth

| Method | Path | Notes |
| --- | --- | --- |
| POST | /auth/login | `{email, password}`; 5 attempts per minute per email and IP |
| POST | /auth/logout | |
| GET | /auth/me | user, effective grants, permission codes |

## Identity

| Method | Path | Permission |
| --- | --- | --- |
| GET | /persons?q= | persons.view |
| POST | /persons | persons.manage; `409` with `matches` for possible duplicates unless `confirm_not_duplicate: true` |
| POST | /persons/duplicates | persons.manage |
| GET / PATCH | /persons/{uuid} | persons.view / persons.manage (view is access-logged) |
| GET | /users?q=&status= | users.view |
| POST | /users | users.manage; `{person_id, email, password?}` |
| GET / PATCH | /users/{uuid} | users.view / users.manage (cannot change own status) |
| GET | /users/{uuid}/grants?include_history=1 | users.view |
| POST | /users/{uuid}/grants | grants.manage over the scope; `{role, scope_type, org_unit_id?, starts_at?, ends_at?, reason}`; never to self |
| POST | /grants/{id}/revoke | grants.manage; `{reason}` |
| GET | /permissions, /roles, /roles/{code} | users.view |
| POST / PATCH | /roles, /roles/{code} | roles.manage; `{name, permissions[]}` |

## Institution

| Method | Path | Permission |
| --- | --- | --- |
| GET | /branding | public |
| PUT | /branding | settings.manage |
| GET | /org-unit-types, /org-units, /org-units/{uuid} | signed in (not paginated; tree order) |
| POST | /org-units | org_units.manage over the parent (global for a top-level unit) |
| PATCH | /org-units/{uuid} | org_units.manage over the unit and the new parent |
| GET | /academic-sessions, /academic-sessions/{id} | signed in |
| POST / PATCH | /academic-sessions, /academic-sessions/{id} | sessions.manage; `processes: {admission: "open", ...}` |
| POST | /academic-sessions/{id}/make-current, /close | sessions.manage; close needs `{reason}` and locks the session |
| POST | /academic-sessions/{id}/semesters | sessions.manage; dates must sit inside the session |
| PATCH | /semesters/{id} | sessions.manage |
| GET | /calendar-events?academic_session_id=&from=&to= | signed in |
| POST / PATCH / DELETE | /calendar-events[/{id}] | calendar.manage over the event's unit (global for university-wide) |

## Audit, workflow, notifications

| Method | Path | Permission |
| --- | --- | --- |
| GET | /audit-logs?type=&record_id=&user_id=&action=&from=&to=&cursor= | audit.view (cursor paginated) |
| GET / POST | /workflow-definitions | workflows.manage |
| GET | /workflow-definitions/{id} | workflows.manage |
| POST | /workflow-definitions/{id}/retire | workflows.manage |
| GET | /workflows/inbox | signed in; items at a stage the user can act on |
| GET | /workflows/mine | signed in |
| GET | /workflows/{uuid} | initiator or a possible approver |
| POST | /workflows/{uuid}/actions | `{action: approve/reject/return/resubmit/cancel, remarks}` |
| GET | /notifications?unread=1 | own only |
| POST | /notifications/{uuid}/read, /notifications/read-all | own only |

## Academic setup (M2)

Catalogue reads (programmes, courses, published curricula, published offerings) are open to every
signed-in user. Writes check the permission against the record's department.

| Method | Path | Permission |
| --- | --- | --- |
| GET | /academic-config/{award-types\|study-modes\|programme-categories}?include_inactive=1 | signed in |
| POST | /academic-config/{kind} | academic_config.manage; `{code, name, level?}` |
| PATCH | /academic-config/{kind}/{code} | academic_config.manage; `{name?, active?}` |
| GET | /programmes?q=&department=&status= | signed in (not paginated) |
| POST / PATCH | /programmes[/{uuid}] | programmes.manage over the department; `study_modes: [{code, duration_years?}]`, `entry_levels: [100, 200]` |
| GET | /courses?q=&department=&level=&status=active\|retired\|all | signed in (paginated, up to 200) |
| POST / PATCH | /courses[/{uuid}] | courses.manage over the department |
| POST | /courses/{uuid}/requisites | courses.manage; `{course_id, type: prerequisite\|corequisite, requirement: pass\|registered}` |
| DELETE | /courses/{uuid}/requisites/{id} | courses.manage |
| GET | /curricula?programme=&status= | published and retired for all; others need curricula.view in scope |
| GET | /curricula/{uuid} | as above; includes courses, groups and approval workflow |
| POST | /curricula | curricula.manage; `{programme_id, effective_session_id, required_credit_units, based_on_id?}` |
| PATCH | /curricula/{uuid} | curricula.manage; draft or returned only |
| POST / PATCH / DELETE | /curricula/{uuid}/courses[/{id}] | curricula.manage; draft or returned only |
| POST / DELETE | /curricula/{uuid}/elective-groups[/{id}] | curricula.manage; draft or returned only |
| POST | /curricula/{uuid}/submit | curricula.manage; starts or resubmits the `curriculum_approval` workflow |
| POST | /curricula/{uuid}/publish | curricula.publish; approved only |
| POST | /curricula/{uuid}/retire | curricula.publish; `{reason}` |
| GET | /staff?q=&department=&status=&category= | staff.view, filtered to scope |
| GET | /staff/{uuid} | staff.view over the unit |
| POST | /staff | staff.manage over the unit; `person_id` or `person{…}` (409 with `matches` on possible duplicates) |
| PATCH | /staff/{uuid} | staff.manage; `employment_status` needs `reason` |
| GET | /offerings?semester=&department=&status=&q=&flagged=1 | published for all; drafts need offerings.view in scope |
| POST | /offerings | offerings.manage over the delivering unit; `{course_id, semester_id, group_code?, capacity?, delivery_mode?}` |
| POST | /semesters/{id}/offerings/generate | offerings.manage; returns `{created, linked, skipped_out_of_scope}` |
| GET / PATCH | /offerings/{uuid} | view as above; PATCH offerings.manage |
| POST | /offerings/{uuid}/publish | offerings.manage; sets `lms_shortname` |
| POST | /offerings/{uuid}/cancel | offerings.manage; `{reason}`; ends active staff assignments |
| POST / DELETE | /offerings/{uuid}/programmes[/{id}] | offerings.manage; `{programme_id, level}` |
| POST | /offerings/{uuid}/staff | offerings.allocate; `{staff_id, role, reason?}` |
| POST | /offerings/{uuid}/staff/{id}/end | offerings.allocate; `{reason}` |
