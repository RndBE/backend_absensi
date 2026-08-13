<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mengganti `nature` dengan `oversight_side`: bukan cuma ADA pengawasan, tapi SIAPA yang
     * mengetahui.
     *
     * `nature` (ditambahkan beberapa jam sebelumnya di migration 000019) salah rancang. Kolom itu
     * hanya menyatakan sebuah pasangan bersifat pengawasan, lalu tampilan menempelkan lencana
     * "mengetahui" di sisi `to` — padahal pihak yang mengetahui tidak selalu di sana:
     *
     *   Lina → Subarkah          Subarkah (Manajer Hardware) yang mengetahui  → sisi `to`
     *   Nofiyanto → Rhomadoni    Nofiyanto (Manajer Software) yang mengetahui → sisi `from`
     *
     * Akibatnya lencana muncul di orang yang salah: "Nofiyanto → Anisa Febriyanti (mengetahui)",
     * seolah Anisa yang cuma tahu, padahal dia justru pemakai sistemnya.
     *
     * Sisinya tidak bisa disimpulkan dari jabatan. Wahyu (L2) memang pihak yang mengetahui di
     * rantai website inventory dan HRIS, tetapi pelaku serah terima langsung di rantai pembayaran
     * klien & faktur — jabatan yang sama, peran berbeda per rantai. Karena itu sisinya harus
     * dicatat, bukan ditebak.
     *
     * Nilai: null = serah terima langsung, 'from' / 'to' = sisi yang hanya mengetahui.
     */
    public function up(): void
    {
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->string('oversight_side', 4)->nullable()->after('label');
        });

        $this->backfill();

        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->dropColumn('nature');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->string('nature', 16)->default('direct')->after('label');
        });

        DB::table('kpi_work_relations')->whereNotNull('oversight_side')->update(['nature' => 'oversight']);

        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->dropColumn('oversight_side');
        });
    }

    /**
     * 19 pasangan pengawasan hasil konfirmasi manajemen 12 Agustus 2026, kini dengan sisinya.
     * Delapan di sisi `to` (Manajer sebagai penerima kabar), sebelas di sisi `from` (Manajer
     * Software yang mengetahui pekerjaan anak buahnya atas sistem yang dia pegang).
     */
    private function backfill(): void
    {
        $oversight = [
            // Manajer di sisi penerima: dia yang diberi tahu.
            ['to', 'Bahan produksi', 'LINA WIDIASTUTI', 'MUHAMMAD SUBARKAH'],
            ['to', 'Barang riset & proyek', 'LINA WIDIASTUTI', 'MUHAMMAD SUBARKAH'],
            ['to', 'SPK', 'ZAINNI NOVENA SANTI', 'MUHAMMAD SUBARKAH'],
            ['to', 'Website inventory', 'SHANDY BAGUS FERDIANSYAH', 'WAHYU NURUL HARYANTO'],
            ['to', 'HRIS absensi & payroll', 'SHANDY BAGUS FERDIANSYAH', 'WAHYU NURUL HARYANTO'],
            ['to', 'Target penyelesaian proyek', 'DEWI SETIAWATI', 'MUHAMMAD SUBARKAH'],
            ['to', 'Target penyelesaian proyek', 'AKHMAD ZAENI MUSTOFA', 'MUHAMMAD SUBARKAH'],
            ['to', 'Target penyelesaian proyek', 'AFIF FAISHAHUDA', 'MUHAMMAD SUBARKAH'],

            // Manajer Software di sisi pemberi: dia mengetahui pekerjaan anak buahnya, bukan
            // mengerjakan serah terimanya.
            ['from', 'Website inventory', 'NOFIYANTO', 'RHOMADONI'],
            ['from', 'Website inventory', 'NOFIYANTO', 'RASYID PRIYO NUGROHO'],
            ['from', 'Website inventory', 'NOFIYANTO', 'ANISA FEBRIYANTI'],
            ['from', 'Website inventory', 'NOFIYANTO', 'LINA WIDIASTUTI'],
            ['from', 'Website inventory', 'NOFIYANTO', 'MARITZA ISYAURA PUTRI RIZMA'],
            ['from', 'CRM penawaran', 'NOFIYANTO', 'DEWI SETIAWATI'],
            ['from', 'CRM penawaran', 'NOFIYANTO', 'AKHMAD ZAENI MUSTOFA'],
            ['from', 'CRM penawaran', 'NOFIYANTO', 'AFIF FAISHAHUDA'],
            ['from', 'HRIS absensi & payroll', 'NOFIYANTO', 'AVISSA NOVA FAUZISTIKA'],
            ['from', 'HRIS absensi & payroll', 'NOFIYANTO', 'WAHYU NURUL HARYANTO'],
            ['from', 'HRIS absensi & payroll', 'NOFIYANTO', 'MARITZA ISYAURA PUTRI RIZMA'],
        ];

        $ids = DB::table('employees')->pluck('id', 'full_name');

        foreach ($oversight as [$side, $label, $from, $to]) {
            if (! isset($ids[$from], $ids[$to])) {
                continue; // karyawan resign atau berganti nama tidak boleh menggagalkan migration
            }

            DB::table('kpi_work_relations')
                ->where('label', $label)
                ->where('from_employee_id', $ids[$from])
                ->where('to_employee_id', $ids[$to])
                ->update(['oversight_side' => $side]);
        }
    }
};
