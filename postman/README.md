# Postman execution guide (not yet run)

Use a running disposable dummy stack at localhost. Import `SIMRS.postman_environment.json` and select it. Fill local/current `demo_prefix` from the actual `.env` in the running stack's workdir; never share the value. Keep URLs as `http://localhost:8080/api/v1` and `http://localhost:3001/api/v1`. Do not point collections at production.

## Recommended end-to-end run

Import `End-to-End.postman_collection.json`. Use Collection Runner, one iteration, sequential, from first request to last. It has 51 requests with status/shape/business assertions. It creates unique synthetic masters, patient, visit, prescription, lab result and payments. The date is read from Core API, and the assigned active schedule is selected dynamically. It calculates the expected invoice from actual registration fee + fixed created procedure/medicine/lab prices. It tests duplicate registration, lab same-key replay, dispense replay and stock, partial cash, same-key payment replay/conflict, final noncash, paid invoice, reporting and RBAC. It logs out six roles at end. This is sequential, not a concurrency test.

Run on a fresh or retained disposable QA DB. Do not run selected middle requests without their variables. If a request fails or times out, stop and inspect (a failed assertion does not necessarily stop the runner). For ambiguous payment/lab/stock writes, retain original key/body and query current state; retry using the replay request or disable key regeneration in the original request. Do not rerun a key-generating request blindly. If resetting the entire workflow, use a new dummy patient/masters only after reconciling the previous attempt. Failed runs can leave token values in collection variables; clear them manually. Do not export current secrets. Runtime evidence is pending.

## Focused collections

| Collection | Prerequisite |
|---|---|
| SIMRS | Core/Node health, six seeded roles |
| MasterData | seeded admin and master data |
| Visits | seeded roles, visit list; validation probes |
| Clinical | set collection variable `clinical_visit_id` to existing assigned dummy visit |
| Ancillary | seeded roles; optional existing dummy lab item where applicable |
| Billing | set `billing_visit_id` to existing eligible dummy visit with invoice |
| Reporting-Admin | set `report_from`, `report_to` to desired WIB period (defaults were authoring dates) |
| QA-Hardening | disposable DB; creates dummy medicine and adjusts stock |

Run focused collections in listed order, ensuring required state. Inspect individual test expectations; do not reuse keys for different transactions. Actual collection runner/export (or Postman CLI/Newman with compatible environment) is required for Postman evidence. Python smoke tests are separate. Screenshot run summary and relevant Node result response; export scrubbed results to docs/evidence/runtime. Clear environment secret/current token values and collection tokens from every export. Do not upload live credentials or real data.

## v1.1.0 source validation
`python3 scripts/check-release.py` checks all 9 collection files, request method/path mappings against Yii/Node route definitions, and JavaScript event syntax. It does not validate actual response behavior or count as Postman execution. Existing bearer collections remain valid with browser-specific session endpoints unchanged in shape; cookie-specific tests live in security-smoke.py and manual UAT.
