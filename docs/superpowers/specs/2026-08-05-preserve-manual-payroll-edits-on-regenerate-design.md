# Preserve Manual Payroll Edits During Regeneration

## Goal

Regenerating a draft payroll must refresh values derived from current payroll, attendance, BPJS, loan, and tax data without removing changes an administrator made manually.

## Selected Design

Store explicit manual overrides on each `payroll_run_details` record in a nullable JSON column named `manual_overrides`.

The override document contains:

- an optional manually overridden basic salary;
- components manually added or changed, keyed by a stable component identity composed from type and normalized name;
- tombstones for automatically generated components that an administrator manually removed.

When an administrator updates a payslip, the server compares the submitted state with the state currently stored before applying the update. It records only actual differences as overrides and retains earlier overrides that were not reverted. Components added manually are always recorded as overrides.

## Regeneration Flow

1. Allow regeneration only for a draft payroll, as today.
2. Inside one database transaction, snapshot manual override data for every existing employee detail.
3. Delete and regenerate payroll details from the current source data.
4. Match each regenerated detail to its snapshot by employee ID.
5. Apply basic-salary overrides, component overrides, additions, and removal tombstones.
6. Recalculate each detail's earning, deduction, net salary, and tax-dependent values where applicable.
7. Recalculate payroll-run totals and record the regenerate action.

If any step fails, the transaction rolls back so the previous payroll remains intact.

## Component Matching

Components are matched by normalized `type + name`. Matching is case-insensitive and ignores surrounding whitespace. This matches the current data model, which does not assign component IDs.

If an overridden automatic component still exists after regeneration, its stored manual version replaces the generated version. If it no longer exists, a manually added component remains present, while a removed automatic component remains removed.

## Legacy Manually Edited Details

Older rows can have `is_manual_edited = true` without `manual_overrides`. They cannot reveal which automatic fields were changed manually. To prevent data loss, the first regeneration after deployment derives a conservative legacy snapshot:

- components marked non-automatic are preserved as manual additions;
- automatic components whose generated value differs from the old value are preserved as legacy overrides;
- manually removed automatic components cannot be inferred from legacy data and therefore may be generated again;
- once derived, overrides are saved in the new structure for subsequent regenerations.

This limitation only affects edits made before override tracking is deployed. New edits, including removals, are tracked exactly.

## User-Facing Behavior

The regenerate confirmation and success message state that automatic data is refreshed while manual edits are retained. No new action is required from the administrator.

## Error Handling

- Non-draft payroll runs remain rejected.
- Missing employees after regeneration are skipped without applying an override to another employee.
- Invalid or malformed legacy override JSON is treated as empty instead of breaking the payroll run.
- Database failures roll back deletion, generation, and override application together.

## Tests

Feature tests will prove that:

- regenerate refreshes an unedited automatic component;
- a manually added Rate BPJS component survives regenerate;
- a manually changed automatic component survives while unrelated automatic components refresh;
- a manually removed automatic component stays removed;
- basic salary override survives and totals are recalculated;
- legacy manually edited data is preserved conservatively;
- non-draft payroll still cannot be regenerated.
