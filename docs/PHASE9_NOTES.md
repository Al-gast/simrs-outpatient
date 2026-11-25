# Phase 9 — Testing & Quality Assurance (v0.9.0-qa)

Base: exact `simrs-outpatient-phase8.zip` extracted into a separate working directory. Preserve Phase 1–8 docs, source and approved Premium Healthcare SaaS design tokens. No new third-party dependencies. Scope: source-level review and hardening, not a penetration test or production certification.

## Findings and changes by severity

| ID | Severity | Finding in Phase 8 | Phase 9 change | Runtime proof |
|---|---|---|---|---|
| QA-01 | High | Billing UI stripped all non-digits from input; `1.5` could become `15`. Input length could silently truncate pasted values. | Preserve entered string, explicit integer validation and visible invalid state; no silent truncation. | Browser pending; 8 pure TS input assertions |
| QA-02 | High | Stock adjustment applied another delta on retry after ambiguous response. | Require UUID key, immutable stock adjustment request ledger, actor/medicine/payload comparison and atomic movement + audit. Per-tab recovery in UI. | New PHP policy assertions pass; HTTP replay/concurrency pending |
| QA-03 | High | Core DB grants allowed mutation/deletion of audit/payment/stock/lab event ledgers. | Revoke UPDATE/DELETE/TRUNCATE on four existing ledgers; new adjustment ledger SELECT/INSERT only. | Migration/grant SQL prepared, runtime pending |
| QA-04 | Medium | Login permitted up to 256 bytes but bcrypt accepts effectively 72 bytes and NUL may error. | Shared password policy 1–72 bytes, no NUL; admin still minimum 12 bytes; aligned UI. | Pure PHP pass; HTTP pending |
| QA-05 | Medium | Failed reload after a rejected payment could leave a stale invoice usable for new attempts. | Track `needsReload`; disable new payment until successful refresh; still allow existing same-key retry. | Browser/API pending |
| QA-06 | Medium | Invoice detail aggregated all payments before filtering the selected visit; billing queue lacked a matching partial ordering index. | Restrict payment aggregation for detail to target invoice; add partial billing queue index. | SQL plans prepared, no measured speedup |
| QA-07 | Medium | Some Core timestamp DTOs were raw PostgreSQL strings; browser parsing is implementation-dependent. | ISO 8601 for billing, audit and record timestamps. | PHP syntax pass; browser/API pending |
| QA-08 | Medium | Frontend lacked CSP and explicit permissions policy. | Restrictive local CSP (self scripts, inline styles for Ant Design, exact default Core/Node origins), no camera/mic/geolocation. | Nginx/browser pending; adapt CSP for non-default origins |
| QA-09 | Medium | QA scripts originally used fixed default project DB and had no orchestrated safe test flow; concurrency tests reused identities. | Isolated disposable Compose runner, real distinct doctor/cashier fixtures, negative API checks, explicit grants and database invariants; Docker builds run Vitest. | Dry-run verified; runtime blocked by missing Docker |
| QA-10 | Low | Keyboard focus and narrow drawer sizing could be improved. | Skip link, visible focus, drawer max-width, responsive clinical summary. | CSS/source review only; browser pending |
| QA-11 | Low | Node/Core lab normalization differed for Unicode boundary whitespace and length validation order. | Match PHP trim boundary (ASCII space/tab/newline/CR/VT; reject NUL and >2000 raw bytes). | Syntax pass; service contract runtime pending |

Severity is engineering judgment, not an external penetration-test result. Changes address the issues in source, but runtime fixes have not been demonstrated here.

## Review scope and retained design

