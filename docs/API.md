# REST API reference — v1.1.0 (source contract; runtime pending)

Core `http://localhost:8080/api/v1`; Node `http://localhost:3001/api/v1`. JSON UTF-8, bearer token except login, health and explicit browser session transport. IDs and money serialized as decimal strings (bigint-safe); quantity/version as integer; date `YYYY-MM-DD` local WIB, timestamps ISO-8601 with offset/UTC. Money inputs nonnegative integer rupiah strings, payments positive; enforce safe bounded integer multiplication/aggregation. DTO fields snake_case. Reject unknown writable fields, never mass-assign actor/status/price. HTTP error status and domain code consistent in both services.

```json
{"data":{"id":"1"},"meta":{"request_id":"req-123"}}
```

```json
{"error":{"code":"INSUFFICIENT_STOCK","message":"Stok obat tidak mencukupi","details":{}},"meta":{"request_id":"req-123"}}
```

Lists: `data: []`, `meta: {page,per_page,total,request_id}`; default page=1, per_page=20, max 100. Filters `q`, `active`, `status`, `clinic_id`, `doctor_id`, `date`, `from`, `to` only where applicable. Stable sorting with ID tie-breaker. 200 read/update/replay; 201 create; 204 hard deletion/logout (soft deactivation returns 200); 400 malformed JSON; 401 auth; 403 role; 404 not found/scope; 409 conflict; 422 validation; 429 rate-limit; 503 unavailable; 500 generic unexpected error. Error details contain field-level messages, no secrets or SQL.

## Yii core endpoints (current routes)

| Method / path | Access | Request / behavior |
|---|---|---|
| GET /health | public | service & DB readiness, no internals |
| POST /auth/login | public | username, password -> access_token, expires_in, user |
| GET /auth/me | authenticated | id, name, role, staff_id; Node introspection |
| POST /auth/logout | authenticated | revoke via token_version |
| GET/POST /admin/users | admin | list/create with username,name,password,role,staff_id |
| PATCH /admin/users/{id} | admin | edit password/role/active with expected_version; active=false disables |
| GET/POST /master/{resource} | RBAC | search/list/create; whitelist resource below |
| GET/PATCH/DELETE /master/{resource}/{id} | RBAC | read/update; legacy delete or deactivate; `?mode=permanent` strict delete |
| POST /master/medicines/{id}/adjust-stock | admin | delta integer, reason; required UUID `Idempotency-Key` header; return current balance, `movement_id`, `replayed` |
| GET /visits | admin,registration,doctor | filters date,clinic,doctor,status; doctor own scope; role-specific projection |
| POST /visits | registration | patient_id, schedule_id; derive doctor,clinic,date,fee,queue |
| GET /visits/{id} | admin,registration,doctor | role-specific details; doctor own scope |
| GET /patients/{id}/visits | registration,doctor | administrative/clinical history per RBAC |
| POST /visits/{id}/start | doctor | waiting -> in_exam; assigned doctor only |
| GET /visits/{id}/clinical | doctor | assigned visit, EMR and procedure snapshots; separate clinical projection |
| GET /visits/{id}/clinical-history | doctor | scoped, paginated historical metadata |
| GET /visits/{id}/clinical-history/{target} | doctor | authorized historical clinical snapshot; audit access |
| PUT /visits/{id}/medical-record | doctor | complaint,anamnesis,vital_signs,physical_exam,primary_diagnosis,secondary_diagnoses,expected_version,reason if update; 0 for create |
| PUT /visits/{id}/procedures | doctor | items[{procedure_id,quantity}], expected_version, reason when required; replace list transactionally during in_exam or ready_for_billing without payments; revise invoice atomically |
| PUT /visits/{id}/prescription | doctor | items[{medicine_id,dosage,frequency,quantity}], expected_version, reason when required; replace pending during exam; empty removes draft |
| PUT /visits/{id}/lab-order | doctor | items[{lab_test_id}], expected_version, reason when required; one order per visit; replace before first result; empty removes draft |
| POST /visits/{id}/finish-exam | doctor | expected_version; verify complete EMR, advance workflow |
| GET /pharmacy/orders | pharmacy | status filter, limited patient info |
| GET /pharmacy/orders/{id} | pharmacy | items and availability |
| POST /pharmacy/orders/{id}/dispense | pharmacy | dispense entire prescription atomically |
| GET /laboratory/orders | laboratory | status filter, limited patient info |
| GET /laboratory/orders/{id} | laboratory | items and result status |
| POST /laboratory/items/{item_id}/result | laboratory | result_text; Idempotency-Key UUID required; event replay supported |
| GET /billing/visits | cashier,admin | list eligible visits and invoices; status/q/page/per_page |
| GET /billing/visits/{id} | cashier,admin | invoice, items, payments, current balance; id is visit ID |
| POST /billing/visits/{id}/invoice | cashier | issue legacy missing invoice; return existing on repeat |
| POST /billing/visits/{id}/payments | cashier | amount,method,expected_revision; Idempotency-Key UUID |
| GET /admin/audit | admin | metadata only; period, actor_id, action, entity_type |
| GET /system/access/{role} | matching authenticated role | foundation probe |

