# Flexible Approval Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make per-employee and bulk approval settings ignore blank steps, accept any active same-company approver, reject cross-company IDs, and visibly report validation errors.

**Architecture:** Add one focused input-normalization utility used by both approval-setting controllers. Normalize blank step values before Laravel validation, then validate all requester/approver IDs against the logged-in administrator's company and active status. Keep `EmployeeApprover::saveChain()` unchanged and add a shared validation-error alert to the admin layout.

**Tech Stack:** PHP 8.3, Laravel 12, Blade, PHPUnit 11, SQLite in-memory tests.

---

## File Structure

- Create `app/Support/ApprovalChainInput.php`: normalize blank approval-step values without hiding malformed non-array input from validation.
- Create `tests/Unit/ApprovalChainInputTest.php`: specify normalization behavior.
- Create `tests/Feature/AdminApprovalSettingsTest.php`: exercise both controllers with real validation and database writes.
- Modify `app/Http/Controllers/Admin/EmployeeApproverController.php`: normalize and validate per-employee chains within the administrator's company.
- Modify `app/Http/Controllers/Admin/ApprovalRuleController.php`: normalize and validate bulk assignment within the administrator's company.
- Modify `resources/views/admin/layouts/app.blade.php`: display Laravel validation errors.
- Modify `tests/Feature/AdminConfirmModalTest.php`: lock in shared admin validation feedback.

### Task 1: Approval-chain input normalization

**Files:**
- Create: `app/Support/ApprovalChainInput.php`
- Create: `tests/Unit/ApprovalChainInputTest.php`

- [ ] **Step 1: Write the failing normalization tests**

```php
<?php

namespace Tests\Unit;

use App\Support\ApprovalChainInput;
use PHPUnit\Framework\TestCase;

class ApprovalChainInputTest extends TestCase
{
    public function test_it_removes_only_blank_steps_and_reindexes_the_chain(): void
    {
        $this->assertSame([12, 19, '  '], ApprovalChainInput::steps([
            '', 12, null, 19, '  ',
        ]));
    }

    public function test_it_normalizes_each_chain_without_hiding_malformed_values(): void
    {
        $this->assertSame([
            'budget' => [20],
            'travel_report' => 'invalid',
            'lpj' => [],
        ], ApprovalChainInput::chains([
            'budget' => ['', 20],
            'travel_report' => 'invalid',
            'lpj' => [null, ''],
        ]));

        $this->assertSame('invalid', ApprovalChainInput::chains('invalid'));
    }
}
```

- [ ] **Step 2: Run the unit test and verify RED**

Run:

```powershell
php artisan test tests/Unit/ApprovalChainInputTest.php
```

Expected: FAIL because `App\Support\ApprovalChainInput` does not exist.

- [ ] **Step 3: Add the minimal normalization utility**

```php
<?php

namespace App\Support;

final class ApprovalChainInput
{
    public static function steps(mixed $steps): mixed
    {
        if (! is_array($steps)) {
            return $steps;
        }

        return array_values(array_filter(
            $steps,
            static fn ($id) => $id !== null && $id !== ''
        ));
    }

    public static function chains(mixed $chains): mixed
    {
        if (! is_array($chains)) {
            return $chains;
        }

        return array_map(
            static fn ($steps) => self::steps($steps),
            $chains
        );
    }
}
```

- [ ] **Step 4: Run the unit test and verify GREEN**

Run:

```powershell
php artisan test tests/Unit/ApprovalChainInputTest.php
```

Expected: 2 tests PASS with no failures.

- [ ] **Step 5: Commit the normalization unit**

```powershell
git add app/Support/ApprovalChainInput.php tests/Unit/ApprovalChainInputTest.php
git commit -m "fix: normalize blank approval steps"
```

### Task 2: Per-employee approval replacement

**Files:**
- Create: `tests/Feature/AdminApprovalSettingsTest.php`
- Modify: `app/Http/Controllers/Admin/EmployeeApproverController.php`

- [ ] **Step 1: Create the feature-test schema and employee fixtures**

Create `tests/Feature/AdminApprovalSettingsTest.php` with this shared setup:

