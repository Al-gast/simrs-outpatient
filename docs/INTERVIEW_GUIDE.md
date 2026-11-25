# Technical interview preparation

Explain the actual implementation in your own words; inspect services and run tests before claiming results. Do not claim to have personally authored or measured anything you cannot explain. The assignment focuses on flow, logic, code organization, CRUD and authentication.

| Question | Answer points / code to inspect | Follow-up / trade-off |
|---|---|---|
| Why two backends? | Yii2 authoritative write service; Node lab bridge + reporting view reads; React REST | Shared core auth availability; no distributed DB writes |
| Why not MAX(queue)+1? | Atomic counter UPSERT under transaction and two unique constraints, VisitService | Counter rollback; one visit per patient/clinic/day |
| How does stock race work? | Visit lock, sorted item IDs, conditional UPDATE and stock ledger, AncillaryService | No reservation, all-or-nothing; deadlock retry |
| What if response lost after payment? | Same UUID/fingerprint replay before balance validation, BillingService | Different key not automatically same transaction; reconcile history |
| How do clinical versions work? | Single shared EMR version for clinical writes; 409 stale | No auto merge, reload/reconcile |
| Can doctor edit after invoice? | Unpaid procedure revision with invoice lock and revision; diagnosis-only changes after payment | No refund/credit note; freeze cost after first payment |
| Why integer Rupiah? | Bigint stored, decimal string over JSON, bounded quantities | Avoid floating precision; validate server values |
| What if Node fails? | Timeout, same-key retry, direct Core fallback; no false ack | No durable queue; client needs retention and reconciliation |
| What prevents IDOR? | Backend role and assigned doctor scope; 404 for inaccessible resources | Authorized clinical history only; separate DTO projections |
| How does logout work? | Token_version increment; Node introspection; no token in localStorage | Revokes all sessions; in-flight requests may finish |
| Why audit append-only? | Runtime role can insert/select, not update/delete ledger | Owner can mutate; no cryptographic proof or compliance claim |
| What does revenue mean? | Sum recorded payments by paid_at WIB; not invoice value | Status report current state of selected visit cohort |
| How do you optimize reports? | Independent aggregates, half-open timestamp ranges, indexes, pool, bounded dates and pagination | No measured SLA yet; explain plans with actual dataset |
| What tests prove concurrency? | Parallel HTTP barriers + SQL invariants; static policy checks separate | Sequential Postman can't prove race; actual run evidence required |
| What is not production ready? | No real data/payment gateway, lockfiles/advisory checks and runtime pending until verified, no offline durable queue | TLS, backups, operational monitoring, privacy, external security assessment needed |

## Demo strategy (10–15 minutes)

Show architecture and ERD briefly, then one complete dummy visit using `DEMO_WALKTHROUGH.md`. Demonstrate partial and final payments and actual invoice print, Node integration response and report filters. Present actual QA results and one race test. Explain limitations openly. If something failed, describe observed error, hypothesis, verification, root cause and fix rather than hiding it.