Master resource names & writable fields:

| Resource | Fields |
|---|---|
| patients | identity_no nullable,name,birth_date,sex,phone,address; MRN generated server-side |
| medical-staff | code,name,profession (doctor/nurse/other) |
| clinics | code,name,registration_fee |
| doctor-schedules | doctor_id,clinic_id,weekday (ISO 1-7),start_time,end_time |
| procedures | code,name,price |
| medicines | code,name,unit,price; stock starts 0, changes via adjustment |
| lab-tests | code,name,unit,price |

All master records expose active flag; PATCH may change active subject to invariants. `medical-staff?profession=doctor` is doctor picker. `doctor-schedules?clinic_id=...&date=...` selects weekday. Queue status is visit status. `profession` is an allowed filter for medical staff. Phase 5 doctor clinical projection is separate at `/visits/{id}/clinical`; includes EMR and procedures. Phase 6 adds prescription and lab order snapshots to current and historical clinical responses. Administrative visit detail never includes clinical notes.

## Node supporting endpoints

| Method / path | Access | Behavior |
|---|---|---|
| GET /health | public | report service readiness; no internals |
| GET /laboratory/orders/{id} | laboratory | authenticated proxy to Yii lab order |
| POST /laboratory/items/{item_id}/result | laboratory | validate UUID, result_text, forward original bearer and same key to Yii; propagate domain codes |
| GET /reports/summary?from=...&to=... | admin | aggregate reporting views |
| GET /reports/details?metric=...&from=...&to=... | admin | paginated rows; metric allowlist |

Also `GET /system/access/{role}` is a role-specific probe. Report dates are inclusive WIB, maximum 366 days. See Phase 8 details below for response keys and metric enum. No clinical text or patient identity in reporting DTOs.

## Example payment (current)

```http
POST /api/v1/billing/visits/42/payments
Authorization: Bearer <token>
Idempotency-Key: 9c670c94-a31f-4d24-9eb4-67c7bdc69de1
Content-Type: application/json

{"amount":"250000","method":"cash","expected_revision":1}
```

Response `data: {invoice, payment_id, replayed}`; invoice contains individual receipts and current balance, including on replay. Same key must retain actor, visit and payload. For a new payment use a new UUID only after reconciliation. Print using the invoice/payments DTO and browser print CSS.

## Implementation history (Phase 2–9)

The following dated notes preserve implementation history; earlier statements of planned features are superseded by the current route table and later deltas. Runtime verification is still pending.

## Phase 2 implementation status

Implemented source: Core health, login, me, logout; Node health. Both expose `GET /system/access/{role}` as a foundation-only authorization probe (same role required). The probe is not a clinical permission endpoint; later domain-specific role policies remain mandatory. Node introspects via core and validates the role path with Zod. Node dashboard/lab business endpoints and all domain CRUD/workflow endpoints remain planned. Login throttles requests by source IP and username/IP (fixed 15-minute window); 415 for non-JSON login body; 413 for oversized body. No dependency/runtime verification yet in build environment; see `PHASE2_NOTES.md`.

## Phase 3 implementation delta

Seven master endpoints implemented; user administration remains planned. Schedule filter `weekday=1..7` added. Search uses case-insensitive literal contains, including doctor/poli names for schedules; doctor patient search only name/MRN. Master list allows page up to 100000 and per_page up to 100. Price limits 0–999999999999 rupiah, adjustment delta ±1000000, nonzero. Clinic and doctor deactivation requires first disabling active schedules, doctor account linkage also checked. Stock adjustments are transactional but not idempotent; on timeout recheck current balance and audit before retry.

## Phase 4 implemented visit contract

