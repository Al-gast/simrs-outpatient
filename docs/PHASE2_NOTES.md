# Phase 2 — implementation and actual verification

Checkpoint `v0.2.0-foundation`. Source created from Phase 1 `v0.1.1-design` with final UI tokens intact. This is an implementation checkpoint, not a tested release or finished SIMRS.

## Implemented source

- Core Yii2 API uses request correlation, consistent JSON envelope, explicit route allowlist, CORS, bcrypt-based dummy seed, JWT HS256 via firebase/php-jwt, active account and token version validation, generic login errors, fixed-window login throttle. Logout increments token version; no refresh token. Role probe is a foundation check, not the domain authorization implementation.
- Node Express uses Zod for config/role validation and upstream user DTO, live core introspection for every protected request, timeouts, 503 fail-closed behavior and a reporting-only database account. Health checks query a reporting view and core health.
- React uses Ant Design custom light theme, white collapsible sidebar, drawer mobile navigation, teal split login, memory-only bearer, role landing, profile, logout and actual service readiness cards. No domain navigation to nonexistent features, no fake patient or revenue metrics. Approved design tokens copied exactly into frontend source.
- Docker Compose uses internal DB, one-shot migration/seed, separate migration/core/report accounts. Runtime images built on PHP 8.3, Node 22 and PostgreSQL 16 targets. SQL baseline unchanged; migration adds auth throttle table and grants. Dummy seed creates six distinct role accounts, doctors/nurse, two clinics, schedules, one dummy patient, procedures, medicine stock with opening movements, lab master.
- Postman collection and local scripts prepared; not executed against running services here.

## Actual tests run during authoring

| Test | Result | Scope |
|---|---|---|
| PHP CLI lint | PASS | 17 PHP entry/source/test files (including `yii` script), PHP 8.4.24 available locally |
| PHP standalone policy/DTO/API test | PASS | 36 role matrix combinations + 6 checks, not JWT integration |
| TypeScript strict compile (dependency-free modules) | PASS | `backend-node/src/policy.ts` and `frontend/src/lib/roles.ts` only |
| Node policy assertions | PASS | 90 assertions for RBAC, frontend route policy, request ID and bearer shape |
| TypeScript transpilation syntax | PASS | 16 TS/TSX source/test/config files; no third-party type resolution |
| Shell syntax | PASS | setup and DB grant scripts |
| JSON, Python, Compose YAML parse | PASS | syntax/structure, not actual Docker Compose validation |
| Design token equivalence | PASS | source tokens equal Phase 1 approved tokens |
| Selected documentation links | PASS | README and architecture |
| ZIP integrity | checked during packaging | archive CRC only |

Raw output: `evidence/phase2-static-checks.txt`. Re-run pure policy checks with `./scripts/check-static.sh` if PHP, Node and global tsc are installed. Vitest files are included but Vitest was not installed/executed here.

## Not executed / blockers

- Docker/Compose, PostgreSQL server/client, Composer and PDO PostgreSQL extension were unavailable. npm registry lookup failed with `EAI_AGAIN` (DNS). No node_modules or vendor installed.
- No Docker build, full dependency-aware TypeScript typecheck, Vite production build, Yii bootstrap, PostgreSQL migration/seed, real JWT login, live network integration, Postman runner, DB grants or browser UI tests executed. No screenshots are claimed as actual application evidence.
- No generated package-lock.json/composer.lock. Package manifests use compatibility ranges; verify versions/advisories and commit actual locks on a network-enabled machine.
- Domain modules, user management CRUD, master CRUD, clinical object scoping, lab integration command and dashboard metrics are still future phases. Current role probes do not prove clinical authorization. No concurrency, billing, inventory or clinical transaction tests run.
- PHP CLI server for evaluation is not production-ready and serializes requests by default. Future concurrent testing needs multiple workers or PHP-FPM. Throttle buckets need periodic pruning for production. Demo secrets are generated locally; do not expose app publicly.

## Phase 2 exit gate (run before Phase 3)

1. `./scripts/setup-env.sh`; `docker compose config --quiet`; `docker compose up --build -d`; confirm migrate exits 0 and both health checks pass.
2. `python3 scripts/smoke.py` (109 expected HTTP checks; use actual script output as authoritative; checks all six roles, both services, cross-role denial, invalid credentials and revoked JWT). Run Postman collection independently.
3. `./scripts/verify-db-grants.sh`; ensure all 5 reporting views readable and core table access/write/DDL denied for report account.
4. In browser login as all six roles, inspect authorized menu, forbidden route, profile, logout, reload. Test desktop 1440/1280/1024, tablet 768 and mobile 390 widths; record actual screenshots.
5. Generate dependency lockfiles and execute full builds/tests; fix any runtime errors before building master modules. Preserve logs and do not claim PASS for anything not executed.

Only the static subset of the exit gate is currently passed.
