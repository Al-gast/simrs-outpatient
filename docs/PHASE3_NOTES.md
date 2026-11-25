# Phase 3 — Master Data (v0.3.0)

Source checkpoint derived from Phase 2 ZIP. No clinical transaction endpoints, user administration, or operational reporting yet. Runtime verification deferred at user request.

## Implementation
- Seven whitelisted master resources: patients, medical-staff, clinics, doctor-schedules, procedures, medicines, lab-tests. List/detail/create/PATCH/DELETE and dedicated medicine stock adjustment.
- Server validates fields and IDs; prices are integer rupiah strings, bounded at 999999999999. Unknown input fields rejected. Search, status and applicable schedule/profession filters; pagination and deterministic ID order. Unique constraints reject duplicate codes/identity. MRN from sequence with nontruncating padding.
- Yii transaction and database row locks; all master writes additionally serialized via one transaction-level advisory lock for simplicity. This is a deliberate MVP throughput trade-off; high-volume clinical operations should use appropriately scoped locks. Phase 4 registration must coordinate with master deactivation and use a consistent lock order. Schedule overlap checked while master writes are serialized; linked active doctor accounts and active schedules block incompatible master changes.
- Delete unreferenced rows; fallback to deactivation via savepoint after FK violation. Nonzero medicine stock blocks deletion. Stock balance and movement/audit records committed atomically, negative balance rejected. Adjustment endpoint is not idempotent: after an ambiguous timeout, inspect balance and audit before retry. Future dispense must also lock or conditionally update medicine row.
- Frontend follows approved white sidebar/teal/spacious tokens, responsive table with horizontal scrolling, real data, search debounce, filters, pagination, form and detail drawers, confirmation, pending/error states. Route/role checks mirror backend; doctor patient lookup restricted by assigned visit. Doctor sees no identity/phone. Drawer-close unsaved changes confirmation implemented; full navigation-away protection remains pending.
- No schema change; all existing baseline tables and privileges reused. Node unchanged. Local PHP development server configured with four workers to permit concurrent HTTP requests (not production deployment).

## Verification actually executed
See `evidence/phase3-static-checks.txt`: PHP syntax, 42 baseline assertions, 133 new master authorization/DTO assertions, 174 total TypeScript policy assertions, 18 TS syntax/transpile checks, shell/JSON/Python checks. These are not integration tests. `master-policy.php` is dependency-free, with a minimal framework exception stand-in. Docker/Composer/PostgreSQL unavailable; dependency resolution and full TypeScript typechecking/build, API, SQL and browser runtime not executed. No screenshots or Postman results are fabricated.

## Pending execution on disposable dummy database
1. Build and start Compose; run Phase 2 smoke and grants checks.
2. Run `python3 scripts/master-smoke.py` for master CRUD, role checks, duplicate identity, schedule collision, direct stock write rejection, two concurrent adjustment requests, and referenced medicine deactivation. Script creates dummy audit/movement records; use disposable dummy DB only. HTTP parallelism does not prove overlapping database lock acquisition; add deterministic database-level concurrency verification in QA.
3. Import `postman/MasterData.postman_collection.json` with existing environment. Execute admin login/list/negative validation/logout probes. Save actual results.
4. Verify rendered UI on desktop/mobile, drawers, error messages, pagination and keyboard interaction. User management and full clinical workflow remain later phases.

## Next phase
Registration and queue: derive doctor/poli and fee from validated schedule, duplicate-visit DB constraint, atomic queue counter and transactional creation. No final submission before runtime verification.
