# UI/UX specification — SIMRS Outpatient

Design version: 1.0 | Project checkpoint: v0.1.1-design | Status: user-approved direction; implementation pending.

## 1. Approved direction

Premium Healthcare SaaS, light/white sidebar, teal primary color, spacious density, light mode. No additional user notes. This replaces the earlier dark-sidebar concept. Use Indonesian UI copy, Inter with system sans-serif fallback, and Lucide outline icons. Professional, calm, clinical, accessible; no excessive gradients, glass effects, decorative animation or heavy shadows. Desktop-first operational workspace; responsive on smaller screens. This design is an evaluation MVP, not a certified clinical UI.

## 2. Visual tokens

The machine-readable companion is `UI_TOKENS.json`. Apply via Ant Design ConfigProvider and CSS custom properties in Phase 2; component overrides are intentional, not a reason to replace Ant Design. Use system fonts if Inter is unavailable (no runtime font network dependency required).

| Token | Value |
|---|---|
| Primary / hover / soft | #0F766E / #115E59 / #F0FDFA |
| App background / surface / border | #F8FAFC / #FFFFFF / #E2E8F0 |
| Heading / text / muted | #0F172A / #475569 / #64748B |
| Success / background | #15803D / #DCFCE7 |
| Warning / background | #B45309 / #FEF3C7 |
| Info / background | #1D4ED8 / #DBEAFE |
| Danger / background | #B91C1C / #FEE2E2 |
| Focus | #0F766E, 2px visible outline, 2px offset |
| Spacing scale | 4, 8, 12, 16, 20, 24, 32, 40 px |
| Sidebar expanded / collapsed | 264 / 80 px |
| Header height | 72 px |
| Desktop content padding / card padding | 32 / 24 px |
| Card / input / button radius | 16 / 10 / 10 px |
| Input / primary button height | 44 px |
| Table row minimum | 56 px (allow wrapping and taller rows) |
| Page title / section title / body / caption | 28/18/14/12 px |
| Font weights | 400 regular, 500 medium, 600 semibold, 700 bold |
| Shadow | 0 1px 3px rgba(15,23,42,.06), use sparingly |

Use tabular numbers and right-aligned monetary values. Primary CTA filled teal with white text, secondary outlined, destructive red only for destructive actions; low-emphasis actions text/ghost. Status indicators include text, not color alone. Decorative pale fills are not text colors. Maintain WCAG 2.2 AA contrast targets and test actual components (not yet verified). Inputs need discernible borders/focus; use #64748B where a border must alone identify a control. Never remove focus outlines. Icons are supplementary to text; accessible names on icon-only controls.

## 3. Application shell

White sidebar with subtle divider, teal cross/wordmark `SIMRS Outpatient`, grouped role-authorized menu, soft teal selected row and dark teal text. No inactive placeholder links presented as functioning. Topbar: breadcrumb, collapse/menu button, user name and role, logout. Content: page heading and description, one main action, contextual filters, main work area. Avoid global patient search in roles without permission. Admin monitoring is not a clinical workspace. No notification system or dark theme required.

Responsive rules: >=1280px expanded sidebar and 32px content padding; 1024–1279px collapsible 80px sidebar and 24px padding; <1024px drawer navigation, 16px content padding and stacked columns. At <640px use single-column forms and 12–16px card padding, horizontal scrolling for wide tables with critical action accessible; no whole-page horizontal overflow. Print routes hide sidebar/topbar/actions. Test widths 1440, 1280, 1024, 768, 390. Clinical workflows optimize desktop; maintain input usability and no data loss on small screens. Prefer dense grouped sections over excessive scrolling in clinical/cashier workflows; keep 44px primary controls.

## 4. Role-aware navigation and screens

Routes are proposed frontend paths, not changes to the REST contract. The actual RBAC and field exposure are governed by `RBAC.md` and `API.md`.

| Role | Landing | Menu/workspace |
|---|---|---|
| admin | /dashboard | Dashboard, monitoring visits/invoices, master patients/staff/clinics/schedules/procedures/medicines/lab tests, users, audit metadata |
| registration | /registration | Pendaftaran, antrean, data pasien, administrative visit history |
| doctor | /doctor | Assigned queue, examination, authorized patient history, read-only service results |
| pharmacy | /pharmacy | Resep masuk, detail/dispense, prescription history, read-only medicine master |
| laboratory | /laboratory | Lab orders, results, order history, read-only test master |
| cashier | /billing | Eligible visits, invoices, payments, receipt history |

All roles: own profile and logout. React route guard redirects missing token to login and handles forbidden/out-of-scope states without leaking details. Backend remains authoritative; hide inaccessible navigation and mutations. Admin cannot perform medical, dispense, lab or payment actions. Do not expose cross-module clinical data in summary cards or search. Store access token in memory per architecture; v1.0.1 restores the original unexpired token via a scoped HttpOnly cookie. No long-lived remember-me token or patient data in localStorage. Login uses username (not email) and password as specified by API. No signup/reset unless later explicitly implemented.