`GET /visits` defaults to server today in Asia/Jakarta; supports `page`, `per_page`, `q`, `date`, `clinic_id`, `doctor_id`, `status`. Metadata adds `today` (server WIB date) for the registration form. `q` searches patient name/MRN/identity for registration and admin; doctor searches name/MRN only. Doctor results always restricted by linked staff ID, even with a conflicting doctor filter. `GET /patients/{id}/visits` uses same filters but defaults to all dates; doctor only sees their own visits at this checkpoint, and 404 if no assigned visit exists. No clinical fields in administrative visit responses.

`POST /visits` accepts exactly `{"patient_id":"1","schedule_id":"2"}`; only registration role. Server derives doctor, clinic, current date, fee, queue and status. 201 on success, 409 `DUPLICATE_VISIT`, `QUEUE_CONFLICT`, `PATIENT_INACTIVE`, `SCHEDULE_UNAVAILABLE`; 404 missing patient; 422 invalid body. `POST /visits/{id}/start` is doctor-only, no body; 200 waiting→in_exam or existing in_exam replay, 404 out of scope, 409 `INVALID_VISIT_STATUS` otherwise. Deadlock/serialization failure retried at most twice, then 503 `RETRY_LATER`.

Visit DTO includes id, patient_id, doctor_id, clinic_id, schedule_id, visit_date, queue_number (int), queue_label, registration_fee (decimal string), status, created_at/updated_at (ISO), patient_name, medical_record_no, clinic_name, clinic_code, doctor_name, start_time, end_time. Clinic code/name and schedule times are current master display values, not snapshots; registration fee and numeric queue are snapshots. No identity number or clinical notes in this DTO.

### Phase 5 implemented clinical contract

`GET /visits/{id}/clinical` and all clinical writes return `data: {visit,record,procedures,procedure_total}`; record is null before creation. Amounts and IDs are decimal strings, quantities/versions are integers. `GET /clinical-history` (relative to a visit) returns paginated metadata; its target route returns `{visit_id,visit_date,record,procedures,procedure_total}`. Only the assigned doctor may write or read the anchor visit. Cross-doctor history is read-only and requires an assigned `in_exam` visit today (WIB) for the same patient; detailed historical reads are audited.

Record create: expected_version=0; every later write requires the latest version. Record and procedure updates and finish share a version, incremented atomically. Required text: complaint, anamnesis, physical_exam, primary_diagnosis; secondary_diagnoses array, up to ten. Required numeric vital_signs: systolic, diastolic, pulse, temperature, respiratory_rate, weight. Bounds are MVP input guardrails, not clinical decisions. Update reason is required after record creation. For procedures, reason may be omitted on the first successful section save, but is required for subsequent saves, clearing/re-adding after a previous save, and any invoice revision. Maximum 30 unique procedure lines, quantity 1–100 each. Do not send price, actor or status. Empty items clears the procedure list. Existing snapshot prices retained until a line is removed; new line prices from active master.

409 STALE_RECORD: reload, reconcile, do not blindly replay. 409 RECORD_REQUIRED on finish without EMR; INVALID_VISIT_STATUS on disallowed save/finish; BILLING_LOCKED on procedure updates after exam; PROCEDURE_UNAVAILABLE for new inactive/missing master. Phase 5 originally allowed only diagnosis amendments after finish; Phase 7 supersedes this with unpaid procedure/invoice revision (below).

## Phase 6 implemented ancillary contract

Core `PUT /visits/{id}/prescription` and `PUT /visits/{id}/lab-order` accept `expected_version` (>0), optional initial `reason`, and 0–20 unique items. After a section has been saved, every later save or removal requires a non-empty reason (including re-adding after deletion). Quantity integer 1–10000; dosage/frequency each up to 200 UTF-8 bytes. Empty items removes an existing pending order (not yet processed). Each edit increments the shared clinical version. Existing master name/price snapshots are retained; line IDs can change on draft replacement. One lab order per visit for this MVP, containing multiple tests.

Core list/detail: `/pharmacy/orders` and `/laboratory/orders`; `q`, `status=pending|done|all`, `page`, `per_page`. Pharmacy only sees visits after examination finish. Lab can see active examinations too. Detail exposes minimal patient identity, order status, items; no unrelated EMR. `POST /pharmacy/orders/{id}/dispense` with `{}` is naturally idempotent by prescription ID; returns current dispensed snapshot on repeat. Rechecks all items and commits stock movements atomically.

Both Core and Node expose `POST /laboratory/items/{item_id}/result` with `{"result_text":"dummy"}` and required `Idempotency-Key: UUID`. Node additionally proxies GET `/laboratory/orders/{id}`. Node validates and forwards original bearer, request ID and key. Yii writes result, ledger, audit and visit status in one transaction. Same actor/item/normalized text/key returns current order snapshot without writing; mismatched key -> `IDEMPOTENCY_CONFLICT`; different key on an already recorded item -> 409 `RESULT_EXISTS` or `ORDER_LOCKED` if order already completed. On timeout Node returns 503 with unknown outcome; repeat same key/payload or use explicit direct Yii fallback. Result free text up to 2000 bytes; include units manually. No result amendment or durable Node inbox in this MVP.

