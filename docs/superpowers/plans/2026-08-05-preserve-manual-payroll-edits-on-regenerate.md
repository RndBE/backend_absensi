# Preserve Manual Payroll Edits During Regeneration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refresh automatically generated payroll data during draft regeneration while retaining explicit administrator edits, additions, and removals.

**Architecture:** Persist a JSON override ledger on each payroll detail. A focused support class captures differences during manual save and overlays them onto a freshly generated component list; the payroll controller wraps destructive regeneration and overlay application in one transaction.

**Tech Stack:** PHP 8.2, Laravel 12, Eloquent JSON casts, PHPUnit 11, SQLite test database.

---

## File Structure

- Create `database/migrations/2026_08_05_000001_add_manual_overrides_to_payroll_run_details_table.php`: nullable JSON persistence with a reversible migration.
- Create `app/Support/PayrollManualOverrides.php`: component identity, override capture, legacy derivation, overlay, and totals.
- Modify `app/Models/PayrollRunDetail.php`: make `manual_overrides` fillable and cast it to an array.
- Modify `app/Http/Controllers/Admin/PayrollRunController.php`: capture edits, transactionally regenerate, derive legacy overrides, reapply overrides, rebuild dependent PPh 21, and recalculate totals.
- Modify `app/Http/Controllers/Admin/PayslipController.php`: capture basic salary and component overrides from the second payslip edit endpoint.
- Modify `resources/views/admin/payroll-runs/show.blade.php`: clarify regenerate confirmation.
- Modify `tests/Feature/PayrollLoanDeductionTest.php`: integration coverage using the existing complete payroll schema fixture.
- Create `tests/Unit/PayrollManualOverridesTest.php`: focused component-ledger behavior tests.

### Task 1: Override Ledger Persistence and Core Merge Rules

**Files:**
- Create: `database/migrations/2026_08_05_000001_add_manual_overrides_to_payroll_run_details_table.php`
- Create: `app/Support/PayrollManualOverrides.php`
- Modify: `app/Models/PayrollRunDetail.php`
- Create: `tests/Unit/PayrollManualOverridesTest.php`

- [ ] **Step 1: Write failing unit tests for additions, changed auto components, removals, and unrelated refreshes**

Create tests that call the intended API:

```php
$ledger = (new PayrollManualOverrides)->capture(null, $before, $submitted);
$merged = (new PayrollManualOverrides)->apply($fresh, $ledger);

$this->assertSame(2_624_387.0, $this->amount($merged, 'Rate BPJS Kesehatan'));
$this->assertSame(120_000.0, $this->amount($merged, 'BPJS Kesehatan'));
$this->assertSame(300_000.0, $this->amount($merged, 'Lembur'));
$this->assertNull($this->component($merged, 'Potongan Terlambat'));
```

- [ ] **Step 2: Run the unit tests and verify RED**

Run: `php artisan test tests/Unit/PayrollManualOverridesTest.php`

Expected: FAIL because `App\Support\PayrollManualOverrides` does not exist.

- [ ] **Step 3: Add the migration and model cast**

The migration adds and removes the column safely:

```php
Schema::table('payroll_run_details', function (Blueprint $table) {
    $table->json('manual_overrides')->nullable()->after('is_manual_edited');
});
```

Add `manual_overrides` to `$fillable` and `'manual_overrides' => 'array'` to `casts()`.

- [ ] **Step 4: Implement the minimal ledger service**

Expose these methods:

```php
public function capture(?array $existingLedger, array $before, array $submitted, ?float $basicBefore = null, ?float $basicSubmitted = null): array;
public function deriveLegacy(PayrollRunDetail $old, PayrollRunDetail $fresh): array;
public function apply(array $generated, array $ledger, ?callable $filter = null): array;
public function basicSalary(float $generated, array $ledger): float;
public function totals(float $basicSalary, array $components): array;
```

Use normalized `type + name` keys. Store full normalized components under `components`, and removed automatic components under `removed` with their previous component payload so tax-only reapplication can filter correctly. Preserve existing ledger entries unless a later manual edit replaces or removes them.

- [ ] **Step 5: Run the unit tests and verify GREEN**

Run: `php artisan test tests/Unit/PayrollManualOverridesTest.php`

Expected: all override-ledger tests PASS.

- [ ] **Step 6: Commit Task 1**

```bash
git add database/migrations/2026_08_05_000001_add_manual_overrides_to_payroll_run_details_table.php app/Models/PayrollRunDetail.php app/Support/PayrollManualOverrides.php tests/Unit/PayrollManualOverridesTest.php
git commit -m "feat: track manual payroll overrides"
```

### Task 2: Capture Overrides From Both Manual Edit Endpoints

**Files:**
- Modify: `app/Http/Controllers/Admin/PayrollRunController.php:123-184`
- Modify: `app/Http/Controllers/Admin/PayslipController.php:211-257`
- Modify: `tests/Feature/PayrollLoanDeductionTest.php`

- [ ] **Step 1: Write failing feature tests for override capture**

Extend the custom `payroll_run_details` test schema with:

```php
$table->json('manual_overrides')->nullable();
```

Assert a manual component addition and auto-component amount change are recorded after `updateDetail()`:

```php
$ledger = $detail->fresh()->manual_overrides;
$this->assertSame(250000.0, (float) $ledger['components']['earning|tunjangan baru']['amount']);
$this->assertSame(120000.0, (float) $ledger['components']['deduction|bpjs kesehatan']['amount']);
```

Also cover a removed automatic component in `manual_overrides['removed']`.

- [ ] **Step 2: Run the focused feature tests and verify RED**

Run: `php artisan test tests/Feature/PayrollLoanDeductionTest.php --filter='manual_override|newly_added_component'`

