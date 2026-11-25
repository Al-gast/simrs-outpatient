# Phase 4 — Registration & Queue (v0.4.0)

## Scope and decisions

Based on exact Phase 3 ZIP. Phase 1 design restricts booking to today's WIB date, one visit per patient/clinic/date. Future bookings, cancellation, return visits on same day and queue calling screen are out of MVP. The queue sequence is per clinic/day, not per doctor. The UI label is clinic code + padded numeric queue (e.g. UMUM-001). Clinic code/name and schedule times are current master values; immutable historical values include IDs, date, numeric queue and registration fee. Changing clinic code can change the displayed label in old visits; no historical label snapshot in this checkpoint. Doctor sees only assigned visit history; full clinical history is Phase 5. No UI for clinical notes yet. Registration, admin and doctor read separate role-guarded views of the same core API. No Node change is necessary.

## Transaction design

1. Validate request IDs as decimal strings. Ignore no client fields: unknown fields rejected.
2. Begin transaction; shared advisory lock coordinates with Phase 3 exclusive master mutation lock.
3. Validate active patient, doctor profession/active, clinic active and schedule weekday/active, using server date Asia/Jakarta.
4. Atomic upsert counter `(clinic_id,visit_date)` and retrieve number.
5. Insert visit with fee snapshot and default waiting; targeted duplicate conflict returns 409; queue uniqueness collision returns 409. Rollback all including counter on conflict or audit failure. Two bounded retries for deadlock/serialization failure; no automatic HTTP retry.
6. Write audit metadata (no clinical notes or identity number), commit and return joined DTO.
7. Start exam under row lock for assigned doctor; waiting→in_exam; same in_exam replay does not add duplicate audit or mutate timestamp.

The shared/exclusive lock coordinates application writes only; out-of-band SQL must not bypass invariants. The schema has both relevant uniqueness constraints already. No migration change; Phase 1 baseline unchanged. Counters serialize per clinic/date; master changes may briefly wait behind registrations. No throughput claim without runtime load test.

## Test plan and evidence

`docs/evidence/phase4-static-checks.txt` contains actual checks. `scripts/check-static.sh` runs PHP syntax, dependency-free PHP policy tests and strict compilation/execution of pure TypeScript policy modules; the React application itself is not typechecked end-to-end. Postman collection is prepared, not executed. `scripts/visit-smoke.py` tests login roles, rejection matrix, invalid body/filter, inactive patient/schedule, weekday mismatch, concurrent duplicate requests with different schedules, distinct concurrent queue numbers, rollback without gaps, price snapshot, identity search, scope, history and parallel start. It creates five persistent dummy visit fixtures, and uses the same seeded registration account from separate HTTP connections because user-management CRUD is pending. Run only on a disposable dummy DB; fixture needs a free 30-minute slot for the seeded doctor. Run away from WIB midnight. Multi-worker PHP Compose setting supports concurrent requests; script demonstrates parallel HTTP clients, not instrumentation of DB lock overlap. No API, PostgreSQL, Composer, Docker, browser or dependency-resolved typecheck executed in this environment.

## Remaining work / known limitations

- Phase 5 EMR/treatments; Phase 6 pharmacy/laboratory; Phase 7 billing; Phase 8 reporting; later user management, unsaved navigation guard, master stock adjustment idempotency, and full runtime testing.
- Registration does not implement idempotency keys (duplicate retry returns conflict; UI advises checking existing queue). Concurrent request outcomes and audit atomicity need PostgreSQL verification.
- Pickers return up to 20 search results and support narrowing via remote search; queue/history use server-side pagination. Queue status refreshes every 30 seconds or via manual refresh; no WebSocket.
- Source checkpoint only, no screenshots claimed, no lockfiles or Git remote generated. Prior Phase 2/3 runtime limitations persist. User elected to defer integrated runtime checks.
