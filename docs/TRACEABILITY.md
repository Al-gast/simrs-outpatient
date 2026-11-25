# Requirement traceability — REAL CASE TEST, PT Inova Medika Solusindo

Source: original five-page assignment, sections A–G. Status **S** = source implementation present, **P** = planned/pending evidence, **D** = documentation included. S does not mean runtime pass. Paths below are relative to repository root.

| Requirement | Source / deliverable | Verification | Status |
|---|---|---|---|
| A, E1–5: React, Yii, Node, PostgreSQL, REST | frontend, backend-yii, backend-node, database, docker-compose.yml | qa-runtime.py build + health | S; P runtime |
| B1: patients/MRN, staff, clinics, schedules, procedures/tariffs, medicines/stock/prices; CRUD/search/filter/validation/duplicates | MasterController, MasterService, MasterPolicy, MasterData UI; schema | master-smoke.py, MasterData collection | S; P runtime |
| B2: MRN/identity lookup, register, clinic/doctor schedule, automatic queue, status/history | VisitController, VisitService, Visits.tsx | visit-smoke.py, Visits collection | S; P runtime |
| B3: doctor queue, complaint/anamnesis/vitals/physical exam/primary+secondary diagnoses and history | ClinicalController, ClinicalService, Clinical.tsx | clinical-smoke.py, Clinical collection | S; P runtime |
| B4: multiple master procedures/tariffs, invoice components | ClinicalService, BillingService, visit_procedures | clinical-smoke.py, billing-smoke.py | S; P runtime |
| B5: Rx names/dose/frequency/quantity, validation, dispense, decrement, drug history | AncillaryService, AncillaryEditor/Queue, stock_movements | ancillary-smoke.py | S; P runtime |
| B6: lab request/list/result/history, integration API | AncillaryService, backend-node/src/laboratory.ts, clinical history | ancillary-smoke.py, End-to-End collection | S; P runtime |
| B7: four cost components, auto total, cash/noncash simulation, unpaid/partial/paid, installments, dedup, history, print | BillingService, Billing.tsx, invoice/payment tables | billing-smoke.py + browser print | S; P runtime |
| B8: visits, by clinic, procedures, revenue, drug usage, payment status, period and drill-down | backend-node/src/reporting.ts, Reports.tsx, reporting views | report-admin-smoke.py, Reporting-Admin collection | S; P runtime |
| B9, E6: auth/authorization, 6 roles, validation | AuthService, RolePolicy, AdminService, Node auth, route guards | smoke.py, security-smoke.py | S; P runtime |
| C1–5: lab/external REST, dashboard API, middleware, consistent errors, API docs | laboratory.ts, reporting.ts, auth.ts, api.ts, docs/API.md | health, collection, outage smoke | S/D; P runtime |
| D1: duplicate registration | VisitService + unique constraints | visit-smoke.py | S; P runtime |
| D2: concurrent stock | AncillaryService + conditional update | ancillary-smoke.py | S; P runtime |
| D3: duplicate payment | BillingService + UUID ledger | billing-smoke.py | S; P runtime |
| D4: edit after billing | ClinicalService + invoice revision | billing-smoke.py | S; P runtime |
| D5: service outage | Node timeout, retry and Core fallback | ancillary-smoke.py --outage, manual ambiguity test | S; P runtime; no durable offline queue |
| D6: performance | indexes, bounded aggregates, pagination, explain-reporting.sql | SQL plan + measured benchmark | S; P measurement |
| E7, F5: Postman API tests | postman/*.json | execute/export results locally | S collections; P evidence |
| E8, F1: Git and accessible repository | .gitignore, GITHUB_SETUP.md, ZIP | initialize/commit/push and verify evaluator access | P remote |
| E9–10: libraries and Yii/Node split | package manifests; ARCHITECTURE.md | dependency resolution; review | D/S; P build |
| F1: three source code sets | frontend, backend-yii, backend-node | ZIP manifest | S |
| F2: schema, relations, ERD, migration/SQL | database/schema.sql; three Yii migrations; ERD.md | clean migration + round-trip latest | D/S; P runtime |
| F3: architecture, communication, API, installation, technical decisions | README, ARCHITECTURE, API, TECHNICAL_DECISIONS | review | D |
| F4: screenshots, full workflow, invoice/receipt, Node evidence | DEMO_WALKTHROUGH, SUBMISSION_CHECKLIST | capture actual screenshots and response | P |
| F5: test cases, Postman evidence, critical unit/integration | TEST_CASES, TESTING, static evidence, scripts | static log; execute QA/Postman | D/S; P runtime |
| G: 72h from receipt, dummy data, email subject | README, seed, SUBMISSION_EMAIL.md | candidate verifies actual deadline and submits | D; P submission |

No external Git repository, actual screenshots, printed invoice, Postman execution or performance results are fabricated. Browser print supports saving PDF; no dedicated report export. Optional UI/filter features present in source.
