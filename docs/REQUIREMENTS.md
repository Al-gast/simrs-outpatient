# Requirements & acceptance criteria

Source mapping: original PDF sections B (modules), C (Node), D (technical cases), E (stack), F (outputs), G (dummy data). All rows below are planned, not implemented. UI and API must demonstrate the workflow with six demo users.

| ID / source | Functional scope | Acceptance criteria |
|---|---|---|
| R01 / B1 | Patients, doctors/medical staff, clinics, schedules, procedures, medicines; lab test master added for pricing | Create/list/update/deactivate or delete unreferenced master records; search/filter/paginate; required values validated; duplicate MRN, identity if provided and master codes rejected; stock/price nonnegative. History remains readable. |
| R02 / B2 | Patient registration & queue | Search MRN/identity/name; choose active clinic/doctor/schedule on current local date; server generates next queue; list queue status and patient visit history; simultaneous duplicate visits yield one visit. |
| R03 / B3 | EMR | Assigned doctor sees queue, records complaint, anamnesis, vitals, physical exam, primary and multiple secondary diagnoses; history is retrievable; changes versioned and audited. |
| R04 / B4 | Medical procedures | Multiple procedures and quantities per visit; server snapshots tariffs; invoice includes all committed procedure lines. |
| R05 / B5 | Pharmacy | Create prescription with dosage, frequency, quantity; validate stock at prescription and again on dispense; stock unchanged before dispense; all-or-nothing dispense with movements; repeated requests do not reduce stock twice; history available. |
| R06 / B6 | Lab | Doctor requests one or more tests; lab staff list orders and enter results; doctor can see results in visit history; Node integration supports read and result submission through Yii. |
| R07 / B7 | Billing | Server generates one invoice with registration, procedure, completed lab and dispensed medicines; cash/non-cash simulation, partial payment, unpaid/partial/paid status; reject overpayment and duplicate transaction; list patient payment history; print invoice and per-payment receipt. |
| R08 / B8 | Dashboard | Node API returns visit count, counts per clinic, procedure quantity, collected revenue, medicine usage, invoice payment statuses; inclusive local-date filters and drill-down paginated details. |
| R09 / B9 | Auth | Six roles; login/logout; role-specific UI and server permission checks; assigned-doctor scope; invalid or revoked token rejected. Admin manages users. |
| R10 / C | Node service | REST lab + dashboard endpoints, authentication and Zod validation, consistent JSON errors and docs; no core writes from Node. |
| R11 / D1–D6 | Technical problems | Concurrency, idempotency, audit, integration failure and performance evidence; see test plan and workflow. |
| R12 / E–G | Delivery | Three sources, accessible Git repo, schema/ERD/migration, architecture/API/setup/decisions, actual screenshots, invoice/receipt, Postman evidence and critical tests; dummy data only. |

## Non-functional goals (proposed)

- Correctness first: database constraints and transactions supplement request validation.
- Security: backend RBAC, least privilege, parameterized SQL, password hashing, no patient data or credentials in logs. No real patient data.
- Maintainability: thin controllers, domain services, shared response shape, migration history; no duplicated transaction logic.
- Usability: consistent navigation, Indonesian labels, clear pending/loading/error/empty states, print layout.
- Performance: indexed filters, bounded pages, aggregate queries, no per-row query loops; measure before asserting latency.

## Out of scope / limitations

Inpatient, insurance/BPJS, SATUSEHAT, real payments, real lab devices, multi-facility, advanced inventory batches/expiry, ICD catalog (free-text diagnosis), cancellations/refunds and post-payment financial adjustment UI. One outpatient visit per patient per clinic per local date, one prescription per visit, one invoice per visit. A hospital may allow repeat visits and more complex workflows; those are deliberately not modeled. Zero-total invoices are supported and close without payment. Export PDF is optional; browser print is required. No deployment requirement in PDF.
