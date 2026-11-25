# Manual QA and evidence checklist — run after isolated runtime QA

All rows are **NOT RUN** in the authoring environment. Use dummy data only. Record date, browser/version, device, observed behavior, PASS/FAIL and screenshot reference. Do not invent evidence. Use Chrome and Safari on Mac if available.

| ID | Steps | Expected |
|---|---|---|
| UI-01 | Open localhost:3000, login each of six demo roles. | Correct landing and navigation; no unauthorized menu or console error. |
| UI-02 | Open forbidden routes manually, log out, reload, back navigation. | Forbidden screen or login; no protected cached content. |
| UI-03 | Admin creates patient, edits data, searches, filters and paginates. | Validations clear, data persists, duplicate identity rejected. |
| UI-04 | Adjust medicine stock; interrupt request response and retry same attempt. | One movement only; replay indicated; changed-body key rejected. |
| UI-05 | Registration: select patient, active clinic and available schedule. | Queue generated, duplicate visit rejected, visit visible to assigned doctor. |
| UI-06 | Doctor fills record, adds procedures, Rx and lab; two tabs edit same version. | Stale update conflict; draft remains visible; refresh explicitly. |
| UI-07 | Lab enters dummy results via Node; stop Node, retry or use Core fallback, restart Node. | No duplicate result; error truthful; no data mutation from Node. |
| UI-08 | Pharmacy checks and dispenses prescription. | Stock decreases once, second attempt replays, unavailable stock blocks. |
| UI-09 | Cashier opens invoice; compare four cost categories with completed services. | Accurate price snapshots and totals. |
| UI-10 | Try decimal, separators, negative, overlong input, overpayment. | No silent normalization/truncation, submit unavailable or server rejects. |
| UI-11 | Record partial payment then final payment; retry after reload/login. | Correct remaining amount; stable retry key in same tab; complete only when paid. |
| UI-12 | Revise unpaid procedure from doctor workspace while cashier views old revision. | Old invoice revision rejected; reload required; later paid invoice cannot change cost. |
| UI-13 | Print invoice and each payment receipt in Chrome/Safari; save PDF. | A4-readable output, no clipped amounts, correct timestamps and status; no clinical payload. |
| UI-14 | Dashboard period and all six drill-downs; compare against SQL QA evidence. | Correct source-backed totals, no fake statistics; no error when zero rows. |
| UI-15 | Admin creates/resets/disables users; try old token. | Revocation and role checks; self-edit blocked. Audit metadata only. |
| UI-16 | 1440×900, 1024×768, 375×667, keyboard Tab/Shift+Tab. | White sidebar/teal/spacious layout retained; mobile drawer usable; skip link and focus visible. |
| UI-17 | Use DevTools Network/Console on each main flow, verify CSP. | No unexpected JS error, blocked intended API request, or leaking sensitive payloads. |
| UI-18 | Open drawer, change unsaved form, close/reload/route navigation. | Confirm where implemented; explicitly record remaining SPA navigation-guard gap. |

## Evidence required for technical-test handoff

- Run all Postman collections with a local dummy environment; remove tokens/password from exports. Do not equate Python HTTP scripts with Postman execution.
- Capture actual screenshots of six role workspaces, one end-to-end dummy visit, invoice/receipt and Node integration response. Do not substitute chat mockups for actual screenshots.
- Keep `qa-results/<run>/summary.json` and relevant logs, scrub credentials and dummy PII if necessary. Capture actual SQL query plans; describe dataset size and do not extrapolate to large workloads.
- Generate lockfiles after successful dependency resolution, run advisory review, commit code and evidence to an accessible Git repository. Keep `.env`, `node_modules`, `vendor`, `qa-results` out of Git; copy selected scrubbed evidence into docs if needed.

### v1.0.1 targeted browser UAT (pending)

| ID | Test | Expected |
|---|---|---|
| V101-UI-01 | Log in each role, reload a protected route and navigate back | Same role and route restored; no flash of protected data before validation |
| V101-UI-02 | Log out then reload/back | Login required; old bearer rejected by Core and Node |
| V101-UI-03 | Reset/deactivate dummy account in separate browser profile, reload its old session | Restore rejected; no privileged data shown |
| V101-UI-04 | Stop Core temporarily, reload, then start Core and click Coba lagi | Error/retry, not automatic logout on transient failure |
| V101-UI-05 | Inspect DevTools Application storage and Network | No bearer in localStorage/sessionStorage; HttpOnly Strict path-scoped cookie; allowed-origin credentialed calls only |
| V101-UI-06 | Create coded master without code; repeat concurrently in API test | Unique auto codes; old codes unchanged |
| V101-UI-07 | Toggle active/inactive and use strict delete on referenced dummy master | Status changes separate; linked delete rejects without status change |
| V101-UI-08 | Save first procedure/Rx/lab with no reason, edit and remove after save | First save succeeds; subsequent save requires reason; stale writes rejected |
| V101-UI-09 | Clear a saved section, add again | Reason remains required; audit and version preserved |
| V101-UI-10 | On upgraded UAT DB verify INV-2 paid, INV-1 unpaid, OB001 stock 90 (if data unchanged since prior UAT) | Existing transactions and user records preserved; no new seed rows |