Expected: FAIL because saves do not populate `manual_overrides`.

- [ ] **Step 3: Capture overrides before dependent recalculation**

Inject or resolve `PayrollManualOverrides` in both controllers. After normalizing submitted components but before rebuilding PPh 21, calculate:

```php
$manualOverrides = $overrides->capture(
    $detail->manual_overrides,
    $existingComponents,
    $components,
    (float) $detail->basic_salary,
    $submittedBasicSalary
);
```

Persist `manual_overrides` together with `is_manual_edited = true`. In `PayrollRunController`, pass equal basic salary values because that edit form changes components only. In `PayslipController`, pass the old and submitted salaries.

- [ ] **Step 4: Run focused feature tests and verify GREEN**

Run: `php artisan test tests/Feature/PayrollLoanDeductionTest.php --filter='manual_override|newly_added_component'`

Expected: selected tests PASS.

- [ ] **Step 5: Commit Task 2**

```bash
git add app/Http/Controllers/Admin/PayrollRunController.php app/Http/Controllers/Admin/PayslipController.php tests/Feature/PayrollLoanDeductionTest.php
git commit -m "feat: record payroll edit overrides"
```

### Task 3: Transactional Regeneration With Manual Overlay

**Files:**
- Modify: `app/Http/Controllers/Admin/PayrollRunController.php:277-296`
- Modify: `tests/Feature/PayrollLoanDeductionTest.php`

- [ ] **Step 1: Write failing regeneration feature tests**

Seed a draft run, generate its detail, manually change BPJS, add `Rate BPJS Kesehatan`, and update a separate automatic source value. Call `regenerate()` and assert:

```php
$fresh = PayrollRunDetail::where('payroll_run_id', $run->id)->firstOrFail();
$this->assertSame(120000.0, $this->componentAmount($fresh, 'BPJS Kesehatan'));
$this->assertSame(2624387.0, $this->componentAmount($fresh, 'Rate BPJS Kesehatan'));
$this->assertSame(300000.0, $this->componentAmount($fresh, 'Lembur'));
$this->assertSame(
    (float) $fresh->total_earning - (float) $fresh->total_deduction,
    (float) $fresh->net_salary
);
```

Add a legacy test where `is_manual_edited` is true and `manual_overrides` is null, proving a non-auto Rate BPJS component survives.

- [ ] **Step 2: Run the regeneration tests and verify RED**

Run: `php artisan test tests/Feature/PayrollLoanDeductionTest.php --filter=regenerate`

Expected: FAIL because regeneration deletes manual additions and changes.

- [ ] **Step 3: Implement transaction, snapshot, generation, and overlay**

Import `DB` and execute the destructive flow with `DB::transaction()`. Snapshot old details keyed by employee ID, regenerate, derive a ledger for legacy rows when needed, apply the ledger, and persist recalculated detail totals.

For BPJS or basic salary overrides, set the overlaid salary and rebuild PPh 21 from non-tax components. Then reapply tax-specific overrides/removals and calculate totals:

```php
$merged = $overrides->apply($generatedComponents, $ledger);
$detail->basic_salary = $overrides->basicSalary((float) $detail->basic_salary, $ledger);

if ($basicChanged || $this->bpjsAmountsChanged($generatedComponents, $merged)) {
    $merged = $this->rebuildPph21Components($run, $detail, $merged);
    $merged = $overrides->apply($merged, $ledger, fn (array $component) => $this->isTaxComponent($component));
}

$totals = $overrides->totals((float) $detail->basic_salary, $merged);
```

Save the ledger on the regenerated row and retain `is_manual_edited` when the ledger is non-empty. Recalculate run totals after all details are updated.

- [ ] **Step 4: Run regeneration tests and verify GREEN**

Run: `php artisan test tests/Feature/PayrollLoanDeductionTest.php --filter=regenerate`

Expected: regeneration tests PASS.

- [ ] **Step 5: Commit Task 3**

```bash
git add app/Http/Controllers/Admin/PayrollRunController.php tests/Feature/PayrollLoanDeductionTest.php
git commit -m "fix: preserve manual edits during payroll regeneration"
```

### Task 4: User Messaging and Full Verification

**Files:**
- Modify: `resources/views/admin/payroll-runs/show.blade.php:76`

- [ ] **Step 1: Update confirmation and success copy**

Use explicit text:

```blade
data-confirm="Regenerate data otomatis? Perubahan manual tetap dipertahankan."
```

Return: `Data otomatis berhasil di-regenerate tanpa menghapus perubahan manual.`

- [ ] **Step 2: Run formatting and focused tests**

Run:

```bash
vendor/bin/pint --test app/Support/PayrollManualOverrides.php app/Models/PayrollRunDetail.php app/Http/Controllers/Admin/PayrollRunController.php app/Http/Controllers/Admin/PayslipController.php database/migrations/2026_08_05_000001_add_manual_overrides_to_payroll_run_details_table.php tests/Unit/PayrollManualOverridesTest.php tests/Feature/PayrollLoanDeductionTest.php
php artisan test tests/Unit/PayrollManualOverridesTest.php tests/Feature/PayrollLoanDeductionTest.php
```

Expected: Pint exits 0 and all selected tests PASS.

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test`

Expected: full suite exits 0 with no failures.

- [ ] **Step 4: Verify migration reversibility and diff hygiene**

Run:

```bash
php artisan migrate --pretend
git diff --check
git status --short
```

Expected: migration SQL includes `manual_overrides`, `git diff --check` exits 0, and only intended files are changed.

- [ ] **Step 5: Commit final UI copy if not included earlier**

```bash
git add resources/views/admin/payroll-runs/show.blade.php
git commit -m "ui: clarify payroll regeneration behavior"
```
