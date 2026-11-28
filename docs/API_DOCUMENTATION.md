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

## Admissions and registry (M3)

Public:

| Method | Path | Notes |
| --- | --- | --- |
| POST | /applicant/register | `{surname, first_name, other_names?, email, phone, password, password_confirmation}`; sends a verification link |
| GET | /applicant/verify/{user}/{hash} | signed link; redirects to the SPA sign-in |
| GET | /admission-exercises/open | exercises taking applications now, with programmes and documents |

Applicant (role `applicant`, permission applications.apply; own applications only):

| Method | Path | Notes |
| --- | --- | --- |
| POST | /applicant/verification-notice | resend the link |
| GET / POST | /applicant/applications | list; start `{exercise_id}` (email must be verified) |
| GET | /applicant/applications/{uuid} | includes `missing` while editable, exercise programmes and documents, offer and clearance |
| PUT | …/biodata, …/details, …/olevel, …/education | sections of the form; locked after submission unless corrections are open |
| POST | …/documents | multipart `{document_type, file}` (PDF, JPG, PNG) |
| POST | …/payments/{application_fee\|acceptance_fee} | `{reference, paid_on, evidence_document_id}` |
| POST | …/submit | 422 lists what is missing |
| POST | …/offer/accept, …/offer/decline | `{declaration: true}` / `{reason}` |
| PUT | …/onboarding | sponsor, residence, emergency contact, medical, declaration; starts clearance |
| POST | …/clearance/{item}/resubmit | `{remarks?}` after a correction request |

Staff:

| Method | Path | Permission |
| --- | --- | --- |
| GET / POST / PATCH | /admission-config/{categories\|exam-bodies\|subjects\|document-types\|clearance-units\|matric-formats}[/{id}] | read: signed in; write: admissions.configure |
| GET / PUT | /admission-config/letter-template | admissions.configure |
| GET / POST / PATCH | /admission-exercises[/{uuid}] | admissions.view / admissions.configure |
| POST | /admission-exercises/{uuid}/status | admissions.configure; `{status, reason}` |
| POST / PATCH / DELETE | /admission-exercises/{uuid}/programmes[/{id}] | admissions.configure; `{programme_id, entry_level, capacity?, rules?}` |
| PUT | /admission-exercises/{uuid}/documents | admissions.configure; `{documents: [{code, stage, required}]}` |
| GET | /admission-applications?exercise=&programme=&department=&status=&outcome=&min_score=&q= | admissions.view, filtered to scope (paginated) |
| GET | /admission-applications/export | same filters, CSV |
| GET | /admission-applications/{uuid} | admissions.view; written to the access log |
| POST | /admission-applications/{uuid}/{screen\|recommend\|hold\|reject\|open-corrections\|close-corrections} | admissions.process; `{reason}` where required |
| POST | /admission-applications/{uuid}/change-programme | admissions.change_programme; `{exercise_programme_id, reason}` |
| GET / PATCH | /admission-documents/{uuid} | download (owner or staff in scope) / review `{status, remarks?}` |
| GET | /admission-payments?status= | admission_payments.verify |
| POST | /admission-payments/{uuid}/review | admission_payments.verify; `{approve, remarks?}` |
| GET / POST | /admission-lists[/{uuid}] | admissions.view / admissions.lists |
| POST / DELETE | /admission-lists/{uuid}/entries[/{application}] | admissions.lists; `{applications: [uuid]}` |
| POST | /admission-lists/{uuid}/submit | admissions.lists; starts `admission_approval` |
| GET | /admission-offers?exercise=&status= | admissions.view; includes clearance and unmet matriculation conditions |
| GET | /clearance-items?status= | clearance.review, filtered to the officer's units |
| POST | /clearance-items/{id}/review | clearance.review; `{status: under_review\|approved\|rejected\|requires_correction, remarks?}` |
| POST | /matriculations | students.matriculate; `{offers: [uuid]}` returns matriculated and skipped with reasons |
| GET | /students?q=&programme=&level=&status=&session= | students.view, filtered to scope |
| GET | /students/{uuid} | students.view |
| POST | /students/{uuid}/status | students.manage; `{status, reason, effective_on?}` |
