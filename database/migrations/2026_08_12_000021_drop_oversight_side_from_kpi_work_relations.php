<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengawasan tidak lagi disimpan — diturunkan dari `employees.manager_id` saat halaman dibuka.
     * Lihat App\Support\KpiWorkChainOverseers.
     *
     * "Atasan mengetahui koordinasi anak buahnya" bukan hubungan antar dua orang, melainkan fakta
     * antara seorang atasan dan sebuah RANTAI. Memaksanya masuk tabel pasangan menghasilkan tiga
     * kerusakan sekaligus:
     *
     *   1. Satu kenyataan jadi banyak baris. Nofiyanto mengetahui koordinasi inventory timnya —
     *      satu fakta — tercatat 5 baris (ke Rhomadoni, Rasyid, Anisa, Lina, Maritza), dan 11 baris
     *      di tiga rantai. Di graf dia jadi simpul terpadat kedua, seolah berurusan dengan
     *      lima orang itu satu per satu.
     *   2. Penandaan tangan selalu bolong. Di website inventory tercatat Nofiyanto (Manajer, dua
     *      tingkat di atas Shandy) tetapi tidak Fadel — Leader Software, atasan LANGSUNG Shandy,
     *      yang justru lebih tahu. Wahyu di bahan produksi, Rhomadoni di laporan hasil riset, dan
     *      seluruh pengawas rantai desain untuk RnD juga terlewat.
     *   3. Melengkapinya dengan tangan berarti 2–6 penanda per rantai yang harus ditambah ulang
     *      setiap kali ada peserta baru — perawatan yang pasti terlupa.
     *
     * Seluruhnya sudah ada di garis `manager_id`, jadi menyimpannya hanya menduplikasi struktur
     * organisasi dalam bentuk yang bisa basi. Setelah ini tabel `kpi_work_relations` kembali berarti
     * satu hal saja: siapa benar-benar menyerahkan apa kepada siapa.
     */
    public function up(): void
    {
        // 19 baris pengawasan bukan serah terima — di model baru tidak ada tempatnya di tabel ini.
        DB::table('kpi_work_relations')->whereNotNull('oversight_side')->delete();

        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->dropColumn('oversight_side');
        });
    }

    /**
     * Kolomnya bisa dikembalikan, isinya tidak: penanda lama sudah dihapus dan tidak tercatat di
     * mana pun selain riwayat git migration 2026_08_12_000020.
     */
    public function down(): void
    {
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->string('oversight_side', 4)->nullable()->after('label');
        });
    }
};
