# Submission gate — PT Inova Medika Solusindo

The assignment requires an accessible Git repository, source code, DB/ERD/migrations, technical documentation, actual screenshots and service-flow evidence, invoice/receipt, Node implementation proof, test cases, actual Postman proof, and critical unit/integration tests. Deadline from assignment: 72 calendar hours from receipt; follow explicit HR deadline if provided. The previously recorded HR deadline is Wednesday, 25 November 2026, 14:00 WIB; verify it against the original email (the PDF itself states 72 hours). Use only dummy data.

## Release gate (v1.1.0 RC; no runtime PASS yet)

- [x] Source code and migrations included; technical documentation and ERD included.
- [x] Static regression evidence included (see evidence/v1.1.0-static-checks.txt for exact results).
- [ ] Existing UAT backup, business fingerprint diff and migration-only upgrade (see UPGRADE_V1.1.0.md).
- [ ] Targeted UAT (see FINAL_UAT.md).
- [ ] Install dependencies, generate real lockfiles, review advisories, build all services.
- [ ] Run isolated runtime QA, resolve failures; rerun, preserve actual summary and logs.
- [ ] Execute Postman collections; capture/export actual results.
- [ ] Complete manual UI/UX checklist, actual screenshots and print PDF.
- [ ] Publish accessible Git repository; verify evaluator access and clean clone instructions.
- [ ] Review evidence for secrets, real identities and misleading claims; send email with repository link.

## Suggested evidence filenames (all pending; names are suggestions, not existing screenshots)

| Filename | Required capture | Status |
|---|---|---|
| 01-admin-dashboard.png | Actual dashboard and date filter | Pending |
| 02-master-patients.png | Dummy patient/MRN table | Pending |
| 03-registration-queue.png | New visit and queue | Pending |
| 04-doctor-record.png | Saved dummy EMR and vitals | Pending |
| 05-doctor-services.png | Procedure/Rx/lab tabs | Pending |
| 06-lab-result.png | Lab result and order completed | Pending |
| 07-node-api.png | Postman or Network Node request response, redact bearer | Pending |
| 08-pharmacy-dispense.png | Dispensed Rx and stock change | Pending |
| 09-invoice-unpaid.png | Four actual charge categories | Pending |
| 10-payment-partial.png | Partial balance and receipt | Pending |
| 11-payment-paid.png | Paid/completed and two receipts | Pending |
| 12-invoice.pdf | Actual browser print output | Pending |
| 13-receipt-cash.pdf | Actual first receipt | Pending |
| 14-receipt-noncash.pdf | Actual final receipt | Pending |
| 15-reports.png | Actual drill-down | Pending |
| 16-user-audit.png | User roles and audit metadata | Pending |
| 17-postman-run.json / .png | Actual runner results | Pending |
| 18-runtime-summary.json | Scrubbed actual QA summary | Pending |
| 19-query-plans.txt | Actual EXPLAIN ANALYZE and dataset sizes | Pending |

Store selected captures in `docs/evidence/runtime/` (create after testing). Add an index with date, environment/browser, visit ID, expected vs observed, file name and status. Do not substitute illustrative screenshots or fabricated evidence. Do not include tokens, password prefix, secrets, or real patient data. If deadline arrives before full verification, disclose which items are pending and what did pass; do not claim all requirements were proven.