## v1.0.2 targeted presentation regression (pending Mac runtime)

Run on dummy data after the final cumulative upgrade. Check Chrome and Safari where available at 1440×900, 1280×800, 1024×768, 768×1024, and 375×667/390×844. These are test targets, not results. Keep original business/API assertions and session checks from v1.0.1.

| ID | Test | Expected result |
|---|---|---|
| UI102-01 | Admin expand/collapse Master Data | Exactly seven children, no child icons; selected route teal; parent opens when navigating to master directly. |
| UI102-02 | Six roles, including collapsed desktop dropdown and mobile menu | Only allowed master children; cashier no Master Data; route guards still deny forbidden direct URLs; navigation works with keyboard. |
| UI102-03 | Create/edit/detail for all seven masters and user account | Centered modal, two-column master forms on desktop, one column on mobile; close/cancel/save keyboard behavior; no clipping at viewport edges. |
| UI102-04 | Form changes, invalid fields, cancel, save while pending | Existing validation and discard confirmations work; clicking mask does not close; no duplicate request; required errors readable. |
| UI102-05 | Register patient; open detail and history while registration modal open | Picker/dropdown appears above modal, history closes back to registration draft; save once; no change to same-day rules. |
| UI102-06 | Icon actions hover/focus/touch | Label/tooltip and keyboard focus; correct permission visibility; business-critical actions retain visible text; no unexpected action on click. |
| UI102-07 | Currency typing | Type 150000 progressively, display 150.000, form value/API amount string 150000; edit middle, backspace at grouping, select-all replace, clear. |
| UI102-08 | Currency paste/invalid | Paste 150.000 accepted as explicit thousands, 1.5/1,5/-1000/abc/1.000,50/01.000 rejected. Over-limit and over-balance rejected, no payment written. Confirm exact payload through Network tab. |
| UI102-09 | Stock direction and preview | Add 50, subtract 30, preview correct, zero/negative quantity invalid; excessive subtraction still denied by server. Check ledger. |
| UI102-10 | Uncertain stock retry | Pending signed payload/key stays unchanged, input frozen and projection hidden; retry does not double-adjust. |
| UI102-11 | Date/time | Calendar dates stay same across device timezone; timestamp uses WIB; native date filter sends ISO unchanged; no invalid date or duplicate WIB. |
| UI102-12 | Search, filter and empty states | Search placeholder visible at target widths; role/date/status/poli filters maintain results; empty state reflects filter; reload works. |
| UI102-13 | Badge and link | Compact status tags, consistent semantic colors across doctor/visit/billing/report, accessible text; links teal. |
| UI102-14 | Ancillary result/dispense | Centered modal, multiline lab results preserved in doctor view; busy/confirmation/retry safety retained; stock decremented exactly once. |
| UI102-15 | Responsive shell and tables | No full-page horizontal overflow; local table scrolling available; modal footer and navigation reachable; 200% browser zoom usable. |
| UI102-16 | Session and existing UAT data | Refresh restores session (v1.0.1); prior invoices, stock, patients and audit remain unchanged after migration. |

Do not mark these cases PASS until actual browser/device execution. Record screenshot evidence with dummy data only. Print redesign, clinical guided workflow, dashboard drill-down and administration-specific redesign are later-phase scope.

## v1.0.3 targeted clinical and billing regression — pending actual runtime

Use new dummy visits (one without ancillary services and one with full services). Do not modify the existing paid invoice merely to test the new UI.

