# USIMS — University Student Information Management System

USIMS is a modular-monolith university management platform: one Laravel API and one React
single-page app serving every portal (Registry, Bursary, Academic Planning, faculties,
departments, lecturers, students, applicants and ICT) from the same records.

Institution-specific rules (structure, approval chains, fees, grading, number formats) are
configuration, not code. FUNAAB is the process reference; nothing FUNAAB-specific is hard-coded.

| Part | Path | Stack |
| --- | --- | --- |
| API | `backend/` | Laravel 13, PHP 8.3+, PostgreSQL 16, Redis (queues, cache) |
| Web app | `frontend/` | React 19, TypeScript, Vite, TanStack Query, Tailwind CSS 4 |
| Docs | `docs/` | Architecture, database design, API, roadmap, progress |

## Run it locally

Requirements: PHP 8.3+ with `pdo_pgsql`, Composer, Node 22, PostgreSQL 16 (Laragon, a local
install, or `docker compose up -d postgres redis mailpit`).

```bash
# API
cd backend
cp .env.example .env            # set DB_PASSWORD
composer install
php artisan key:generate
php artisan migrate --seed      # in APP_ENV=local this also loads demo data
php artisan serve               # http://localhost:8000

# Web app (second terminal)
cd frontend
npm install
npm run dev                     # http://localhost:5173
```

Demo accounts (local only, password `password`): `admin@usims.test`, `registry@usims.test`,
`planning@usims.test`, `bursary@usims.test`, `cscofficer@usims.test`, `cschod@usims.test`.

## Tests

```bash
cd backend && php artisan test          # needs the usims_test database
cd frontend && npm test && npx tsc -b
```

CI runs both on every pull request (`.github/workflows/ci.yml`).

## Where to read next

- `docs/ARCHITECTURE.md` — modules, access control, workflow engine, integration pattern
- `docs/DATABASE_DESIGN.md` — tables, constraints and the history strategy
- `docs/API_DOCUMENTATION.md` — endpoints and conventions
- `docs/DEVELOPMENT_ROADMAP.md` — milestones M0 to M7
- `docs/PROJECT_PROGRESS.md` — what is done, what is next
