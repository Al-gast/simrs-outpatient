# Implementation and submission plan

All phases other than design are pending. Prefer a working end-to-end thin slice rather than polishing individual modules in isolation. Do not claim a production-ready clinical system.

| Phase | Deliverable | Exit gate |
|---|---|---|
| 1 | Requirement, architecture, ERD, SQL baseline, RBAC, contract, tests, approved UI/UX specification | Cross-check B1–B9/C/D/E/F; design checkpoint |
| 2 | Yii2, React, Express, PostgreSQL, Compose, migrations, seed, login, light sidebar shell, theme tokens and role checks | Fresh DB migration, six role logins, UI shell/route guards, health, service connectivity and DB grants verified |
| 3 | Master CRUD and search | All masters, safe deletion, stock adjustment, validation |
| 4 | Registration/queue | End-to-end visit creation; duplicate and concurrent queue tests |
| 5 | EMR and procedures | Assigned doctor authorization, versioned record, snapshots |
| 6 | Pharmacy and laboratory | Atomic dispense, lab API bridge, status transitions |
| 7 | Billing & payment | Invoice, partial/full payment, print, idempotency and amendment tests |
| 8 | Node dashboard | Six metrics, filters and detail rows, unavailable state |
| 9 | QA | Actual Postman, integration/concurrency/security checks, screenshots, known limitations |
| 10 | Package | Reproducible setup, Git accessible to evaluator, final ZIP and submission |

## Target repository structure

```
simrs-outpatient/
  frontend/              # React application
  backend-yii/           # Yii web entry, config, controllers, models, services, migrations, tests
  backend-node/          # Express routes, middleware, clients, reporting, tests
  database/              # phase 1 baseline and later exported schema
  docs/                  # design and actual evidence
  postman/               # collection and environment
  docker-compose.yml
  .env.example
  .gitignore
  README.md
```

Phase 2 UI baseline: follow [UI/UX specification](UI_UX_SPECIFICATION.md) and [design tokens](UI_TOKENS.json); use shared components across all later phases.

Phase 2 target: create actual app and lockfiles, health checks, migration runner and deterministic seed; avoid running destructive seed/reset on normal startup. Use Docker Compose commands documented after actual implementation. Use Git commits by phase; ZIP is backup not a substitute for required accessible repository.

## Submission checklist (PDF F)

- [ ] React, Yii and Node sources; accessible Git repository (verify evaluator permissions)
- [ ] Database schema, ERD, Yii migrations or SQL installation route
- [ ] Architecture diagram, communication/ownership explanation, API docs, setup, decisions
- [ ] Actual screenshots, end-to-end workflow, invoice/receipt, Node service evidence
- [ ] Main test cases, actual Postman results, unit/integration tests for critical transactions
- [ ] Dummy data only; no `.env`, credentials, real patient records, vendor/node_modules or database volume
- [ ] Send to inovamedika.career@gmail.com; subject `Senior Programmer_Pengumpulan Real Case Test_Nama Lengkap`
- [ ] Submit by Wednesday, 25 November 2026, 14:00 WIB (HR message)

If deadline is close: freeze feature expansion, finish end-to-end path, verify core invariants, document missing requirements honestly. Export PDF, decorative UI and public deployment are lower priority than required functionality. Check recruitment rules on assistance and ensure you can explain and modify every submitted implementation.

## Phase 1 verification

Static check: README file links resolve; all SQL REFERENCES target declared table names; ZIP integrity verified. PostgreSQL server/client and Docker were unavailable in the build environment, so DDL execution, constraint behavior and all application tests remain unverified.

## Phase 2 checkpoint v0.2.0

Source written for foundation. Exit gate is **not yet fully passed**: Docker, PostgreSQL, Composer/vendor and npm dependency resolution were unavailable in authoring environment. Follow `README.md` and `PHASE2_NOTES.md`; execute the smoke and grant scripts on Mac before advancing to Phase 3. Domain endpoints not in this checkpoint are intentionally unimplemented.

## Phase 3 checkpoint v0.3.0

Source implemented for the seven master resources. User management remains pending. Static checks passed; runtime exit gates for Phases 2 and 3 are deferred at user request and must be verified before submission.

## Phase 4 checkpoint v0.4.0

Source implemented: registration, queue, history, administrator read-only monitoring, assigned doctor start. Uses existing visits/counter constraints, no new migration. Static policy tests passed; runtime gates remain pending at user's request. See `PHASE4_NOTES.md`. Phase 5 adds EMR and visit procedures.

## Phase 5 checkpoint v0.5.0

Source implemented for assigned clinical workspace, EMR, vital signs, diagnoses, procedure selection and pricing, shared optimistic version, scoped history, audit and finish exam. Existing schema and theme unchanged. Static tests passed; all runtime gates remain pending. Phase 6 adds pharmacy/laboratory and must reuse visit locks, version coordination where relevant and clinical workspace. Phase 7 adds billing and permitted unpaid invoice revision.

## Phase 6 checkpoint v0.6.0

Source implemented for doctor ancillary drafts, pharmacy and laboratory workspaces, atomic stock decrement and movement ledger, Node result bridge, idempotency ledger, result review, audit and visit status advancement. Existing schema/design tokens unchanged. Runtime exit gates remain deferred; see PHASE6_NOTES.md. Phase 7 adds billing from completed service snapshots and unpaid invoice revision.

## Phase 7 checkpoint v0.7.0

Source implemented for invoice issue, cashier/admin billing workspace, partial and final payments, payment key replay and overpayment checks, unpaid procedure/invoice revision, audit and printable invoice/receipt. Entire clinical-to-payment workflow is present in source, not yet runtime verified. Phase 8: dashboard/report API via Node, period filter, drill-down, and remaining user management. See PHASE7_NOTES.md.

## Phase 8 checkpoint v0.8.0
Source implemented for reports, user administration and audit metadata. Static policy checks and syntax checks only; runtime verification still deferred. Phase 9 should execute fresh install + upgrade migration, all smoke scripts, full typecheck/build, browser and print flows, and query plans against dummy data. See PHASE8_NOTES.md.

## Phase 9 checkpoint v0.9.0
Static review and regression; fixes and new QA migration; isolated runtime runner and manual checklist. Actual Docker, PostgreSQL, full dependency typecheck, browser, print and Postman verification remain pending. See PHASE9_NOTES.md and QA_MANUAL_CHECKLIST.md. Phase 10 should package verified evidence only, after running the app locally.

## Phase 10 checkpoint v1.0.0
Source release candidate packaged; actual static regression log, final route reference, diagrams, traceability, demo and submission guides included. Runtime and Git publication are still open gates, not claimed complete. See [submission checklist](SUBMISSION_CHECKLIST.md) and [release notes](PHASE10_NOTES.md).
