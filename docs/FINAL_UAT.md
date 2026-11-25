# v1.1.0 targeted UAT and evidence collection — all pending

Use the upgrade guide. Run historical read-only checks on preserved DB; use new dummy visits and accounts for write tests, and a disposable QA stack for race/failure tests. Use current WIB date for new registration and 24 Nov 2026 for original historical fixtures. No real health or payment data. Do not declare PASS without observation.

| ID | Gate | Expected | Evidence |
|---|---|---|---|
| R110-01 | Backup + migrate only | validated dump TOC, empty business fingerprint diff, 4 migrations, 3 HTTP health/UI checks | private backup, scrubbed migration log |
| R110-02 | Login, refresh, logout all six roles; stale tabs | same role/route after refresh; another login invalidates older tab locally; stale logout cannot revoke newer different-account cookie; old token after explicit logout rejected | actual screenshots and security HTTP suite |
| R110-03 | Auto master codes + manual compatibility; active/status/delete | no duplicate codes, references block strict delete, old codes/stock unchanged | master smoke + actual UI |
| R110-04 | Master sidebar and simple form | seven admin submenu items; role-filtered other menus; centered modal, icon/tooltip, mobile layout, no false dirty prompt | screenshots |
| R110-05 | New registration → doctor, no optional services | no duplicate visit/queue; save first record without reason; explicit skip; review then finish; registration-only invoice | screenshot + runtime suite |
| R110-06 | Full care visit | procedures/Rx/lab save and version updates; actual lab via Node; stock dispense once; four invoice categories | doctor review, Node network response, stock |
| R110-07 | Clinical amendments + stale version | reason required for persisted change incl clear/re-add; reject stale; diagnosis correction audited; procedure revision only before first payment | HTTP + UI |
| R110-08 | Cashier partial/full payment | exact balance/progress, same-key replay no duplicate; admin unpaid invoice read-only | invoice screenshot, receipts, negative RBAC |
| R110-09 | Print | A4 actual invoice and both receipts; correct nominal and identifiers; header/footer browser option off | PDFs |
| R110-10 | Original UAT read-only | historical Budi/Siti invoices, prior stock and dates retained (compare actual state before upgrade) | before/after UI |
| R110-11 | Reports | old fixture totals reconcile; all drilldowns and filter; period boundaries and empty charts; paid_at vs visit-date cohort | dashboard, SQL reconciliation |
| R110-12 | Admin and audit | staff name, explicit role, separate edit/reset/status, self protection; actor/action/entity filter; detail no sensitive fields | UI + HTTP |
| R110-13 | Failure and concurrency | duplicate registration, stock race, payment replay, stale invoice, Node outage & recover, DB grants/ledger invariants | isolated QA actual logs |
| R110-14 | Responsive and navigation | 375px/1024px/desktop, keyboard, clean navigation unblocked; dirty guard, retry data safe | actual browser evidence |
| R110-15 | Postman & submission | 51 end-to-end requests run and assertions verified; scrubbed evidence, accessible Git, email | actual collection runner, repo link |

Prioritize R110-01/02/05/06/08/10/11/13/15 if time limited, but disclose incomplete cases. Remaining detailed phase cases are in QA_MANUAL_CHECKLIST.md. This is a release gate, not evidence of passing tests.

## Evidence record template

| Case | Actual timestamp/WIB | Environment | Expected | Observed | PASS/FAIL/BLOCKED | Screenshot/log |
|---|---|---|---|---|---|---|
| R110-01 | — | — | — | Not run | PENDING | — |

Use existing `docs/SUBMISSION_CHECKLIST.md` filename map. Store selected scrubbed output in `docs/evidence/runtime/`. Never copy `.env`, dumps, raw bearer tokens, or complete Postman current values into the repository. Include failed results or open limitations honestly. Previous screenshots are v1.0.0 only, not v1.1.0 acceptance proof.
