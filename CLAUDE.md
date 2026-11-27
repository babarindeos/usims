# Working on USIMS

Read `docs/PROJECT_PROGRESS.md` first; it says which milestone is in progress and what is next.

## Rules from the product spec

- Modular monolith: code lives in `backend/app/Modules/<Module>`; a module owns its tables and
  others reach it through its services or events, never by writing its tables.
- Never hard-code an institution policy (approval chains, fees, grading, credit limits, number
  formats). Put it in configuration tables with admin screens.
- Never overwrite history: status changes, identifiers, grants, financial and result records are
  appended or end-dated. Sensitive models use the `Auditable` trait.
- Every protected action is authorised on the server with a permission code
  (`Gate::authorize('module.action', $orgUnitOrRecord)`); scope is checked against the org-unit tree.
- Approvals go through `WorkflowEngine`, not controller logic.
- No fake functionality: no placeholder statistics, no simulated payments or Moodle calls. If an
  integration is not configured, say so in the UI.
- Never commit secrets.

## Commands

- Backend tests: `cd backend && php artisan test` (PostgreSQL `usims_test` database)
- Backend style: `cd backend && vendor/bin/pint`
- Frontend: `cd frontend && npx tsc -b && npm run lint && npm test && npm run build`

Update `docs/PROJECT_PROGRESS.md` and the relevant design doc whenever a module changes.
