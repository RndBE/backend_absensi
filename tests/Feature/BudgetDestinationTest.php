<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BudgetController;
use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterDestination;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Support\BudgetDestination;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Tujuan perjalanan (label, titik, asal jarak) disimpan di pengajuan anggaran dan ditampilkan ke
 * approver, termasuk tanda untuk titik yang dipilih sendiri di peta.
 */
class BudgetDestinationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['notifications', 'request_attachments', 'employee_approvers', 'budget_request_participants', 'budget_request_items', 'budget_requests', 'travel_zones', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });
        Schema::create('travel_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone');
            $table->string('name');
            $table->unsignedInteger('min_km');
            $table->unsignedInteger('max_km')->nullable();
            $table->decimal('meal_allowance', 15, 2)->default(0);
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
            $table->unsignedBigInteger('assignment_letter_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedBigInteger('travel_zone_id')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_24_100002_add_destination_to_budget_requests_table.php'))->up();
        Schema::create('budget_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('employee_id')->nullable();
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

        DB::table('employees')->insert([
            'id' => 1, 'full_name' => 'Pengaju', 'email' => 'pengaju@example.test', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('travel_zones')->insert([
            'zone' => 'B', 'name' => 'Zona B', 'min_km' => 101, 'max_km' => null, 'meal_allowance' => 75000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_destination_from_the_app_is_saved_with_the_budget(): void
    {
        $response = $this->store([
            'distance_km' => 120,
            'destination_label' => 'titik peta: Jalan Pahlawan, Pleburan (Semarang, Jawa Tengah)',
            'destination_lat' => '-6.9903',
            'destination_lng' => '110.4231',
            'distance_source' => 'map',
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $budget = BudgetRequest::firstOrFail();
        $this->assertSame(120, (int) $budget->distance_km);
        $this->assertSame('titik peta: Jalan Pahlawan, Pleburan (Semarang, Jawa Tengah)', $budget->destination_label);
        $this->assertSame(-6.9903, $budget->destination_lat);
        $this->assertSame(110.4231, $budget->destination_lng);
        $this->assertSame('map', $budget->distance_source);

        $json = $budget->toArray();
        $this->assertSame('Titik peta, dipilih sendiri', $json['distance_source_label']);
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=-6.9903,110.4231', $json['destination_map_url']);
    }

    public function test_a_regency_destination_keeps_its_regency(): void
    {
        $this->store([
            'distance_km' => 98,
            'destination_label' => 'Kendal (ibukota Kabupaten Kendal)',
            'destination_lat' => '-6.9217033',
            'destination_lng' => '110.2040344',
            'distance_source' => 'regency',
            'destination_regency_id' => 3324,
        ]);

        $budget = BudgetRequest::firstOrFail();
        $this->assertSame(3324, (int) $budget->destination_regency_id);
        $this->assertSame('Ibukota kabupaten/kota', $budget->distance_source_label);
    }

    public function test_without_a_distance_no_destination_is_kept(): void
    {
        $this->store(['destination_label' => 'Kendal', 'distance_source' => 'regency']);

        $budget = BudgetRequest::firstOrFail();
        $this->assertNull($budget->destination_label);
        $this->assertNull($budget->distance_source);
        $this->assertNull($budget->destination_map_url);
    }

    public function test_an_unknown_source_or_half_a_point_is_refused(): void
    {
        foreach ([['distance_source' => 'tebakan'], ['destination_lat' => '-6.99']] as $fields) {
            try {
                $this->store(['distance_km' => 120, ...$fields]);
                $this->fail('harus ditolak: '.json_encode($fields));
            } catch (ValidationException) {
                $this->assertSame(0, BudgetRequest::count());
            }
        }
    }

    public function test_a_letter_with_distances_names_its_farthest_location(): void
    {
        $letter = new AssignmentLetter();
        $letter->setRelation('destinations', collect([
            new AssignmentLetterDestination(['address' => 'Kantor BBWS, Sleman', 'distance_km' => 12]),
            new AssignmentLetterDestination(['address' => 'Bendungan Bener, Purworejo', 'distance_km' => 74.5]),
            new AssignmentLetterDestination(['address' => 'Rapat daring', 'distance_km' => null]),
        ]));

        $this->assertSame([
            'destination_label' => 'Bendungan Bener, Purworejo',
            'destination_lat' => null,
            'destination_lng' => null,
            'distance_source' => 'letter',
            'destination_regency_id' => null,
        ], BudgetDestination::letterAttributes($letter));
    }

    public function test_approvers_see_the_point_on_a_map_and_a_warning_for_manual_points(): void
    {
        $budget = new BudgetRequest([
            'destination_label' => 'titik peta: Jalan Pahlawan',
            'destination_lat' => -6.9903,
            'destination_lng' => 110.4231,
            'distance_source' => 'map',
        ]);

        $html = (string) view('partials.budget-destination', ['budgetRequest' => $budget]);

        $this->assertStringContainsString('titik peta: Jalan Pahlawan', $html);
        $this->assertStringContainsString('Titik peta, dipilih sendiri', $html);
        $this->assertStringContainsString('Titik ini dipilih sendiri oleh pengaju', $html);
        $this->assertStringContainsString('marker=-6.9903,110.4231', $html);
        $this->assertStringContainsString('https://www.google.com/maps/search/?api=1&amp;query=-6.9903,110.4231', $html);
    }

    public function test_approvers_see_no_warning_for_a_regency_and_nothing_without_a_destination(): void
    {
        $regency = new BudgetRequest(['destination_label' => 'Kendal (ibukota Kabupaten Kendal)', 'distance_source' => 'regency']);
        $html = (string) view('partials.budget-destination', ['budgetRequest' => $regency]);
        $this->assertStringContainsString('Ibukota kabupaten/kota', $html);
        $this->assertStringNotContainsString('dipilih sendiri oleh pengaju', $html);
        $this->assertStringNotContainsString('<iframe', $html, 'tanpa titik, tanpa peta');

        $this->assertSame('', trim((string) view('partials.budget-destination', ['budgetRequest' => new BudgetRequest()])));
    }

    private function store(array $fields)
    {
        $request = Request::create('/api/budget/requests', 'POST', [
            'type' => 'budget',
            'title' => 'Perjalanan dinas',
            'items' => json_encode([['type' => 'meal', 'description' => 'Uang makan', 'amount' => 150000]]),
            ...$fields,
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        return app(BudgetController::class)->store($request);
    }
}
