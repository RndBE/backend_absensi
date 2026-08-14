<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautan tinjauan peta rantai kerja untuk para Manajer, tanpa login, plus catatan siapa
     * mengubah apa.
     *
     * ══ Kenapa tanpa login ══
     *
     * Manajer perlu memeriksa peta sekali dua kali lalu selesai. Membuatkan mereka akun admin
     * berarti memberi jalan masuk permanen ke seluruh HRIS — termasuk payroll — hanya untuk
     * pekerjaan sepekan. Tautan bertoken memberi akses yang tepat sebesar tugasnya dan bisa
     * dicabut kapan saja.
     *
     * ══ Yang menahan risikonya ══
     *
     * Token 64 karakter disimpan sebagai sha256, sama seperti `employee_magic_links` — kebocoran
     * basis data tidak menyerahkan tautan yang bisa dipakai. Tautannya bisa kedaluwarsa
     * (`expires_at`) dan dicabut (`revoked_at`), setiap pembukaan dicatat (`last_used_at`,
     * `use_count`), dan setiap perubahan masuk `kpi_work_chain_edit_logs` beserta IP-nya.
     *
     * Berbeda dari magic link portal karyawan, tautan ini TIDAK sekali pakai: manajer akan
     * membukanya berkali-kali selama masa tinjauan. Karena itu tidak ada `used_at` yang
     * menghanguskan, hanya jejak pemakaian.
     */
    public function up(): void
    {
        Schema::create('kpi_work_chain_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->string('last_ip_address', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'expires_at']);
        });

        /*
         * Catatan perubahan berdiri sendiri, tidak ikut terhapus bersama tautannya
         * (`nullOnDelete`). Justru pada saat tautan dicabut karena disalahgunakan, catatannya
         * yang paling dibutuhkan.
         *
         * `actor_employee_id` diisi juga untuk perubahan dari halaman admin internal, supaya satu
         * tabel ini memuat riwayat lengkap — bukan cuma yang datang dari tautan tinjauan.
         */
        Schema::create('kpi_work_chain_edit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('kpi_work_chain_reviewers')->nullOnDelete();
            $table->foreignId('actor_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('source', 16); // 'review' (tautan manajer) | 'admin' (halaman internal)
            $table->string('action', 24);
            $table->string('label', 80);
            $table->json('detail');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_work_chain_edit_logs');
        Schema::dropIfExists('kpi_work_chain_reviewers');
    }
};
