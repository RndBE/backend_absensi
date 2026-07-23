<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_regulation_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_regulation_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->timestamps();

            $table->index('company_regulation_id');
        });

        if (! Schema::hasTable('company_regulations')) {
            return;
        }

        $now = now();

        DB::table('company_regulations')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->get(['id', 'file_path', 'file_name', 'file_size', 'file_mime'])
            ->each(function ($regulation) use ($now) {
                DB::table('company_regulation_attachments')->insert([
                    'company_regulation_id' => $regulation->id,
                    'file_path' => $regulation->file_path,
                    'file_name' => $regulation->file_name,
                    'file_size' => $regulation->file_size,
                    'file_mime' => $regulation->file_mime,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_regulation_attachments');
    }
};