The two ancillary draft endpoints, six Core queue/detail/process endpoints, and two Node endpoints above are source-implemented. Earlier `/integration/...`, `/prescriptions`, and `/lab-orders` planned paths are superseded. All runtime evidence still pending.

## Phase 7 implemented billing contract

All billing routes on Core only; no Node write access. IDs and monetary amounts are decimal strings. `GET /billing/visits?q=&status=all|unpaid|partial|paid|unissued&page=1&per_page=20` returns list and pagination metadata. `GET /billing/visits/{visit_id}` returns patient name/MRN, visit date and clinic, invoice identifiers, revision, total_amount, paid_amount, balance, payment_status, items and payments. The status `unissued` is only for older ready visits lacking an invoice. Both reads require admin or cashier. No diagnosis or raw payment keys in response.

`POST /billing/visits/{visit_id}/invoice` with `{}` (cashier only) issues missing invoice for ready legacy visits or returns existing invoice. New visits are invoiced automatically upon transition to ready_for_billing. Zero total is paid and completed without a payment row.

`POST /billing/visits/{visit_id}/payments` (cashier only), header `Idempotency-Key: UUID`, body `{ "amount": "10000", "method": "cash", "expected_revision": 1 }`. `method=cash|non_cash`; positive integer rupiah as a string (1–18 digits). Returns `data: {invoice, payment_id, replayed}` (HTTP 200 for both creation and replay); receipt_no and individual payment details are in `invoice.payments`. Replays compare invoice, actor and hash of canonical amount/method/revision/visit, return current invoice and original payment ID, no extra payment/audit. 409 `IDEMPOTENCY_CONFLICT` if mismatch, `STALE_INVOICE` if revision changed, `OVERPAYMENT` if amount exceeds balance, `INVOICE_CLOSED` when paid, `INVOICE_REQUIRED` if missing invoice; 422 invalid input/key. 404 if visit not billing-ready/completed.

`PUT /visits/{id}/procedures` now permits assigned doctor edits while ready_for_billing with zero payment rows/amount. Same clinical expected_version and reason; update invoice lines, total, revision and audit atomically. Clinical current response includes `can_revise_procedures` (advisory UI flag, verified again on write). Diagnosis-only amendments remain possible after payment and do not alter invoice. A revision with identical lines leaves invoice revision unchanged. Paid/partially paid invoices cannot be modified, voided or refunded in MVP.

## Phase 8 implemented routes
All routes require an authenticated administrator. Core uses the existing API envelope; list endpoints return `data` array and `meta` pagination. Node summary/details return `data` object. Authorization is enforced in both APIs (Node introspects Yii).

- Node `GET /api/v1/reports/summary?from=2026-11-01&to=2026-11-24`: visits/completed, top 10 clinics, procedure quantities/groups, cash received/transactions by day, dispensed medicines/types/groups, payment status/current balance, visits trend. Inclusive WIB date period, max 366 days.
- Node `GET /api/v1/reports/details?from=...&to=...&metric=visits&page=1&per_page=20`: metric enum `visits|clinics|procedures|revenue|medicines|payments`; optional `clinic_id` for visits/clinics only. `data={metric,rows,total,page,per_page}`. Numeric totals and IDs are decimal strings. Page 1–10000, per_page 1–100. No patient identity or clinical text in report views.
- Core `GET /api/v1/admin/users`: filters `q`, `role`, `active=true|false`, `page`, `per_page`. DTO id, username, name, role, staff_id, active, version (token_version), plus nullable staff_name in list responses as of v1.0.4. No password hash.
- Core `POST /api/v1/admin/users`: `{username,name,role,staff_id,active,password}`; username lowercase, staff_id decimal string for doctors else null, password 12–72 UTF-8 bytes. 201 success; duplicate username/staff 409.
- Core `PATCH /api/v1/admin/users/{id}`: `{name,role,staff_id,active,expected_version,password?}`. Password omission preserves hash. Status false deactivates account; no hard deletion. Cannot edit own account or reassign existing doctor identity. All successful updates increment token version; stale versions return 409.
- Core `GET /api/v1/admin/audit?from=...&to=...`: `actor_id`, `action`, `entity_type`, `page`, `per_page` optional. Metadata only: id, actor_id, actor_username, action, entity_type, entity_id, request_id, created_at. No reason or before/after payload. Filter exact action/entity; max 366 days.

