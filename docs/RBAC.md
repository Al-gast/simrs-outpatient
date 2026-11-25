# Role-based access control

Legend: M = manage, R = read, W = workflow actions, O = only assigned doctor's visits, — = forbidden. Admin is not a universal clinical/payment actor. Backend enforces every permission; UI menu is only a convenience.

| Resource | admin | registration | doctor | pharmacy | laboratory | cashier |
|---|---|---|---|---|---|---|
| Users, roles | M | — | — | — | — | — |
| Patients master | M | M | R/O | — | — | — |
| Clinics, staff, schedules | M | R | R | — | — | — |
| Procedures, medicine, lab master | M | — | R | R medicines | R lab tests | — |
| Stock adjustments | M | — | — | — | — | — |
| Registration & queue | R | W | R/O | — | — | — |
| Clinical record / diagnosis | — | — | W/O | — | — | — |
| Visit procedures / prescription / lab request | — | — | W/O | R prescription | R lab order | — |
| Dispense | — | — | R/O | W | — | — |
| Lab result & integration | — | — | R/O | — | W | — |
| Invoice & payments | R | — | — | — | — | W |
| Dashboard & aggregate drill-down | R | — | — | — | — | — |
| Audit metadata (no clinical content) | R | — | — | — | — | — |

All authenticated accounts: own profile and logout. Registration can view patient's administrative visit history (not clinical notes). Doctor's patient lookup requires an assigned visit; full historical EMR returned only for a patient with a current assigned visit or the doctor's own past visit, with audit of history access. For other clinical operations restrict by `visits.doctor_id = users.staff_id`. Pharmacy/lab/cashier see limited patient name/MRN and visit identifiers from their own module response, not general patient APIs or other module notes. Cashier may see billing descriptions/amounts, not diagnosis. Admin dashboard, visits and audit endpoints return non-clinical operational information; admin cannot prescribe, dispense, submit results or pay merely by being admin.

Master delete: unreferenced record -> 204 physical deletion; referenced record -> deactivate and return 200. Referenced medicine with stock cannot be deleted without inventory reconciliation. User disable instead of delete; don't disable last active admin. Doctor staff mapping must refer to profession doctor; reject changing linked active doctor's profession. Reject overlapping active doctor schedules for same weekday across clinics (serialize schedule writes by locking staff row). Reject changing schedule's doctor/clinic when referenced by visits. New master codes normalized uppercase/trim; usernames lowercase/trim; MRN generated via dedicated DB sequence; blank identity normalized null. Existing visits retain price snapshots and history.

## Route guard behavior

401 missing/invalid/revoked token; 403 authenticated but lacks role permission; 404 when an object outside assigned-doctor scope is requested, to avoid leaking existence. Every ID taken from path/body is validated and looked up within scope. React redirects unauthenticated users to login and renders forbidden state; no client-only authorization. Demo users seeded with distinct role credentials and dummy linked doctor; no public signup.

Phase 4 implementation: `/visits` UI monitoring route is admin-only; registration and doctor use their respective workspaces. Administrative history API is registration/doctor only; doctor history is restricted to their own visit rows until clinical history rules are implemented in Phase 5. Only assigned doctor can start examination; admin cannot start or register.

Phase 5: six `/visits/{id}/clinical*`, `/medical-record`, `/procedures` and `/finish-exam` routes enforce doctor role and assigned anchor. Cross-doctor history detail is read-only during an assigned in_exam visit today for the same patient. Outside that context history is limited to own prior records. Every historical detail access is audited. No clinical notes in admin/registration visit response; no UI or endpoint exposing raw clinical audit JSON.

Phase 6: doctor-only prescription and lab draft endpoints require assigned visit and in_exam state; lab draft freezes after first result. Pharmacy-only list/detail/dispense routes expose only necessary prescription and patient identity; laboratory-only list/detail/result routes expose only ordered tests and results, with Node role middleware and repeated Yii role enforcement. No admin clinical write privileges. Historical clinical access rules also govern drug and lab snapshots.

Phase 7: cashier may list/read/issue/pay billing; admin may list/read only (including print). All other roles are denied billing routes. Assigned doctor can revise procedures on a ready, unpaid visit through the existing clinical endpoint; no direct invoice/payment API access. Payment and invoice responses do not expose EMR, idempotency keys or payload hashes.

## Phase 8 administrator extensions
Dashboard summary/drill-down, user list/create/edit/deactivate/reset, and audit metadata are administrator-only. Core account mutations recheck active role and token version inside the shared master advisory lock. Node introspects live authorization. Non-admin access is denied even when React routes are called directly. No hard-delete of users; self-edit blocked to avoid losing last admin. Existing doctor identity binding is immutable. No clinical audit snapshots are exposed.
