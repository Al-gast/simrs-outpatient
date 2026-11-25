# Test plan — runtime execution pending

Evidence must include actual expected vs observed result, command/request, timestamp and relevant screenshot/log. All runtime scenarios below are planned; dependency-free policy tests have separate actual logs under evidence/. Do not label screenshots or tests PASS until run against actual application. Use fresh seeded test DB, fake identities and accounts. For concurrency use separate DB connections / HTTP workers and a barrier or concurrent requests; sequential Postman tests alone do not prove race safety.

| ID | Scenario / setup | Expected result | Method |
|---|---|---|---|
| T01 | Login each of six roles, bad password, expired token, logout | Valid token only for correct credentials; revoked token 401 | Postman + integration |
| T02 | Registration calls pay, doctor calls other doctor's visit, Node forged role | 403 / scoped 404; no side effects | Postman |
| T03 | Create/update/search each master, duplicate MRN/code/identity, invalid price, delete referenced record | Valid CRUD; duplicate conflict; invalid 422; references retained and inactive | Postman |
| T04 | Register active patient with current-day schedule and mismatched/inactive schedule | Correct doctor/clinic/fee snapshot/queue; invalid rejected | Integration + UI |
| T05 (D1) | Two concurrent requests same patient/clinic/date | Exactly one visit, one success, other 409; counter increments only once | Parallel HTTP + SQL |
| T06 (D1) | Concurrent requests different patients same clinic/day | Distinct queue numbers, both visits present | Parallel HTTP + SQL |
| T07 | Assigned doctor start, save record/vitals/diagnoses, stale version | Valid history; stale version 409; audit before/after | Integration + UI |
| T08 | Add two procedures, change master price | Existing snapshots unchanged; totals correct | Integration |
| T09 | Prescription 2 drugs; stock available before dispense | No stock change on creation, correct amount deducted on dispense; repeated dispense harmless | Integration |
| T10 (D2) | Stock 5, two separate visits/prescriptions qty 5, concurrent dispense | One success; one 409, stock 0, one movement | Parallel HTTP + SQL |
| T11 (D2) | Multi-line dispense, second item insufficient | All medicine updates and movements rolled back; pending stays | Integration |
| T12 | Lab 2 items, first result then second via Node; view doctor history | Pending until both; results persisted through Yii; visit advances when all services done | Postman + UI |
| T13 (D5) | Repeat lab event with same UUID/body; different body with same UUID | Same result on retry; 409 conflict on mismatch; one event | Integration |
| T14 | Finish incomplete exam, invoice while services pending | Rejected; no invoice before ready | Integration |
| T15 | Invoice from 25k registration + 150k procedures + 150k lab + 75k meds | Total 400k; four categories; duplicate generate no duplicate invoice | Integration + UI |
| T16 | Pay 250k cash then 150k non_cash; try overpay | unpaid -> partial -> paid; correct outstanding; overpay rejected; visit completed | Postman + UI |
| T17 (D3) | Commit payment then simulate lost response, retry same key/body | One payment, same receipt; changed payload with same key 409 | Integration + proxy/mock loss |
| T18 (D3) | Two simultaneous same keys; then different keys each attempting full balance | Same-key replay once; different-key overpay rejected; sums <= total | Parallel HTTP + SQL |
| T19 (D4) | Revise procedure before first payment, stale invoice payment | New total/revision and audit atomically; stale revision 409 | Integration |
| T20 (D4) | Concurrent first payment vs procedure revision | Serialized; either revision first + stale payment rejection, or payment first + locked edit | Parallel HTTP + SQL |
| T21 (D4) | Change diagnosis after paid; attempt procedure edit | Diagnosis version + audit, invoice unchanged; procedure rejected | Integration |
| T22 (D5) | Stop Node, core direct lab flow; dashboard request unavailable | Core works; dashboard error not fake zeros; no lost committed data | Compose + UI |
| T23 (D5) | Stop Yii before external lab request, then restore; replay original event; simulate upstream timeout after commit | 503 no false acknowledgment, sender retains key; one committed result on retry | Compose + Postman + integration |
| T24 (D6) | Seed synthetic data, compare dashboard against SQL; run EXPLAIN ANALYZE | Correct metrics, date bounds WIB, stable paging; capture actual query plans and latency | SQL + API |
| T25 | Node DB account attempts core table SELECT/UPDATE | Permission denied; reporting SELECT works | SQL integration |
| T26 | Print invoice and both payment receipts | Printable, correct line totals/receipt numbers; no unrelated clinical notes | Browser |
| T27 | Zero-total invoice | Paid/completed without payment record | Integration |
| T28 | Dashboard period and drill-down for all metrics | Summary matches details; no PHI, forbidden for non-admin | Postman + UI |
| T29 | Inactive master with historical use, medicine stock adjustments | History remains, no new selection, correct audited balance | Integration |
| T30 | Role-based UI and full outpatient workflow | Correct menus, loading/error states, successful demo from registration to payment | Browser E2E |

## Evidence to collect

- Postman collection and environment with local URLs, no real secrets, login tests and workflow requests using collection variables.
- PHPUnit domain/integration tests for critical DB transactions (real PostgreSQL, no SQLite substitute for lock behavior). Parallel script or integration runner with independent connections.
- Vitest for UI helpers; Node tests for middleware, validation, forwarding/errors and reports. Add browser E2E if time permits.
- SQL invariant queries: no duplicate visits/queues, negative stock, duplicate payment keys, payments exceeding total or mismatch invoice total vs item sum.
- Screenshots of six role workflow, invoice and receipt, Node dashboard, and actual Postman runner result. Capture performance numbers only from executed tests.