```php
<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ApprovalRuleController;
use App\Http\Controllers\Admin\EmployeeApproverController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminApprovalSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->integer('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
            $table->unique(['employee_id', 'request_type', 'step_order']);
        });

        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => 1, 'full_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x', 'is_active' => true],
            ['id' => 2, 'company_id' => 1, 'full_name' => 'Finance User', 'email' => 'finance@example.test', 'password' => 'x', 'is_active' => true],
            ['id' => 3, 'company_id' => 1, 'full_name' => 'Lina', 'email' => 'lina@example.test', 'password' => 'x', 'is_active' => true],
            ['id' => 4, 'company_id' => 1, 'full_name' => 'Maritza', 'email' => 'maritza@example.test', 'password' => 'x', 'is_active' => true],
            ['id' => 5, 'company_id' => 2, 'full_name' => 'Other Company', 'email' => 'other@example.test', 'password' => 'x', 'is_active' => true],
            ['id' => 6, 'company_id' => 1, 'full_name' => 'Inactive', 'email' => 'inactive@example.test', 'password' => 'x', 'is_active' => false],
        ]);

        session(['admin_id' => 1]);
    }

    private function request(array $payload): Request
    {
        $request = Request::create('/admin/approval-settings', 'POST', $payload);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }
}
```

- [ ] **Step 2: Add the failing Lina-to-Maritza regression test**

Add inside the test class:

```php
public function test_per_employee_save_replaces_lina_with_maritza_despite_blank_steps(): void
{
    foreach (['budget', 'travel_report', 'lpj'] as $type) {
        DB::table('employee_approvers')->insert([
            'employee_id' => 2,
            'request_type' => $type,
            'step_order' => 1,
            'approver_id' => 3,
        ]);
    }

    app(EmployeeApproverController::class)->store($this->request([
        'chains' => [
            'budget' => ['', 4],
            'travel_report' => [4, null],
            'lpj' => ['', 4, ''],
        ],
    ]), 2);

    $this->assertSame(
        ['budget' => 4, 'lpj' => 4, 'travel_report' => 4],
        DB::table('employee_approvers')
            ->where('employee_id', 2)
            ->orderBy('request_type')
            ->pluck('approver_id', 'request_type')
            ->map(fn ($id) => (int) $id)
            ->all()
    );
}
```

- [ ] **Step 3: Add failing same-company and active-approver tests**

```php
public function test_per_employee_save_rejects_an_approver_from_another_company(): void
{
    $this->expectException(ValidationException::class);

    app(EmployeeApproverController::class)->store($this->request([
        'chains' => ['budget' => [5]],
    ]), 2);
}

public function test_per_employee_save_rejects_an_inactive_approver(): void
{
    $this->expectException(ValidationException::class);

    app(EmployeeApproverController::class)->store($this->request([
        'chains' => ['budget' => [6]],
    ]), 2);
}
```

- [ ] **Step 4: Run the per-employee tests and verify RED**

Run:

```powershell
php artisan test tests/Feature/AdminApprovalSettingsTest.php --filter=per_employee
```

Expected: the blank-step replacement test FAILS validation, and the cross-company/inactive tests FAIL because the current `exists` rule accepts those IDs.

- [ ] **Step 5: Normalize before validation and scope approver IDs**

In `EmployeeApproverController`, add imports:

```php
use App\Support\ApprovalChainInput;
use Illuminate\Validation\Rule;
```

Replace the start of `store()` through `$chains` assignment with:

```php
$admin = Employee::findOrFail(session('admin_id'));
$request->merge([
    'chains' => ApprovalChainInput::chains($request->input('chains')),
]);

$activeCompanyEmployee = Rule::exists('employees', 'id')
    ->where(fn ($query) => $query
        ->where('company_id', $admin->company_id)
        ->where('is_active', true));

$validated = $request->validate([
    'chains' => ['required', 'array'],
    'chains.leave' => ['nullable', 'array'],
    'chains.leave.*' => ['integer', $activeCompanyEmployee],
    'chains.overtime' => ['nullable', 'array'],
    'chains.overtime.*' => ['integer', $activeCompanyEmployee],
    'chains.attendance' => ['nullable', 'array'],
    'chains.attendance.*' => ['integer', $activeCompanyEmployee],
    'chains.budget' => ['nullable', 'array'],
    'chains.budget.*' => ['integer', $activeCompanyEmployee],
    'chains.travel_report' => ['nullable', 'array'],
    'chains.travel_report.*' => ['integer', $activeCompanyEmployee],
    'chains.lpj' => ['nullable', 'array'],
    'chains.lpj.*' => ['integer', $activeCompanyEmployee],
]);

$employee = Employee::where('company_id', $admin->company_id)->findOrFail($employeeId);
$chains = $validated['chains'];
```