### Login

Desktop split layout: white form panel and soft teal brand panel with optional lightweight local illustration or geometric medical mark. Mobile form only. Username/password labels, show-password toggle, submit loading, generic invalid credentials, session expiry notice. No fake social login. Optional documented dummy accounts on a development-only help section; do not show real credentials. Landing based on returned role.

### Admin dashboard

Page heading, date-range filter default today in WIB (max 366 days) and refresh. Top cards for visit count, procedure quantity, collected revenue and generated invoice payment summary. Additional sections: horizontal bars for visits by clinic, medicine usage ranked table/bar chart, invoice status breakdown; metric detail opens paginated table using Node details endpoint. All six required metrics from API. No daily trend, growth percentages or operational metrics that API does not supply; add only after contract expansion. Dashboard data unavailable must be a retryable error, not zero. Zero is displayed only for successful empty aggregates. Explain date basis and current invoice statuses; revenue is payments received, not billed value. No PHI in drill-down.

### Master data and users

Consistent table toolbar: search, relevant filters, active state, add button. Columns prioritize identifiers/name/status plus domain-specific fields (price, schedule, stock). Server pagination, default 20, stable sort; reset page on filter change. Drawer for create/edit, full form only if needed, confirmation for delete/deactivate. Preserve history, show server conflict, stock adjustment dedicated action with reason; generic medicine edit never overwrites stock. User disable/revoke behavior and last-admin safeguards follow RBAC.

### Registration and queue

Search by MRN/identity/name, select existing patient or create via permitted master form. Patient summary, clinic, active doctor and schedule selection, current visit date from server policy, clear registration fee. Submit only patient_id and schedule_id. Queue number shown only after confirmed server response. Duplicate visit error with link to authorized existing visit/queue where available. Today's queue filters and administrative history; no clinical notes. No future booking in MVP.

### Doctor workspace

Assigned patient queue with status, explicit start action, sticky compact patient header (name, MRN, visit/queue), grouped sections/tabs: Anamnesis, Tanda Vital & Pemeriksaan Fisik, Diagnosis, Tindakan, Resep, Laboratorium, Riwayat (authorized). Vitals labeled with units; free-text diagnosis, multiple secondary diagnoses, no invented ICD requirement. Separate save actions, expected_version and reason on update. Show tariff snapshots from API; add multiple procedure and prescription items with quantity/dosage/frequency validation. Lab results visible from visit history. Explicit finish exam with summary and warnings for unsaved changes. Draft edits preserve in memory across tabs, warn before leaving. After billing, distinguish diagnosis-only amendment vs fee-affecting revision; show locked state after any payment. On stale version, retain draft in memory and offer reload/review, never overwrite automatically.

### Pharmacy

Prescription list with status and detail showing only permitted patient identity, drug names, dose/frequency, quantity and server stock availability. Clear distinction between advisory availability and confirmed dispense. Entire prescription dispensed in one action with confirmation; show success only after API confirmation. On insufficient stock, refetch availability and display conflict; do not decrement UI optimistically or partially dispense. Repeat safely per workflow. History shows server movements/results if exposed by API; no unsupported inventory edit.

### Laboratory

Order list, test name, unit and result status; detail with result_text input per item, optional unit context from master/DTO, save each result using stable UUID. No invented analyte-specific fields or diagnostic reference ranges. Mark completed results read-only. On ambiguous result timeout retain payload and same key for retry, display pending confirmation, refetch before new submission. Core UI uses Yii lab endpoint; Node integration demonstrable separately in Postman. If Node is unavailable core workflow remains usable.

### Billing & payment

Eligible visits list and invoice list with MRN/status/period. Invoice detail: registration, procedures, lab, dispensed medicine, total, paid, remaining; separate payment history and receipt link. Two-column invoice + payment form at desktop; stack on small screens. Server-provided integer rupiah values as decimal strings, safe formatting without float arithmetic. No client authoritative total. Payment positive and <= outstanding, method cash/non_cash, expected_revision; confirm amount/method. Generate one UUID per payment intent, keep same body/key until definitive outcome. Disable double-click; on timeout show 'Status pembayaran belum dapat dipastikan', offer retry same key and history refresh, do not auto-create new intent. MVP keeps pending intent in memory and uses server history to reconcile after reload; no sensitive payload in local storage. Stale invoice -> reload and review before new intent; paid/zero-total invoice hides payment CTA. Print A4 invoice and individual receipt from API DTO, no real gateway; show invoice revision and receipt number. Zero-total invoice has no receipt.

## 5. Shared behavior and language

