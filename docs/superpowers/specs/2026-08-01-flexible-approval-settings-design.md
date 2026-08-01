# Flexible Approval Settings Design

## Context

HRIS allows an administrator to configure approval chains per employee for Cuti, Lembur, Presensi, Anggaran, LHP (`travel_report`), and LPJ. Replacing an approver can appear to fail when any submitted approval step is blank. Laravel validates every step as an integer employee ID before the controller removes empty values, then redirects back without a visible approval-specific error.

Finance will update its stored approvers to Maritza manually. This change must not modify production approval data or automatically assign Maritza.

## Goal

Allow administrators to replace an approver with any valid active employee from the same company without unrelated blank steps blocking the save. If submitted data is invalid, show a useful message instead of silently returning to the unchanged form.

## Chosen Approach

Normalize approval-chain inputs before validation on both write paths:

- Per-employee approval settings.
- Bulk Assign Approval.

Normalization removes blank/null step values and reindexes each chain. Validation then checks only meaningful values. Existing employee-ID validation remains in place, and approvers are constrained to the administrator's company at the write boundary.

The admin layout or relevant approval views will render validation errors so failures are visible. The success message remains unchanged.

## Data Flow

1. The browser submits selected employee IDs, approval types, and approver IDs.
2. The controller normalizes arrays by removing empty step values and reindexing them.
3. Laravel validates the normalized payload.
4. The controller replaces only the requested approval chains.
5. The user sees either a success message or a validation message.

No migrations or production-data updates are required.

## Error Handling and Security

- A chain that becomes empty after normalization is allowed for the per-employee form and clears that type, matching current behavior.
- Bulk assignment still requires at least one non-empty approver.
- Submitted employee and approver IDs must belong to the logged-in administrator's company; IDs from another company are rejected.
- Database replacement behavior remains unchanged and focused on the selected employee/type pair.

## Tests

Regression tests will cover:

- Replacing Lina with Maritza for Anggaran, LHP, and LPJ while another step value is blank.
- Bulk assignment accepting selected approvers after blank values are removed.
- Bulk assignment rejecting a chain containing no selected approver.
- Rejecting employee or approver IDs from another company.
- Rendering validation feedback in the approval UI.

Tests will be written and observed failing before production code is changed.

## Out of Scope

- Automatically changing existing Finance approval assignments.
- Changing approval-chain semantics or request statuses.
- Adding role or job-level restrictions for approvers.
- Deploying the change to production.
