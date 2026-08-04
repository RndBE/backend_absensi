# Manual Historical BPJS Rates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore both BPJS rate rows and the expected total for manually edited or historical payslips using only their stored payroll snapshot.

**Architecture:** Extend `PayslipBpjsData` so its component-snapshot path reconstructs canonical BPJS items from stored info components and formula details. Keep `PayslipBenefits` as the merge, de-duplication, note, and total layer.

**Tech Stack:** PHP 8.4, Laravel 12, PHPUnit.

---

### Task 1: Reproduce Hella's July snapshot regression

**Files:**
- Modify: `tests/Feature/PayrollPayslipEmailPublishTest.php`

- [ ] **Step 1: Write the failing test**

Add a test that creates a manually edited July detail containing stored Health, JHT, JKK, and JKM company components with `x Rp 2.624.387` formula details. Call `PayslipBpjsData::fromDetail()` and `PayslipBenefits::from()`.

- [ ] **Step 2: Assert the expected historical output**

Assert the labels are `Rate BPJS Kesehatan`, `Rate BPJS Ketenagakerjaan`, JKK, JKM, JHT, and Health contribution in that order, and assert the total equals `5_465_023.0`.

- [ ] **Step 3: Run the test to verify RED**

Run:

```bash
php artisan test tests/Feature/PayrollPayslipEmailPublishTest.php --filter=manual_historical_payslip_reconstructs_bpjs_rate_rows_from_snapshot
```

Expected: FAIL because both rate rows are absent and the total is `216249`.

### Task 2: Reconstruct canonical BPJS snapshot items

**Files:**
- Modify: `app/Support/PayslipBpjsData.php`
- Test: `tests/Feature/PayrollPayslipEmailPublishTest.php`

- [ ] **Step 1: Return snapshot items from stored components**

Replace the empty component-snapshot result with a result containing canonical items and their sum.

- [ ] **Step 2: Parse exact historical bases**

Normalize component labels, prefer explicit Rate BPJS rows, and otherwise parse the numeric value following `x Rp` in the stored detail. Do not consult active payroll data in this path.

- [ ] **Step 3: Normalize contribution labels and ordering**

Map stored JKK, JKM, JHT, JP, and Health company components to the same labels used by calculated benefits, then emit them after the two available basis rows.

- [ ] **Step 4: Run the regression test to verify GREEN**

Run:

```bash
php artisan test tests/Feature/PayrollPayslipEmailPublishTest.php --filter=manual_historical_payslip_reconstructs_bpjs_rate_rows_from_snapshot
```

Expected: PASS.

### Task 3: Verify, publish, and deploy

**Files:**
- Modify only the two implementation/test files above plus these design documents.

- [ ] **Step 1: Run focused benefit tests**

Run the snapshot, inference, de-duplication, and formula-note tests in `PayrollPayslipEmailPublishTest` and confirm zero failures.

- [ ] **Step 2: Inspect scope**

Run `git diff --check`, `git status -sb`, and inspect `git diff` to confirm no unrelated changes.

- [ ] **Step 3: Commit and push**

Commit the regression fix, push the fix branch, fast-forward `main`, and push `main` after all checks pass.

- [ ] **Step 4: Deploy and verify production**

Trigger the Plesk Git deployment for `hris.awass.site`, rebuild Laravel caches, confirm the deployed commit, run PHP syntax checks, and confirm the site returns the expected authentication redirect.
