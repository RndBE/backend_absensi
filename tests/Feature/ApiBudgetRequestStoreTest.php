<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BudgetController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiBudgetRequestStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('budget_request_participants');
        Schema::dropIfExists('budget_request_items');
        Schema::dropIfExists('budget_requests');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->string('type');
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('budget_request_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
        });

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->integer('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size')->default(0);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function test_budget_request_accepts_items_json_string_from_multipart_form(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'full_name' => 'Requester',
            'email' => 'requester@example.test',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employee = Employee::findOrFail(1);
        $request = Request::create('/api/budget/requests', 'POST', [
            'type' => 'budget',
            'title' => 'Biaya perjalanan dinas',
            'description' => 'Keperluan meeting klien',
            'items' => json_encode([
                [
                    'type' => 'transport',
                    'description' => 'Tiket kereta',
                    'amount' => 250000,
                ],
            ]),
        ]);
        $request->setUserResolver(fn () => $employee);

        $response = app(BudgetController::class)->store($request);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertDatabaseHas('budget_requests', [
            'employee_id' => 1,
            'title' => 'Biaya perjalanan dinas',
            'total_amount' => 250000,
        ]);
        $this->assertDatabaseHas('budget_request_items', [
            'type' => 'transport',
            'description' => 'Tiket kereta',
            'amount' => 250000,
        ]);
    }
}
