<?php

use App\Models\KpiWorkRelation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sifat hubungan: serah terima langsung, atau atasan yang mengetahui.
     *
     * Selama ini pembedaan itu hanya hidup di lembar konfirmasi, tidak pernah masuk basis data,
     * jadi halaman Rantai Kerja menampilkan 98 pasangan rata semua — "Lina → Subarkah" tampak
     * sama saja dengan "Lina → Rhomadoni" padahal yang pertama pengawasan dan yang kedua serah
     * terima barang. Manajer yang meninjau peta tidak punya cara membedakannya.
     *
     * Yang TIDAK masuk sini: pembedaan "disebut langsung / turunan divisi / tebakan". Itu soal
     * dari mana keterangannya berasal — riwayat proses konfirmasi, bukan sifat hubungannya —
     * jadi tempatnya memang di lembar konfirmasi, bukan di kolom basis data.
     */
    public function up(): void
    {
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->string('nature', 16)->default(KpiWorkRelation::NATURE_DIRECT)->after('label');
        });

        $this->backfillOversight();
    }

    public function down(): void
    {
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->dropColumn('nature');
        });
    }

    /**
     * 19 pasangan pengawasan hasil konfirmasi manajemen 12 Agustus 2026. Ditulis di sini, bukan
     * ditunggu dari seeder, supaya datanya benar begitu migration selesai — tanpa mengandalkan
     * admin ingat menjalankan `db:seed`.
     */
    private function backfillOversight(): void
    {
        $oversight = [
            ['Bahan produksi', 'LINA WIDIASTUTI', 'MUHAMMAD SUBARKAH'],
            ['Barang riset & proyek', 'LINA WIDIASTUTI', 'MUHAMMAD SUBARKAH'],
            ['SPK', 'ZAINNI NOVENA SANTI', 'MUHAMMAD SUBARKAH'],
            ['Website inventory', 'SHANDY BAGUS FERDIANSYAH', 'WAHYU NURUL HARYANTO'],
            ['Website inventory', 'NOFIYANTO', 'RHOMADONI'],
            ['Website inventory', 'NOFIYANTO', 'RASYID PRIYO NUGROHO'],
            ['Website inventory', 'NOFIYANTO', 'ANISA FEBRIYANTI'],
            ['Website inventory', 'NOFIYANTO', 'LINA WIDIASTUTI'],
            ['Website inventory', 'NOFIYANTO', 'MARITZA ISYAURA PUTRI RIZMA'],
            ['CRM penawaran', 'NOFIYANTO', 'DEWI SETIAWATI'],
            ['CRM penawaran', 'NOFIYANTO', 'AKHMAD ZAENI MUSTOFA'],
            ['CRM penawaran', 'NOFIYANTO', 'AFIF FAISHAHUDA'],
            ['HRIS absensi & payroll', 'SHANDY BAGUS FERDIANSYAH', 'WAHYU NURUL HARYANTO'],
            ['HRIS absensi & payroll', 'NOFIYANTO', 'AVISSA NOVA FAUZISTIKA'],
            ['HRIS absensi & payroll', 'NOFIYANTO', 'WAHYU NURUL HARYANTO'],
            ['HRIS absensi & payroll', 'NOFIYANTO', 'MARITZA ISYAURA PUTRI RIZMA'],
            ['Target penyelesaian proyek', 'DEWI SETIAWATI', 'MUHAMMAD SUBARKAH'],
            ['Target penyelesaian proyek', 'AKHMAD ZAENI MUSTOFA', 'MUHAMMAD SUBARKAH'],
            ['Target penyelesaian proyek', 'AFIF FAISHAHUDA', 'MUHAMMAD SUBARKAH'],
        ];

        $ids = DB::table('employees')->pluck('id', 'full_name');

        foreach ($oversight as [$label, $from, $to]) {
            if (! isset($ids[$from], $ids[$to])) {
                continue; // karyawan resign atau berganti nama tidak boleh menggagalkan migration
            }

            DB::table('kpi_work_relations')
                ->where('label', $label)
                ->where('from_employee_id', $ids[$from])
                ->where('to_employee_id', $ids[$to])
                ->update(['nature' => KpiWorkRelation::NATURE_OVERSIGHT]);
        }
    }
};
