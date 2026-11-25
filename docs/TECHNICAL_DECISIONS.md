# Technical decisions and six problem scenarios

Status: implemented in source, runtime proof pending. Read the corresponding services and scripts, not only these explanations.

| Scenario (assignment D) | Evidence in source | Decision / trade-off | Runtime proof to capture |
|---|---|---|---|
| D1 Duplicate registration | VisitService, queue_counters, visits unique constraints | Atomic UPSERT counter and insert in one transaction; one visit per patient/clinic/day; numeric queue scoped to clinic/date. Rollback prevents consumption on failed insert. | visit-smoke.py: concurrent duplicate + distinct visits, query counter and uniqueness |
| D2 Stock race | AncillaryService, MasterService, stock_movements, stock_adjustment_requests | Visit lock, sorted medicine IDs, conditional stock update, all-or-nothing dispense; no stock reservation at prescription; adjustment UUID replay. | ancillary-smoke.py, security-smoke.py, verify-invariants.sh: one winner and rollback of multi-item failure |
| D3 Double payment | BillingService, BillingPolicy, payments unique UUID | Lock visit then invoice; compare key and fingerprint before validating new payment; compute balance under lock; positive integer IDR. Same key replay returns original payment ID/current invoice; different keys are distinct transactions if balance permits. | billing-smoke.py: same-key replay, two cashiers, sum and audit checks |
| D4 Edit after billing | ClinicalService, BillingService, medical_records.version, invoices.revision | Diagnosis-only amendments after exam; unpaid procedure revisions update invoice atomically; freeze costs after first payment. Stale clinical version/invoice revision conflicts. No refunds/credit notes. | clinical-smoke.py, billing-smoke.py: revision race, paid diagnosis correction, audit |
| D5 Integration outage | Node laboratory.ts/auth.ts, Yii AncillaryService | Synchronous forwarding with original token/key; bounded timeout; ambiguous failure means retry same payload/key or explicit direct Core fallback. Node no writes; no durable offline queue. | ancillary-smoke.py --outage: actual stop/recovery, replay; separate manual lost-response/timeout proof |
| D6 Performance | reporting.ts, migrations 000002/000003, explain-reporting.sql | Independent aggregates, bounded WIB periods, indexed ranges, pool, pagination, read-only snapshot; no Redis until measured. | report-admin-smoke.py and EXPLAIN ANALYZE; report dataset sizes and measured latency only |

## Additional ADRs

- One monorepo, three application services; explicit REST contracts instead of duplicated business logic. Simpler evaluation, coupled auth availability.
- HS256 JWT with live account/token_version check; Node introspects Yii. One role per account and scoped doctor access. In-flight authorized requests are not cancelled on revocation.
- Price snapshots and bigint integer Rupiah, decimal-string serialization; sequences may have gaps; queue counter is transactional.
- Append-only ledger privileges for core runtime; owner still has administrative power. Audit metadata endpoint excludes clinical before/after and reason. Not a tamper-proof or compliance-certified system.
- Current invoice status for selected visit cohort, not historical point-in-time balances. Revenue means money received; medication usage means dispensed, grouped by medicine, not summed across units.
- Master delete only when safe; otherwise deactivate. User accounts deactivate rather than delete. Doctor identity binding stays fixed. No general master optimistic locking.
- Local dummy scope: free-text diagnosis/results, one Rx and one lab order per visit, no partial dispense/result amendment, no payment gateway, no production hardening claim. Browser retry storage is tab-scoped. UI uses Ant Design with custom design tokens and browser print, not a server PDF service.

## v1.0.1 decisions (UAT-driven)

- Restore original two-hour JWT using a scoped HttpOnly cookie; no durable JS token, no new long-lived refresh token or DB sessions. Exact Origin + JSON POST gate reduces CSRF exposure. The explicit restore endpoint still exposes a bearer to same-origin JavaScript: XSS remains a threat, mitigated by CSP and code discipline, not solved by HttpOnly. Browser tabs share one cookie; use different profiles for distinct simultaneous roles. Localhost HTTP is a demo exception.
- Keep externally supplied code compatibility while making backend generation the default in UI. Per-resource monotonic PostgreSQL sequences avoid MAX+1 races; manual collisions skipped under master lock; gaps are acceptable. Clinic mnemonic remains optional because it is used in queue labels. No existing code migration.
- Strict deletion is opt-in for API compatibility; UI explicitly chooses it. Soft deactivate is an independent PATCH. Foreign keys and business dependencies remain authoritative.
- Reason requirement is decided using persisted section state and append-only audit history, under existing visit lock and optimistic version. Client flags are advisory only. No relaxation of invoice revision, record amendment or stock adjustment reason.

## v1.0.4 decisions
- Preserve separate financial date bases. Add exact billed/paid sums to existing group query rather than paginating all invoice rows or deriving cohort paid from cash receipts. Keep decimal strings; BigInt aggregation and consistency check in UI. Use native SVG/CSS for chart geometry; exact values separately accessible.
- Admin list joins existing staff by primary key (read-only), not N+1 requests or potentially truncated dropdown results. Keep full versioned PATCH and self/doctor protections for every distinct account action; no new mutation routes.
- Audit filter follows persisted entity semantics; labels are presentation only. Technical detail is the existing explicit metadata projection, never clinical payload.