| ID | Action | Acceptance |
|---|---|---|
| WF103-01 | Open doctor visit from queue; refresh a saved visit; test other roles and direct URLs | Dedicated page, correct role/assignment; saved data restored; forbidden route/API denies unauthorized role/visit. |
| WF103-02 | Fill initial record, invalid values then valid values; save | Validation, no finish button on record, success advances, version increments only on accepted save. |
| WF103-03 | Click optional empty steps' Tidak diperlukan · lanjutkan | No unnecessary writes; review shows no services and registration-only estimate. |
| WF103-04 | Add actions, Rx and lab; save each | Success advances, version increases; previous steps show persisted data; review estimates exact totals. |
| WF103-05 | Modify draft, attempt previous/next/sidebar/browser Back/reload | No silent loss; cancel retains draft; explicit discard allows navigation; clean navigation no unnecessary prompt. |
| WF103-06 | Force API rejection/stale version or interrupted save in isolated QA | Draft retained, no auto-advance on failure; review blocked until fresh state. |
| WF103-07 | View history during active stage, then close history | Stage/draft unchanged; other-patient data inaccessible. |
| WF103-08 | Review and cancel finish, then confirm | No finalization on cancel; one server transition; pending services vs ready for billing; optional no-service visit supported. |
| WF103-09 | Reopen completed and paid visit | Read/correction rules unchanged, no new Rx/lab; invoice revision only before first payment; contextual empty messages. |
| WF103-10 | Pharmacy detail before/after dispensing and lab result | Unit and subtotal/total accurate; stock decreases once; Node result retry/fallback preserves same key; result line breaks retained. |
| WF103-11 | Search old and padded invoice number | Same invoice displayed; raw API identifiers unchanged. |
| WF103-12 | Cashier/admin open unpaid invoice direct URLs | Cashier can transact, admin sees no payment or issue action; wrong role blocked. |
| WF103-13 | Pay 150000 of 350000, then 200000 | 42.8% (truncated) then 100%; correct balance, two unique receipts; replay remains safe. |
| WF103-14 | Navigate away with pending payment in isolated QA, return and retry | Same stored key/body; no duplicate charge; no new transaction until resolved. |
| WF103-15 | Print invoice and both receipts with browser header/footer disabled | A4, readable order, exact totals, no sidebar; verify long values and multi-page table; individual receipt does not assert full settlement. |
| WF103-16 | Desktop 1440×900, tablet 1024×768, phone 375×667, keyboard | Responsive steps, readable tables, visible progress, focus and scroll at stage changes. |

All above are planned, not recorded as passed. Preserve original UAT volume, compare previously paid INV-2 and unpaid INV-1, and rerun isolated integration suite after final cumulative upgrade.

## v1.0.4 targeted dashboard and administration — runtime pending
| ID | Scenario | Expected |
|---|---|---|
| ADM104-01 | Dashboard 1–24 Nov 2026 with original UAT rows, if unchanged | 2 visits, Rp350k cash, Rp375k billed, Rp350k paid, Rp25k outstanding; paid/unpaid 1 each |
| ADM104-02 | In isolated fixture, payment outside visit-date period and receipt from earlier visit | Cohort paid includes later payment, cash includes only paid_at in period; no false subtraction |
| ADM104-03 | 1-day, empty, sparse and 366-day periods | Exact dates/zeros, readable graph, accessible data table; no fake data |
| ADM104-04 | Invoice donut empty/partial/paid/unpaid | Count-based proportions, status legend/table exact |
| ADM104-05 | Drill-down six metrics and per-clinic link, page 2 | Centered modal, same applied period/scope, raw privacy-safe fields, close restores viewport |
| ADM104-06 | Reporting service unavailable then retry | Error, not zero or fabricated totals; retry works |
| ADM104-07 | Admin users list, search/filter; inactive linked doctor | Actual staff name or neutral fallback, no N+1; role selection explicit for create |
| ADM104-08 | Edit dummy account, omit password | Name changes; password/status preserved, old token revoked, version increments |
| ADM104-09 | Reset dummy account password only | Old login/token rejected; role, staff, active unchanged |
| ADM104-10 | Deactivate/reactivate dummy account, stale version, self account | Inactive login denied; reactivation works; stale 409; own row has no actions; server still rejects self PATCH |
| ADM104-11 | Audit actor search by username/name, inactive account, exact action/entity, pagination, reset | Matching records; filters apply only on submit; reset current month |
| ADM104-12 | Audit detail known and legacy action | Localized or raw fallback; actor/entity IDs and request ID; no clinical data/password/token |
| ADM104-13 | Admin monitoring and non-admin direct routes/API | Read-only monitoring, non-admin denied report/admin endpoints |
| ADM104-14 | 375px/mobile, tablet, desktop and keyboard | Charts/table usable, modal fits, dropdown search and action tooltip focus works |

## v1.1.0 release-specific cases
See FINAL_UAT.md for targeted regression. In two tabs, log in to different dummy accounts sequentially; old tab must clear local state and require login on reload, new tab remains authenticated; stale bearer logout must not revoke newer different-account cookie (HTTP smoke). Test clean single-tab refresh/logout. For simultaneous different-role work use separate browser profiles. Pending actual browser execution.