### Phase 5 prepared runtime checks (not executed)

`python3 scripts/clinical-smoke.py --db-check` checks concurrent record create, concurrent record vs procedure writes, stale finish, wrong-role and out-of-scope access, validation, procedure price/name snapshots, inactive master, diagnosis-only correction, prior-record retrieval and audit counts. Pending-service transitions and cross-doctor historical detail require additional fixtures in Phase 6 / user management; pure policy branches are covered by executed dependency-free tests. Postman collection contains read/validation probes only.

## Phase 6 additional integration scenarios (planned, not executed)

| Case | Expected |
|---|---|
| Save prescription with invalid qty or inactive master | 422 or 409; no partial order |
| Save same clinical version in two tabs | stale write 409 |
| Two prescriptions need same remaining stock | one succeeds; one 409; no negative stock |
| Second item insufficient after first decrement | full rollback including first movement |
| Repeat successful dispense | unchanged stock/movements |
| Enter first lab result during exam | visit stays in_exam; order draft locked |
| Concurrent lab event with same key/payload/actor | two successful responses, single ledger entry |
| Same key, different text or item | 409 conflict |
| Different key on finalized item | 409 conflict |
| All lab items saved but pharmacy pending | visit remains services_pending |
| Last outstanding service finishes | ready_for_billing |
| Node offline | core available; explicit same-key fallback and no double writes |
| Unauthorized role | 403 |

`python3 scripts/ancillary-smoke.py --db-check` exercises most cases; `--outage` also checks core read while Node is stopped. Postman collection has non-mutating probes. Browser/manual check needed for fallback and draft UX.

## Phase 7 additional runtime cases (planned, not executed here)

| Scenario | Expected |
|---|---|
| Finish with pending lab/dispense | No invoice until all services complete |
| Last service finishes | One invoice with all four categories and snapshot totals |
| Repeat issuance | Same invoice ID |
| Doctor revises unpaid procedure | Invoice revision and total updated, previous/new lines audited |
| Pay old revision | 409 STALE_INVOICE |
| Concurrent requests same key | One payment row; both return same payment ID |
| Same key different body/invoice | 409 IDEMPOTENCY_CONFLICT |
| Pay above balance | 409 OVERPAYMENT; no payment row |
| Partial then final payment | Partial then paid and completed |
| Two different keys compete for full balance | One commit, other rejected |
| Payment and revision race | Only one succeeds; other rejected by status/revision |
| Procedure revision after payment | 409 BILLING_LOCKED |
| Diagnosis-only correction after paid | Invoice total unchanged |
| Zero total | Paid/completed without payment row (policy tested; runtime planned) |
| Admin payment or other role billing read | 403 |
| Print invoice and individual receipt | Browser print / save PDF; no diagnosis or payment key |
| Browser refresh after ambiguous write | Retry same payload and key from sessionStorage after login |

Run `python3 scripts/billing-smoke.py --db-check` on disposable dummy database for automated HTTP/race/DB checks; browser and zero-invoice runtime need separate manual verification. Phase 5 regression now checks post-payment procedure lock, not blanket post-finish lock.

## Phase 8 additional planned runtime cases
- Admin-only report and user/audit APIs (all six roles, both backends); no-token rejection.
- Report range validation, leap day, max range, invalid metric and paging; empty period zero/empty, no duplicate-count fan-out; cross-check actual summary metrics with independent base-table SQL after billing smoke.
- Drill-down pagination and clinic filter; timestamps at WIB day boundaries, payments on date different from visit; current invoice status cohort semantics.
- Create unique dummy account, reject duplicate username or staff, role change/password reset and token revocation in both backends, stale account version conflict, deactivate and deny subsequent login, deny self-edit and doctor identity reassignment.
- Audit list contains only metadata, exact filter, no password/hash or clinical snapshots; test user mutations against inactive/changed admin concurrently.
- EXPLAIN ANALYZE for selective and broad date filters; measure on representative volumes, not tiny seed only.
- Browser dashboard responsive layout, period apply, error retry, drill-down, user form, audit filter. None of the runtime cases is claimed executed in Phase 8.

## Phase 9 additional verification (planned runtime unless evidence explicitly records execution)
- Stock adjustment same-key replay, mismatched key payload, missing key, concurrent different keys; compare ledger and stock.
- Separate doctor accounts: both can access own assigned visits; cross-doctor reads/writes/start and unrelated history denied.
- Separate cashier accounts: parallel final payment, same-key replay by winning actor, other-actor key conflict.
- Negative API cases: malformed JSON, unsupported media type, oversized body, malformed bearer, invalid CORS origin, oversized/NUL passwords, invalid bigint ID, invalid query shape and SQL-like filter.
- Ledger grants: core has INSERT/SELECT but no UPDATE/DELETE/TRUNCATE; report role has no core sensitive table read or schema CREATE.
- SQL invariant probes: stock ledger and balance, adjustment link, overpayment, invoice totals, paid visit status, dispense ledger, lab completion, queue counters.
- Fresh Compose build, migration down/up round-trip before business writes, automated API suite, optional Node outage, EXPLAIN plans, browser and print checklist.
