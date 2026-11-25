# UAT backlog — consolidated through v1.1.0

Source: actual user-run browser UAT on v1.0.0 and discussion. “Implemented” below means source modified, not acceptance retested. Unavailable earlier ID mappings are not invented. No production data.

| Priority | UAT finding / requested improvement | Release target / status |
|---|---|---|
| High | Browser refresh forces login; preserve session securely | v1.0.1 implemented; browser regression pending |
| Medium | Auto codes for master staff, procedures, medicine, lab; clinic code optional automatic; MR already automatic | v1.0.1 implemented; preserve existing codes; runtime pending |
| Medium | Explicit activate/deactivate separate from hard delete | v1.0.1 implemented; strict mode UI, legacy API compatible; runtime pending |
| Medium | UI-024: initial clinical section requires “Alasan perubahan” | v1.0.1 implemented; later edit, clear and re-add still require reason |
| Medium | Row action icons and tooltips | v1.0.1 master tables; v1.0.2 shared icon detail/history/edit actions; account split/audit detail v1.0.4 implemented; runtime pending |
| Medium | Centered add/edit modals instead of right drawers; appropriate size for long forms | v1.0.2 implemented in source; runtime pending |
| Medium | Admin Master parent menu with 7 children, children no icons | v1.0.2 implemented in source; runtime pending |
| Low | Date formatting Indonesian, Rupiah grouping while typing, consistent action buttons and badge colors/width, search field size, contextual empty states | v1.0.2 implemented in source; runtime pending |
| High | UI-018, UI-022: doctor uses dedicated workspace and guided record/procedure/Rx/lab/review workflow; avoid accidental finish | v1.0.3 implemented; guided review and confirmation; runtime pending |
| Low | UI-023: no-Rx/no-lab message must reflect finished exam status | v1.0.3 implemented; runtime pending |
| Low | UI-025: dosage/frequency labels and placeholders, units | placeholders v1.0.2 implemented; full dose/frequency unit treatment v1.0.3 implemented; runtime pending |
| Low | UI-026: consistent RX/LAB/INV/PAY display number formatting; preserve identifiers | v1.0.3 implemented; runtime pending |
| Low | UI-027/028/029/030: compact badges, lab result placeholder, filter-specific empty states, newline preservation | v1.0.2 implemented in source; runtime pending |
| Low | UI-031: show Rx subtotal/total | v1.0.3 implemented; runtime pending |
| Medium | UI-032/033/034: Rupiah input, invoice line order, dedicated billing workspace | Rupiah v1.0.2 implemented; invoice order and workspace v1.0.3 implemented; runtime pending |
| Low | UI-035: payment progress indicator | v1.0.3 implemented; runtime pending |
| Medium | UI-036: professional print layouts, browser header/footer instructions | v1.0.3 implemented; runtime pending |
| Medium | UI-037–042: trend charts, donut, badge consistency, financial summary, reporting tabs/pagination copy | badges/pagination copy v1.0.2 implemented; remaining v1.0.4 implemented; distinct date bases for billed cohort and received cash; runtime pending |
| Medium | UI-043: dashboard drill-down updates below viewport; modal or dedicated detail based on complexity | v1.0.4 centered modal implemented; remaining detail verification pending |
| Low | UI-044/045: role-specific monitoring titles/descriptions | v1.0.2 implemented; runtime pending |
| Medium | UI-046–050: staff names not IDs, separate user edit/reset/status actions, add icon, password guidance, explicit role selection | add icon/password guidance v1.0.2 implemented; remaining v1.0.4 implemented; runtime pending |
| Medium | UI-051–057: audit actor/activity/entity dropdowns, Indonesian labels, detail modal for technical IDs, reset filters | v1.0.4 implemented; exact entity semantics retained; runtime pending |
| Medium | UI-013: stock adjustment direction and projected balance | v1.0.2 implemented; preserve signed API payload and retry key; runtime pending |
| Low | UI-009: contextual delete confirmation | v1.0.2 implemented; medicine stock warning only for medicine |
| Medium | BR-001/BR-002: warning outside practice hours and future visit date | Deferred in v1.1.0 after risk review; same-day WIB retained. See V1.1.0_NOTES.md. |
| Pending | UAT 12 Postman intentionally deferred, evidence of actual Node request path and final release regression | v1.1.0 submission gate |
| Pending | Admin unpaid invoice read-only verification and backend role checks, nonempty clinical history, complete report drill-down, remaining invoice/PAY-2 PDF evidence | final UAT evidence gate |

## Scope guardrails

- Registration is same-day WIB by MVP decision; appointment booking is not added here.
- Clinical actions, prescriptions and lab remain optional; do not force medical services merely to complete a UI step.
- Six-role RBAC, transactional stock and payment safety, current pricing snapshots, existing invoice/receipt identifiers and UAT volume stay intact.
- No real gateway, clinical advice, offline write synchronization, or production deployment. Prioritize working submission over optional visual polish.

Final source review: stale-tab browser logout/restore hardening, duplicate login submission guard, seed MRN width and sequential QA build added in v1.1.0. All updated runtime cases remain pending. See FINAL_UAT.md.