| API state | UI response |
|---|---|
| Loading | Skeleton for lists/cards; action spinner with duplicate submission disabled |
| Success | Localized toast, updated data via query invalidation, confirmed server state |
| Empty | Plain explanation and permitted next action; not a fake error |
| 401 | Clear memory token/cache and redirect login |
| 403 / 404 scope | Forbidden/not found view; no hidden object details |
| 409 | Specific domain error; refresh related data; preserve safe unsaved input |
| 422 | Inline field messages and summary; focus first invalid field |
| 429 | Explain throttling; no aggressive auto retry |
| 503 / network | Retryable read error, distinguish uncertain write outcome; do not show fake success/zero |
| 500 | Generic error with request ID if supplied; no stack trace/SQL/PHI |

Visit labels: waiting = Menunggu; in_exam = Dalam pemeriksaan; services_pending = Menunggu pelayanan penunjang; ready_for_billing = Siap pembayaran; completed = Selesai. Payment labels: unpaid = Belum dibayar; partial = Dibayar sebagian; paid = Lunas. Map pharmacy/lab status from actual DTO enum, not visual assumptions. Use `id-ID`, `Asia/Jakarta`, `Rp` and explicit units. Keep raw code values in API, not translated payloads. Use accessible live status messages, focus management for drawer/modal, keyboard interaction, sufficient contrast, and reduced-motion preference. No patient data in console, URL query string beyond required non-sensitive filters/IDs (MRN search API contract is explicit; avoid logging), analytics or persistent browser cache. Dummy data only.

## 6. Component & implementation plan

Phase 2 creates `src/theme/` (tokens + ConfigProvider + CSS), `src/layouts/AppLayout`, `src/components/` (PageHeader, SectionCard, DataTable, StatusTag, FormDrawer, PatientSummary, EmptyState, ErrorState, ConfirmAction), `src/features/auth`, `src/lib/api`, `src/routes` with role guards. Use TanStack Query for request state and bounded retries on reads; no blanket write retries. Logout clears all query data. Build login and shell first; subsequent phases reuse components. Dashboard should show explicit not-yet-implemented state until Node endpoints exist, not mock production data. Design consistency is a gate per module, not a new feature scope. No new dependency except Lucide React and compatible chart support if necessary; simple bars/tables adequate. No external reference images required for implementation.

## 7. UI acceptance checklist (planned, not tested)

- [ ] Theme tokens applied consistently across all nine required modules; white sidebar, teal accent, spacious cards, Indonesian copy.
- [ ] Six demo users land on correct workspace; menu/route/actions honor RBAC; unauthorized API tests remain mandatory.
- [ ] Login matches username contract; error/expiry/logout work without persisted credentials.
- [ ] Responsive shell at listed breakpoints, keyboard navigation and focus return, contrast checked in rendered UI; tables not clipped without scroll.
- [ ] Master CRUD and registration show real server results, errors, empty states, pagination and queue confirmation.
- [ ] Doctor forms show assigned scope, unsaved changes, revision conflicts and billing locks; no invented clinical data fields.
- [ ] Pharmacy/lab confirmation and error states reflect atomic stock and idempotent lab writes.
- [ ] Payment timeout/retry preserves key and request; receipt print hides shell; partial/full/zero totals correct.
- [ ] Dashboard all six metrics, filter and drill-down from Node; service outage shows unavailable.
- [ ] Capture actual screenshots from running app (login and each role/workspace, invoice/receipt) with dummy data only. Mockup/reference images are not implementation evidence.

This specification changes visual and interaction decisions only; `REQUIREMENTS.md`, `ARCHITECTURE.md`, `WORKFLOW.md`, `ERD.md`, `RBAC.md`, `API.md`, SQL and transaction tests remain authoritative for technical scope. Where a proposed UI needs additional data, adapt to existing authorized DTO or explicitly revise the API contract before implementing.

## Phase 2 implementation delta

Source implements light sidebar shell, teal/white spacious Ant Design theme, split login and role-aware workspace/profile. Only functioning links are shown; domain features are explicitly described as next-phase work. Workspace health/access cards are real API-driven readiness checks, not operational dashboard statistics. Responsive CSS is written but actual rendering and accessibility testing remain pending dependency resolution.

## Phase 3 implementation delta

Role-aware master navigation, spacious real API tables, search and filters, edit/create drawers, detail drawer, safe deletion confirmation and dedicated stock adjustment. Doctor patient lookup excludes identity and phone and requires assigned visits. Actual browser and accessibility checks pending. Unsaved drawer edits are confirmed on drawer close; cross-route draft blocking not yet implemented.

### Phase 4 checkpoint

Registration uses a drawer with remote patient, clinic and doctor/schedule pickers, server date WIB, fee preview and confirmation. Queue list uses spacious table, search, status/date/poli/doctor filters, pagination and 30-second refresh. Registration/doctor have patient history drawer; admin has monitoring only. Doctor can start exam but EMR input is pending Phase 5. No simulated live counts.
