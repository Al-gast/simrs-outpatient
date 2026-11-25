# Phase 6 — Pharmacy & Laboratory (v0.6.0-ancillary)

Cumulative source checkpoint, not a runtime-tested application. Based on Phase 5 ZIP. No schema/migration/design-token or dependency changes; package versions updated. UI retains white sidebar, teal, Ant Design theme and spacious workspace.

## Implemented source

- Doctor: one prescription and one lab order per visit, up to 20 unique items each. Pending draft replacement, deletion using empty items, expected clinical version, reason/audit, active master and stock validation, immutable existing price/name snapshots. No stock reservation. Orders editable only in exam; lab locks on first result.
- Pharmacy: server-side searchable/status-filtered/paginated queue and detail, quantity/stock indicators, confirm entire prescription. Yii transaction acquires shared master advisory lock and visit lock, updates medicine rows in ascending ID order with conditional SQL, inserts one movement per item, marks prescription dispensed, audits and advances visit if all services complete. Insufficient stock rolls back all changes. Repeat dispense returns current order; no duplicate movement.
- Lab: queue available during exam or after it; input one free-text result per test (units as part of result). Result stored via Node or explicitly direct Yii. Original bearer, request ID and UUID key forwarded; Node has no write DB permission. Yii serializes key and visit; hash item + normalized result, compare actor separately, reject mismatched key; same event returns current order without additional write. Result, event ledger, audit, order completion, visit advancement atomic. Results immutable in MVP. Clinical current and historical responses contain order snapshots.
- Node: role introspection, Zod request validation, read-order proxy and result proxy, timeout and 503 ambiguous outcome. Browser freezes payload/key after first submit; retry or direct Core fallback with same key. No automatic blind retry. No Node-side durable queue.
- Status: lab can finish during `in_exam` without advancing visit; finish-exam checks both pending domains. Last pending service after exam transitions visit to `ready_for_billing`.

## Verification boundary

Static and pure policy checks in `evidence/phase6-static-checks.txt`. The actual PostgreSQL/Yii/Node/React runtime, npm full project typecheck, Postman execution, browser screenshots, and concurrent HTTP tests remain unverified in this environment (Docker, PostgreSQL, Composer and project dependencies unavailable). Do not present planned tests as passed. `scripts/ancillary-smoke.py --db-check` creates permanent dummy records; optional `--outage` stops/restarts local Node and checks Core read availability. Postman read/validation probes: `postman/Ancillary.postman_collection.json`.

## Design decisions / limitations

- One lab order per visit (multiple tests), versus multiple orders in initial blueprint; schema is unchanged, application visit lock enforces this for application writes. Draft replacement may allocate new item IDs, and lab orders with any result cannot be replaced. No amendment/cancellation after processing; no partial dispensing or stock reservation. If stock becomes insufficient after exam, replenish/adjust stock before dispensing. No automatic clinical prescribing logic; only user-entered dummy data.
- All requested clinical reads are scoped by Phase 5 authorization. Pharmacy/lab role-wide work queues assume one facility. No additional staff assignment by department. No production health data.
- In-memory retry state is not durable across refresh, logout, tab close or navigation; unsaved inputs may be lost. Inspect authoritative order before resubmitting after reload. Same-key replay guarantees database effects, not a byte-identical response or guaranteed network delivery. Global SPA navigation guard remains pending.
- Integration endpoints simplified to canonical `/laboratory/orders/{id}` and `/laboratory/items/{id}/result` on both services. Original draft endpoint names superseded in API.md.
- Billing, unpaid invoice revision, user administration, dashboard and audit metadata UI remain pending. Dependency lockfiles and build still need verification on Mac. No screenshots fabricated.