Remove the now-redundant `array_filter()` inside the save loop; use `$chains[$type] ?? []` directly.

- [ ] **Step 6: Run the per-employee tests and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/AdminApprovalSettingsTest.php --filter=per_employee
```

Expected: 3 tests PASS.

- [ ] **Step 7: Commit the per-employee fix**

```powershell
git add app/Http/Controllers/Admin/EmployeeApproverController.php tests/Feature/AdminApprovalSettingsTest.php
git commit -m "fix: allow flexible employee approver replacement"
```

### Task 3: Bulk approval assignment

**Files:**
- Modify: `tests/Feature/AdminApprovalSettingsTest.php`
- Modify: `app/Http/Controllers/Admin/ApprovalRuleController.php`

- [ ] **Step 1: Add the failing bulk normalization test**

```php
public function test_bulk_assignment_uses_maritza_after_removing_blank_steps(): void
{
    app(ApprovalRuleController::class)->bulkAssign($this->request([
        'employee_ids' => [2],
        'apply_types' => ['budget', 'travel_report', 'lpj'],
        'approver_ids' => ['', 4, null],
    ]));

    $this->assertSame(
        ['budget' => 4, 'lpj' => 4, 'travel_report' => 4],
        DB::table('employee_approvers')
            ->where('employee_id', 2)
            ->orderBy('request_type')
            ->pluck('approver_id', 'request_type')
            ->map(fn ($id) => (int) $id)
            ->all()
    );
}
```

- [ ] **Step 2: Add failing bulk rejection tests**

```php
public function test_bulk_assignment_rejects_a_chain_with_no_selected_approver(): void
{
    try {
        app(ApprovalRuleController::class)->bulkAssign($this->request([
            'employee_ids' => [2],
            'apply_types' => ['budget'],
            'approver_ids' => ['', null],
        ]));

        $this->fail('A blank bulk chain must be rejected.');
    } catch (ValidationException $exception) {
        $this->assertArrayHasKey('approver_ids', $exception->errors());
        $this->assertArrayNotHasKey('approver_ids.0', $exception->errors());
    }
}

public function test_bulk_assignment_rejects_cross_company_requesters(): void
{
    $this->expectException(ValidationException::class);

    app(ApprovalRuleController::class)->bulkAssign($this->request([
        'employee_ids' => [5],
        'apply_types' => ['budget'],
        'approver_ids' => [4],
    ]));
}
```

- [ ] **Step 3: Run the bulk tests and verify RED**

Run:

```powershell
php artisan test tests/Feature/AdminApprovalSettingsTest.php --filter=bulk
```

Expected: normalization and company-scope tests FAIL under the current controller.

- [ ] **Step 4: Normalize and validate bulk inputs**

In `ApprovalRuleController`, add imports:

```php
use App\Support\ApprovalChainInput;
use Illuminate\Validation\Rule;
```

Replace `bulkAssign()` with:

```php
public function bulkAssign(Request $request)
{
    $admin = Employee::findOrFail(session('admin_id'));
    $request->merge([
        'approver_ids' => ApprovalChainInput::steps($request->input('approver_ids')),
    ]);

    $activeCompanyEmployee = Rule::exists('employees', 'id')
        ->where(fn ($query) => $query
            ->where('company_id', $admin->company_id)
            ->where('is_active', true));

    $validated = $request->validate([
        'employee_ids' => ['required', 'array', 'min:1'],
        'employee_ids.*' => ['integer', $activeCompanyEmployee],
        'apply_types' => ['required', 'array', 'min:1'],
        'apply_types.*' => ['in:leave,overtime,attendance,budget,travel_report,lpj'],
        'approver_ids' => ['required', 'array', 'min:1'],
        'approver_ids.*' => ['integer', $activeCompanyEmployee],
    ]);

    foreach ($validated['employee_ids'] as $employeeId) {
        foreach ($validated['apply_types'] as $type) {
            EmployeeApprover::saveChain(
                (int) $employeeId,
                $type,
                $validated['approver_ids']
            );
        }
    }

    $empCount = count($validated['employee_ids']);
    $typeCount = count($validated['apply_types']);

    return redirect()->route('admin.approval-rules.index', [
        'type' => $validated['apply_types'][0] ?? 'leave',
    ])->with('success', "Berhasil menerapkan approval chain ke {$empCount} karyawan × {$typeCount} tipe pengajuan.");
}
```

- [ ] **Step 5: Run the bulk tests and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/AdminApprovalSettingsTest.php --filter=bulk
```

