# Verification guide — v1.0.0

## Evidence levels

- Actual authoring evidence: `evidence/phase10-static-checks.txt` (final dependency-free tests, static validation, runner dry-run). Earlier logs retained as historical records.
- Runtime pending: Docker build, full package-based typecheck/tests, Yii/PostgreSQL migrations, HTTP and concurrency, database grants, Postman, browser/print, query plans. No screenshots or runtime outputs included as evidence.
- `TEST_CASES.md` defines expected outcomes; `QA_MANUAL_CHECKLIST.md` defines 18 manual checks. A planned test is not a pass.

## Recommended Mac run (isolated dummy stack)

From extracted root, start Docker Desktop. Ensure ports 3000/3001/8080 free, and do not run a separate stack concurrently.

```bash
python3 scripts/qa-runtime.py --dry-run --outage
python3 scripts/qa-runtime.py --outage --keep
```

Runner creates random secrets and separate Compose project and volumes, builds images, waits for health, checks migration down/up on fresh seed, runs PHP policies, HTTP smoke tests, grants/invariants and query plans. `--outage` adds Node stop/recovery. `--keep` retains the stack for manual testing and prints exact workdir/cleanup. Without it, only isolated project is removed. On preflight failure (e.g. Docker missing) there may be no summary file; save terminal error. On stage failure see `qa-results/<run-id>/summary.json` and corresponding log. Fix root cause and rerun in a new isolated environment; do not label a failed stage PASS. Use only dummy data. Test scripts write permanent dummy transactions. They are not a production health check.

| Stage | Command/script | Expected proof |
|---|---|---|
| Foundation | smoke.py | 6 logins; role probes; invalid auth/revocation |
| Master | master-smoke.py | CRUD, validation, deletion, stock |
| Registration | visit-smoke.py | concurrent duplicates, distinct queue, assignment |
| Clinical | clinical-smoke.py --db-check | stale version, snapshots, history and audit |
| Ancillary | ancillary-smoke.py --db-check | stock race, multi-item rollback, lab UUID replay |
| Billing | billing-smoke.py --db-check | four categories, revision conflict, two cashiers, partial/paid |
| Reports/Admin | report-admin-smoke.py | independent SQL reconciliation, filters, account changes |
| Security | security-smoke.py | cross-doctor IDOR and adjustment replay |
| DB | verify-db-grants.sh; verify-invariants.sh | permission failures on prohibited operations; stock/payment/visit invariants |
| Outage | ancillary-smoke.py --db-check --outage | Node unavailable/recovered; direct Core fallback |
| Performance | explain-reporting.sql | actual SQL plans/buffers; not a load benchmark |

For standalone dependency-free regression, install PHP CLI, Node, npm and global TypeScript then `sh scripts/check-static.sh`. No vendor/node_modules needed for this subset. It is not a substitute for `npm test` and `npm run build` in both JS projects or actual database transactions.

## Postman and UI

Follow [Postman guide](../postman/README.md) and [demo walkthrough](DEMO_WALKTHROUGH.md). Run the end-to-end collection in order on a disposable DB; export actual run summary with assertions, redact secrets. Sequential Postman replay is not proof of concurrency; retain parallel HTTP/SQL evidence from QA. Complete manual browser tests including print and responsive layouts.

## Performance evidence

Record DB version, CPU/RAM, dataset row counts (visits, payments, stock_movements), date range, exact query, execution plan/buffers, execution time and HTTP timing separately. Compare aggregate results against SQL. Small dummy dataset does not establish production throughput. `EXPLAIN ANALYZE` executes queries; use disposable data. No fabricated benchmark or SLA.

## Result log template (fill only after execution)

| ID/stage | Date, environment, command | Expected | Observed | PASS/FAIL/BLOCKED | Evidence path |
|---|---|---|---|---|---|
| Runtime | — | All required checks pass | Not run | PENDING | — |

Keep raw QA results local; copy only scrubbed evidence under `docs/evidence/runtime/` for submission. Never publish `.env`, credentials, bearer tokens, request secrets, real identities or full clinical data. Review exported Postman JSON for secrets.

## v1.0.1 regression addendum

`master-smoke.py` adds omitted-code generation across five resources, concurrent codes, manual collision, strict deletion, explicit activate/deactivate and role denial. `clinical-smoke.py` and `ancillary-smoke.py` now test first saves without reason, rejected later saves, and history after clearing. `report-admin-smoke.py` tests restore after credential reset/deactivation; `security-smoke.py` asserts CORS, scoped cookie, explicit-origin rejection, restore TTL, cookie-only API denial, and logout revocation. `verify-db-grants.sh` checks new sequence privileges. These HTTP tests simulate cookie headers; real browser persistence is separately tested via `QA_MANUAL_CHECKLIST.md`. `qa-runtime.py --outage` runs all these stages in an isolated dummy project. Authoring results for this release are static only.

## v1.0.2 presentation regression

`sh scripts/check-static.sh` now also compiles `frontend/src/lib/presentation.ts` under strict TypeScript and exercises grouped Rupiah input, rejection of malformed amounts, large exact integer strings, date-only vs timezone-aware timestamps, badge mapping, role-specific master navigation and signed stock adjustment conversion. `frontend/tests/presentation.test.ts` is included in the normal `npm test`/Docker build, but not executed in the authoring environment without package dependencies. Actual static output and full-build limitation are in `evidence/v1.0.2-static-checks.txt`. Browser-level behavior (Ant Design Modal, input caret, dropdown layering and responsive layout) cannot be inferred from pure helper tests. Execute UI102 cases in `QA_MANUAL_CHECKLIST.md` after the final upgrade. Full runtime regression and Postman remain pending for v1.0.1+.

## v1.0.3 workflow regression
`sh scripts/check-static.sh` includes strict compilation of workflow and print-document helpers, role route policy, state transition truth table, display-only transaction ID/search normalization, invoice line sorting, exact progress, and minimal DOM-double execution of print document generator. DOM double does not verify browser CSS, popup permission, real PDF, React Router behavior, or Ant Design integration. Vitest workflow tests are included for future full build. Actual results: `evidence/v1.0.3-static-checks.txt`; execute WF103-01–16 on Mac after combined release and run isolated automated QA again.

## v1.0.4 reporting and admin regression
`sh scripts/check-static.sh` runs dependency-free dashboard/account/audit helper assertions. `scripts/report-admin-smoke.py` now compares invoice cohort sums with direct base-table SQL, verifies staff names, runs separate account reset/status operations and exact audit filters. The HTTP/SQL suite is only for a disposable isolated QA database, not the preserved UAT volume. Run ADM104-01–14 in `QA_MANUAL_CHECKLIST.md` on final Mac runtime; actual static output in `evidence/v1.0.4-static-checks.txt`.

## v1.1.0 release checks
Run `sh scripts/check-static.sh` and `python3 scripts/check-release.py`. The latter statically checks all nine collections against current REST method/path patterns and parses JS event scripts; it does not run Postman. The isolated QA runner now builds sequentially, then starts services. `security-smoke.py` includes a stale bearer vs newer cookie case. Use FINAL_UAT.md and UPGRADE_V1.1.0.md for actual runtime, database fingerprint and release gates.
