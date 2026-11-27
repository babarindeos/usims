# Development roadmap

Eight milestones cover the twelve phases of the product spec. M0 to M5 is the MVP: the full chain
from application to automatic Moodle enrolment for one pilot college. Each milestone ends with a
pull request, passing tests, updated docs and a review before the next starts.

| Milestone | Spec phases | Deliverables | Accepted when |
| --- | --- | --- | --- |
| M0 Setup | 0 | Monorepo, Docker services, CI, design system, docs | CI green; app runs locally |
| M1 Core platform | 1 | Persons, users, scoped roles, org units, sessions, semesters, calendar, workflow engine, audit, notifications, admin screens | Scenario 12 (audit) passes; scope tests pass |
| M2 Academic setup | 2 | Programmes, modes, categories, courses, prerequisites, curriculum versions with approval, course offerings, staff allocation | Published curricula immutable; offerings unique per session and semester |
| M3 Admissions | 3, 4 | Exercises, applicant portal, biodata, JAMB, O'Level, uploads, screening rules, processing, approvals, offers, acceptance, onboarding, clearance, student record, matric numbers, status history | Scenarios 1 to 4 |
| M4 Fees | 5 | Fee structures with maker-checker, invoices, Remita and Paystack drivers, webhooks, verification, receipts, ledger, refunds, reconciliation | Scenario 6 against sandboxes; webhook replays post nothing twice |
| M5 Registration and Moodle | 6, 7 | Registration rules and approvals, add/drop, forms; Keycloak SSO; Moodle plugins, account and course sync, enrolments, sync dashboard | Scenarios 5, 7, 8, 9, 10 against a real Moodle |
| M6 Results and HR | 8, 9 | Exams, assessments, result entry and approval, amendments, GPA/CGPA; staff master and lifecycle | Scenario 11 |
| M7 Graduation and launch | 10 to 12 | Graduation, alumni, dashboards, exports; load, security and backup testing; manuals | UAT signed off |
