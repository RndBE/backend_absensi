# Manual Historical BPJS Rate Snapshot Design

## Problem

Payslips that are manually edited or belong to a prior month intentionally render BPJS benefits from the stored `PayrollRunDetail.components` snapshot. Legacy snapshots store company contributions and their formula details, but they do not store the two BPJS basis rows. As a result, July manual payslips such as Hella's omit both `Rate BPJS` rows and total only the company contributions.

## Desired behavior

- Manual and historical payslips remain snapshot-based; they must not use the employee's current payroll salary or current BPJS eligibility.
- When stored BPJS contribution components contain formula details such as `4% x Rp 2.624.387`, reconstruct the exact historical basis from those details.
- Render benefits in the canonical order: Health basis, Employment basis, JKK, JKM, JHT, JP when present, and Health contribution.
- Preserve unrelated information components and existing formula notes.
- Do not add a database migration or mutate historical payroll records.

## Design

`PayslipBpjsData::fromDetail()` will return normalized snapshot items instead of an empty item list when component snapshots are selected. A focused parser will read explicit `Rate BPJS` components first and otherwise extract the basis after `x Rp` from the stored contribution detail. Company contribution values remain the stored amounts.

`PayslipBenefits` will continue merging snapshot items with raw components. Its existing de-duplication keeps the canonical snapshot items first while still collecting notes and unrelated info components from the raw snapshot.

## Verification

A regression test will model a manually edited July 2026 payslip viewed in August with the four stored contribution rows from the screenshots. It must render the six expected rows and total `5,465,023`, while proving the values come from the stored snapshot.