### Phase 9 API compatibility notes
- `POST /master/medicines/{id}/adjust-stock` now requires `Idempotency-Key` UUID; old clients must add this header. Existing `data.stock` stays available; `data.movement_id` and `data.replayed` are added. Replay uses current master data, not historical balance. Reuse with different payload/actor/medicine: 409 `IDEMPOTENCY_CONFLICT`.
- Login accepts 1–72 UTF-8 bytes, no NUL, consistent with bcrypt; new/reset passwords still require 12–72 bytes.
- Billing, audit and clinical timestamps normalized to ISO 8601 where applicable. No route changes otherwise.

## v1.0.1 — additive browser session and master/clinical contracts

- Existing `POST /auth/login`, `GET /auth/me`, `POST /auth/logout` and bearer semantics are unchanged. New `POST /auth/browser/login` takes the same login JSON, returns the same DTO and sets a host-only `simrs_browser_session` cookie (HttpOnly, SameSite=Strict, Path=/api/v1/auth/browser/, Max-Age=7200; Secure on HTTPS). `POST /auth/browser/restore` takes `{}` and returns the same bearer and remaining expiry only if valid; 401 denies restore without deleting the cookie (v1.1.0; avoids a delayed 401 clearing newer login). `POST /auth/browser/logout` takes `{}`, accepts optional current bearer, revokes the explicit bearer if supplied, otherwise cookie session; clears matching cookie, returns 204. A different cookie is left untouched (v1.1.0). Exact configured Origin is mandatory on these three endpoints (local HTTP allowed only for `http://localhost:3000`); credentialed CORS restricted to them. A cookie alone never authorizes other API routes.
- `POST /master/{resource}` may omit `code` for medical-staff, clinics, procedures, medicines, lab-tests. Explicit code remains accepted for compatibility; existing codes are not rewritten. API validates any explicitly supplied code. Auto prefixes D/N/S, POL-, T, OB, L, minimum six-digit sequence number. Unique DB constraints and master advisory guard handle manual collisions; gaps are normal.
- `PATCH /master/{resource}/{id}` with `{"active":false}` or true is explicit status change, existing RBAC and dependency validation apply. `DELETE /master/{resource}/{id}?mode=permanent` returns 204 on physical removal or 409 if referenced/stock remains, without deactivation. Legacy DELETE without `mode` still falls back to deactivation where allowed (200). UI now uses strict mode and a separate status action.
- Current clinical DTO adds `reason_required: {procedures, prescription, lab_order}` (booleans). First section write may omit reason or send null; explicitly supplied non-null reason must be valid non-blank text <=500 bytes. Later writes require reason based on persisted rows or append-only section audit history inside the visit transaction; invoice revisions always require reason. An initial empty successful save also counts as section history. Record updates and stock adjustments still require reason under their original rules. Existing clients supplying a reason remain valid.

### v1.0.3 presentation-only changes
Frontend dedicated routes are not REST endpoints. Clinical save and finish retain their original `expected_version`; billing payment retains `expected_revision`, raw visit ID and `Idempotency-Key`. Formatted RX/LAB/INV/PAY labels are not new canonical identifiers. Frontend maps an exact zero-padded invoice query back to its canonical `INV-n` form for existing search. No changes to API bodies, response envelope, or backend role policy.

### v1.0.4 additive read projections
- `GET /reports/summary`: each `payments[]` group additionally includes `total_amount` and `paid_amount` (decimal strings). These aggregate all recorded payments on invoices associated with visits in the selected date period; `outstanding = total_amount - paid_amount`. `revenue.amount` remains received cash in paid_at period. All summary metrics are from one read-only snapshot; drill-down is a later independent snapshot.
- `GET /admin/users`: list additionally includes `staff_name` (string or null). No change to create/update DTO, audit metadata or auth/me. No new request fields, routes or role permissions.
- Edit, reset password and status controls use the same full versioned PATCH contract. Audit selects submit exact existing `actor_id`, `action`, `entity_type` filters; no new module aliases.

## v1.1.0 review
No new routes or request fields. Explicit bearer takes precedence during browser logout; stale/mismatched cookie is not revoked or expired. Invalid restore returns 401 without a Set-Cookie mutation; invalid token cannot authorize any request. See browser session section above. Postman method/path and JS syntax are validated by `python3 scripts/check-release.py`; execution is pending.
