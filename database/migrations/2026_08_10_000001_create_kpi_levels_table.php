<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bobot default mengikuti Bab 2 kerangka KPI. Disimpan per perusahaan supaya tiap PT
     * bisa menyetel sendiri tanpa mengubah kode. L1 tidak dinilai, bobotnya nol.
     */
    private array $defaults = [
        ['code' => 'L1', 'name' => 'Direksi & Komisaris', 'is_assessed' => false, 'ex' => 0,  'co' => 0,  'ld' => 0,  'sort' => 1],
        ['code' => 'L2', 'name' => 'Manajer',             'is_assessed' => true,  'ex' => 40, 'co' => 15, 'ld' => 45, 'sort' => 2],
        ['code' => 'L3', 'name' => 'Leader/SPV',          'is_assessed' => true,  'ex' => 50, 'co' => 20, 'ld' => 30, 'sort' => 3],
        ['code' => 'L4', 'name' => 'Staff',               'is_assessed' => true,  'ex' => 70, 'co' => 25, 'ld' => 5,  'sort' => 4],
    ];

    public function up(): void
    {
        Schema::create('kpi_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 5);
            $table->string('name');
            $table->boolean('is_assessed')->default(true);
            $table->decimal('weight_excellence', 5, 2)->default(0);
            $table->decimal('weight_contribution', 5, 2)->default(0);
            $table->decimal('weight_leadership', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        if (! Schema::hasTable('companies')) {
            return;
        }

        $now = now();
        $rows = [];

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($this->defaults as $level) {
                $rows[] = [
                    'company_id' => $companyId,
                    'code' => $level['code'],
                    'name' => $level['name'],
                    'is_assessed' => $level['is_assessed'],
                    'weight_excellence' => $level['ex'],
                    'weight_contribution' => $level['co'],
                    'weight_leadership' => $level['ld'],
                    'sort_order' => $level['sort'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('kpi_levels')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_levels');
    }
};
