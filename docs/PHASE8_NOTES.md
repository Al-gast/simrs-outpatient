# Phase 8 — Dashboard, Reporting & User Management (v0.8.0-reporting)

Cumulative Phase 7 source checkpoint. No dependency or UI token changes. Includes second Yii migration (indexes and replacement view; baseline schema unchanged).

## Metric contract
- Visits: count visit records, not distinct patients; completed count is current status of visits in selected visit-date cohort. Clinic grouping uses current clinic name. Top 10 displayed; all visits accessible via drill-down (clinic filter supported).
- Procedures: sum quantities attached to visits in selected visit-date cohort, including recorded but not yet billed procedures; top 10 by ID/name snapshot, not revenue.
- Revenue: actual recorded payment sums by paid_at in WIB, including partial payments, not invoice totals. Integer decimal strings, no floating-point money arithmetic. Simulated methods only.
- Medicine usage: dispensed stock movements by created_at in WIB; distinct medicine IDs, movement lines, and quantities grouped by ID/name snapshot. No sum across incompatible units. No stock adjustment or opening stock included.
- Payment status: current invoice payment status for selected visit-date cohort; not historical as-of-end-of-period status. Unissued visits excluded. Zero invoices are paid. Outstanding is current balance. Separate report requests can see newer commits; a single response uses read-only REPEATABLE READ.
- Inclusive WIB day filter with exclusive upper bound, max 366 days (years 2000–2099); timestamp columns not wrapped in filter. Top 10 groups and latest 14 active days on dashboard; full rows via drill-down, 20/page, cap 10,000 pages. No export added (optional). No patient names or clinical text sent to reporting service.

## User management / audit
Admin creates accounts or edits name, role, active status and optional password reset. Doctor account requires a valid staff binding; existing doctor identity cannot be reassigned, and unique staff link remains even when disabled. Username immutable; deactivate instead of hard-delete to retain history. Self-edit forbidden (create a second admin for changes); account mutations use shared master lock and revalidate actor; updates increment token_version even for name change, invalidating sessions. Expected version reuses token_version (logout may cause edit conflict). Previously authorized requests already in flight are not guaranteed canceled. Password 12–72 UTF-8 bytes, hash only stored, never logged in audit. Audit metadata omits reason, before/after, clinical payload; actor username is current immutable username. No user impersonation. Existing audit is transactional where previous modules implemented it; not a complete record of every read or login. Account and audit lists use bounded offset pagination.

## Optimization / evidence
New migration indexes audit(created_at,id), audit(actor_id,created_at,id), partial dispense movement date. Replaces invoice status view with lateral payment sum using existing payments(invoice_id) index, intended to reduce scanning unrelated invoice payments for selective cohorts. Existing visit/date and payment/date indexes retained. Aggregates independent to avoid fan-out, bind all user values, static view whitelist, pool max 5, existing per-query timeout 3s. Query plan benefits and total latency depend on data distribution; no performance benchmark claim. A summary executes several sequential queries; aggregate caching/keyset pagination/materialized views deferred. `scripts/explain-reporting.sql` provides actual plan probes for Phase 9.

## Verification
`docs/evidence/phase8-static-checks.txt`: actual dependency-free PHP/TS policy and syntax checks. `scripts/report-admin-smoke.py` and `postman/Reporting-Admin.postman_collection.json` prepared, not executed. Script compares metrics with base-table SQL and tests lifecycle/RBAC on disposable dummy data; run after billing smoke, without concurrent writes. `EXPLAIN ANALYZE`, PostgreSQL migration and SQL execution, full TypeScript typecheck, Docker build, Node/Yii/React integration, browser and Postman remain unverified because runtime dependencies unavailable. No screenshots or fabricated performance results.

## Pending before submission
Phase 9 fresh-install and upgrade testing, concurrency and outage tests, full build/typecheck, actual query plans, integration and browser evidence, review prior-phase limitations (including no durable offline queue, no global SPA navigation guard, no refunds). Phase 10 packaging and accessible Git repository. Not production clinical software; dummy data only.