Expected: 3 tests PASS.

- [ ] **Step 6: Run all approval-setting tests**

Run:

```powershell
php artisan test tests/Unit/ApprovalChainInputTest.php tests/Feature/AdminApprovalSettingsTest.php
```

Expected: 8 tests PASS with no failures.

- [ ] **Step 7: Commit the bulk fix**

```powershell
git add app/Http/Controllers/Admin/ApprovalRuleController.php tests/Feature/AdminApprovalSettingsTest.php
git commit -m "fix: validate bulk approvers within company"
```

### Task 4: Visible validation feedback

**Files:**
- Modify: `tests/Feature/AdminConfirmModalTest.php`
- Modify: `resources/views/admin/layouts/app.blade.php`

- [ ] **Step 1: Add the failing layout test**

Add to `AdminConfirmModalTest`:

```php
public function test_admin_layout_displays_validation_errors(): void
{
    $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));

    $this->assertStringContainsString('$errors->any()', $layout);
    $this->assertStringContainsString('$errors->all()', $layout);
    $this->assertStringContainsString('Data belum dapat disimpan', $layout);
}
```

- [ ] **Step 2: Run the layout test and verify RED**

Run:

```powershell
php artisan test tests/Feature/AdminConfirmModalTest.php --filter=validation_errors
```

Expected: FAIL because the admin layout does not render the validation error bag.

- [ ] **Step 3: Add the shared validation alert**

Insert after the existing `session('error')` alert in the admin layout:

```blade
@if($errors->any())
    <div class="flex items-start gap-2.5 px-4 py-3.5 rounded-lg text-[13.5px] mb-4 bg-red-50 text-red-800 border border-red-200 animate-slide-down">
        <span class="material-symbols-outlined text-[18px] mt-0.5">error</span>
        <div>
            <div class="font-semibold">Data belum dapat disimpan.</div>
            <ul class="list-disc list-inside mt-1 space-y-0.5 text-[12px]">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
```

- [ ] **Step 4: Run the layout tests and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/AdminConfirmModalTest.php
```

Expected: all `AdminConfirmModalTest` tests PASS.

- [ ] **Step 5: Commit visible feedback**

```powershell
git add resources/views/admin/layouts/app.blade.php tests/Feature/AdminConfirmModalTest.php
git commit -m "fix: show admin validation errors"
```

### Task 5: Full verification

**Files:**
- Verify all modified files.

- [ ] **Step 1: Run focused regression tests**

```powershell
php artisan test tests/Unit/ApprovalChainInputTest.php tests/Feature/AdminApprovalSettingsTest.php tests/Feature/AdminConfirmModalTest.php
```

Expected: all focused tests PASS with no failures.

- [ ] **Step 2: Run the complete test suite**

```powershell
php artisan test
```

Expected: exit code 0 and zero failed tests.

- [ ] **Step 3: Check formatting and syntax**

```powershell
vendor/bin/pint --test app/Support/ApprovalChainInput.php app/Http/Controllers/Admin/EmployeeApproverController.php app/Http/Controllers/Admin/ApprovalRuleController.php tests/Unit/ApprovalChainInputTest.php tests/Feature/AdminApprovalSettingsTest.php tests/Feature/AdminConfirmModalTest.php
php -l app/Support/ApprovalChainInput.php
php -l app/Http/Controllers/Admin/EmployeeApproverController.php
php -l app/Http/Controllers/Admin/ApprovalRuleController.php
```

Expected: Pint exit code 0 and every PHP syntax check reports no errors.

- [ ] **Step 4: Review the final diff**

```powershell
git diff HEAD~3..HEAD --check
git status --short
```

Expected: no whitespace errors and no unintended files.
