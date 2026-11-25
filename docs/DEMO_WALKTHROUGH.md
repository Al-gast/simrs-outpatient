# Demonstration walkthrough — one dummy visit

Prerequisite: successful startup and migration, or retained isolated QA environment; use its actual password prefix. Open `http://localhost:3000`. Use a new dummy patient for each run (one visit per patient/clinic/day). Seed has Poli Umum (Rp25,000), doctor D001 with schedule every weekday 08:00–16:00, T001 consultation (Rp100,000), T002 general exam (Rp50,000), OB001 dummy medicine (Rp2,500/tablet), L001 lab (Rp150,000). Existing master changes can alter expected totals: check current prices first. Dates use WIB; registration checks matching weekday, not current clock within shift.

| Step / role | Action | Expected output / capture |
|---|---|---|
| 1 admin | Open dashboard, master clinics/doctor schedule/medicine, confirm stock >=10 | Actual initial metrics; available master data |
| 2 registration | Create new dummy patient with unique synthetic identity, birth date, sex; search by MRN/identity | Generated MRN; no real identity |
| 3 registration | Register patient to Poli Umum, D001, today's schedule | Queue label; status waiting; fee snapshot Rp25,000 |
| 4 doctor | Open own queue; start assigned visit, fill required complaint/anamnesis/vitals/physical/diagnoses, Simpan & lanjutkan | in_exam; record version 1 (new record) |
| 5 doctor | Follow steps: T001 + T002 (qty 1 each), Simpan & lanjutkan; OB001 qty 10 with dummy dosage/frequency text, Simpan & lanjutkan; L001, Simpan & lanjutkan; review. Optional steps may be skipped without creating an empty order. | Procedure total Rp150,000; Rx Rp25,000; lab Rp150,000; versions increase |
| 6 laboratory | Open lab queue, input clearly dummy free-text result via Node route | Result saved; completed lab order; Node HTTP success; capture actual Network/Postman response (redact token) |
| 7 doctor | Reload clinical workspace if needed, revisit the saved steps, review lab result, finish exam | services_pending if Rx pending; version check |
| 8 pharmacy | Open pending Rx, confirm stock, dispense | Stock decreases 10 exactly once; dispensed; ready_for_billing; automatic invoice |
| 9 cashier | Open dedicated visit invoice; confirm four category groups in order: registration, procedure, medicine, laboratory; first pay Rp150,000 cash | Total Rp350,000 at unchanged seed prices; partial, remaining Rp200,000 |
| 10 cashier | Pay remaining Rp200,000 non-cash (simulation) | Paid, balance zero, completed visit, two unique receipts |
| 11 cashier | Cetak invoice; print both receipts, save to PDF from browser; disable Headers and footers | Actual print preview and files; each receipt represents one payment, not necessarily full settlement |
| 12 admin | Filter dashboard to today; inspect six centered drill-downs, monitoring and localized audit detail | Actual aggregates include other test transactions; cash uses payment date, invoice totals use visit date; do not equate global totals with this single visit |
| 13 doctor | Open completed visit history and verify Rx/lab/EMR available; diagnosis-only correction if desired | History available subject to doctor scope; paid costs immutable |

For step 4 use clearly labeled synthetic values (e.g. BP 120/80, pulse 80, temp 37.2, respiratory rate 20, weight 65), free-text "Dummy" diagnoses and notes; not clinical advice. If lab was not processed before finish, visit stays services_pending until both services complete. Log out between roles (revokes all sessions for that role). Reload restores the original unexpired browser session; logout, expiry or account revocation requires login. Follow UI labels in actual running app, not chat mockups.

## Technical demonstration (optional after happy path)

Use isolated QA evidence to explain simultaneous registration, stock race, payment replay and revision vs payment; show actual exit statuses and SQL results. Demonstrate unpaid procedure revision on a *different* dummy visit, then try stale invoice revision. Do not edit a paid invoice. To show outage, use runner `--outage` or stop Node only on isolated dummy project, verify explicit Core lab fallback, then restart. Do not pretend actual lost response was reproduced unless recorded.

Record visit ID/MRN, queue, invoice revision/number, receipt numbers and correlation request IDs across evidence. No tokens, secrets, real patients or real transactions.