- **Authentication/security:** inspected login throttle, JWT claim checks, token revocation, Core/Node authorization, parameterized query paths, error envelopes and logging, CORS, DTO exposure, admin role/doctor linkage. Bearer auth remains in memory; no cookie-based session; Node introspects Core and uses only report views. No role escalation found in inspected route bindings, but formal security testing and dependency vulnerability scans are pending. Already-authorized in-flight requests may finish after revocation; rate-limit cleanup/IP strategy is not hardened for public deployment.
- **DB/transactions:** reviewed queue allocator unique constraints and rollback, master advisory lock, visit lock, clinical version compare, sorted medicine updates, all-or-nothing dispense, lab event key and result lock, visit/invoice/payment-key order, unpaid revision, money arithmetic, transaction audit. Retain Core-only mutation architecture and Node read-only views. New migration 000003 adds one table and index, revokes ledger mutations. Existing baseline SQL and 000001/000002 unchanged. Database owner can still mutate ledgers; not cryptographic tamper-evidence. No trigger-based global financial invariant enforcement; integrity relies on Core service plus constraints and is checked by runtime probes.
- **Concurrency/idempotency:** no new architecture or queue broker. Stable keys protect identical retries, not different keys representing one real-world payment. Lab result/stock/payment retry lifetimes differ: lab draft is memory only, adjustment/payment use per-tab sessionStorage. No offline synchronization or multi-device exactly-once guarantee. Master CRUD last-write-wins and stock not reserved before dispense remain deliberate MVP limits.
- **Query efficiency:** check indexes, date ranges, SQL aggregation, scoped projections, offset pagination, and query plans. New targeted billing aggregation/index is a hypothesis, not a benchmark result. `ILIKE '%term%'` search can scan large data; trigram index, keyset pagination, load test and large-data tuning remain future work. Existing summary uses sequential queries with per-statement timeout; client timeout set longer than server timeout to reduce race. No numerical performance claim.
- **Frontend:** inspect route guards, API error and loading/empty state, clinical optimistic-version handling, payment/stock pending keys, responsive CSS, input and print DOM construction. Print uses text nodes, not user-provided HTML. Design tokens unchanged. Browser screenshots/accessibility audit, print correctness, all-screen visual regression, global SPA unsaved-navigation blocker, and offline durable queue remain pending. No real patient data permitted.

## Actual test evidence

See `docs/evidence/phase9-static-checks.txt`. Executed here: 618 PHP policy/validation assertions (590 prior + 28 new); 411 dependency-free TS assertions (403 prior + 8 new); PHP lint, strict TS compilation of policy subset, 36 TS/TSX syntax checks, JSON/YAML/Python/shell syntax, runner dry-run, baseline/design-token comparison and archive integrity.

Actual blocked attempts: full React build returned TS2688 (`vite/client` types missing); npm registry query returned EAI_AGAIN DNS failure; runtime runner returned exit 1 (`docker` not found). Composer, PostgreSQL client/server and PDO pgsql also absent. Therefore no real Compose build, migration, integration/HTTP, concurrent DB testing, Vitest package test, EXPLAIN ANALYZE, browser rendering, print, Postman, or dependency audit was executed. The number of assertions is not code coverage. No screenshots, HTTP successes, or benchmark numbers are fabricated.

## Run QA on Mac

Use `python3 scripts/qa-runtime.py --outage` or `--keep` for manual UI checks. It refuses occupied default ports and creates a copy with independent random credentials and a distinct Compose project. Fresh migration + seed and migration down/up on the seeded DB precede tests. The rollback refuses to remove the new retry ledger if it contains rows. This migration round-trip is not a proof of migration against all real Phase 8 data. Review any failed stage before proceeding. `--keep` retains the disposable stack; use the exact cleanup command printed by the runner. Do not use `docker compose down -v` on the ordinary app database. Logs and `summary.json` are excluded from Git by default.

## Release readiness

Feature freeze remains. Phase 10 packaging can prepare docs and Git handoff, but do not claim submission-ready fully working software until local runtime and manual verification pass. No deployment, actual repository push, or real Postman/browser evidence yet. No package lockfiles were created. This application is a technical-test MVP using dummy data, not clinical production software.
