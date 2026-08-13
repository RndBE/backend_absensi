<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\KpiCrossItem;
use App\Models\KpiIndicator;
use App\Models\KpiIndicatorRubric;
use App\Models\KpiLevel;
use Illuminate\Database\Seeder;

/**
 * Isi awal master KPI sesuai Bab 2–6 kerangka (kpi-framework.md).
 *
 * Idempoten: aman dijalankan ulang. Baris yang sudah ada tidak ditimpa, supaya penyetelan
 * admin (bobot, penanda inti, teks rubrik) tidak hilang saat seeder dipanggil lagi.
 *
 * Level ikut dibuat di sini — bukan hanya dibaca — karena perusahaan yang dibuat SETELAH
 * migration berjalan tidak punya baris kpi_levels. Bobot defaultnya sengaja sama dengan
 * migration 2026_08_10_000001.
 */
class KpiFrameworkSeeder extends Seeder
{
    private array $levels = [
        ['code' => 'L1', 'name' => 'Direksi & Komisaris', 'is_assessed' => false, 'ex' => 0,  'co' => 0,  'ld' => 0,  'sort' => 1],
        ['code' => 'L2', 'name' => 'Manajer',             'is_assessed' => true,  'ex' => 40, 'co' => 15, 'ld' => 45, 'sort' => 2],
        ['code' => 'L3', 'name' => 'Leader/SPV',          'is_assessed' => true,  'ex' => 50, 'co' => 20, 'ld' => 30, 'sort' => 3],
        ['code' => 'L4', 'name' => 'Staff',               'is_assessed' => true,  'ex' => 70, 'co' => 25, 'ld' => 5,  'sort' => 4],
    ];

    /** Rubrik umum Bab 6.1, dipakai untuk indikator yang belum punya rubrik turunan sendiri. */
    private array $genericRubric = [
        5 => 'Konsisten, tanpa perlu diingatkan, sering melebihi kewajiban, menjadi rujukan bagi orang lain',
        4 => 'Hampir selalu baik, kekurangan kecil dan jarang terjadi',
        3 => 'Memenuhi kewajiban, kadang perlu diingatkan, ada kekurangan yang masih dapat ditoleransi',
        2 => 'Sering terlambat/tidak lengkap, harus ditagih berulang, mengganggu pekerjaan pihak lain',
        1 => 'Menghindar, menahan informasi, melempar tanggung jawab, menghambat pekerjaan',
    ];

    /** Empat rubrik turunan yang dicontohkan Bab 6.2. Sisanya masih memakai rubrik umum. */
    private array $specificRubrics = [
        'EX-L4-02' => [
            5 => '100% tugas selesai tepat waktu',
            4 => '≥ 90% tepat waktu',
            3 => '75–89% tepat waktu',
            2 => '60–74% tepat waktu',
            1 => '< 60% tepat waktu',
        ],
        'CO-L4-01' => [
            5 => 'Respons cepat tanpa diingatkan; sering membantu melebihi permintaan; menjadi rujukan divisi lain',
            4 => 'Selalu memenuhi permintaan tepat waktu dan lengkap; komunikasi jelas',
            3 => 'Memenuhi permintaan, kadang perlu diingatkan atau ada kekurangan yang harus dilengkapi ulang',
            2 => 'Sering terlambat, harus ditagih berulang, atau menyerahkan data tidak lengkap',
            1 => 'Menghindari permintaan, menahan informasi, melempar tanggung jawab',
        ],
        'LD-L3-03' => [
            5 => 'Coaching terjadwal, ada catatan tertulis, minimal 1 anggota naik kompetensi terukur',
            4 => 'Umpan balik rutin dan konsisten kepada seluruh anggota',
            3 => 'Umpan balik diberikan, tetapi umumnya hanya saat ada masalah',
            2 => 'Jarang, bersifat reaktif',
            1 => 'Tidak pernah dilakukan',
        ],
        'EX-L2-05' => [
            5 => 'Nihil temuan; sistem pengendalian internal berjalan mandiri',
            4 => 'Hanya temuan minor, seluruhnya ditutup tepat waktu',
            3 => 'Ada temuan minor, sebagian ditutup terlambat',
            2 => 'Ada temuan mayor, atau temuan minor berulang dari periode sebelumnya',
            1 => 'Temuan mayor berulang, atau pelanggaran regulasi eksternal',
        ],
    ];

    /**
     * Format tiap baris: [kode, nama, deskripsi ("yang dilihat"), bobot %, inti?, sumber otomatis].
     *
     * Penanda `inti` tidak ditentukan dokumen — Bab 9.1 hanya meminta 8–10 butir per level.
     * Aturan yang dipakai di sini: 10 butir per level, dibagi mengikuti bobot kategori
     * (L4 = 7 EX / 2 CO / 1 LD, L3 = 5/2/3, L2 = 4/2/4), diambil dari bobot tertinggi dan
     * melewati indikator yang diisi otomatis. Admin bebas menandai ulang.
     */
    private array $indicators = [
        'L4' => [
            'EX' => [
                ['EX-L4-01', 'Kualitas & akurasi output', 'Tingkat kesalahan, jumlah revisi, temuan atasan', 20, true, null],
                ['EX-L4-02', 'Ketepatan waktu penyelesaian', '% tugas selesai sesuai tenggat', 15, true, null],
                ['EX-L4-03', 'Produktivitas/volume vs target', 'Output aktual dibanding beban standar', 12, true, null],
                ['EX-L4-04', 'Penguasaan teknis pekerjaan', 'Menguasai alat, sistem, dan metode kerja', 12, true, null],
                ['EX-L4-05', 'Kemandirian & penyelesaian masalah rutin', 'Frekuensi harus dibantu untuk hal yang sudah diajarkan', 11, true, null],
                ['EX-L4-06', 'Kepatuhan SOP, K3, dan kebijakan', 'Pelanggaran, insiden, teguran', 10, true, null],
                ['EX-L4-07', 'Kelengkapan dokumentasi & pelaporan', 'Laporan lengkap, tepat waktu, dapat ditelusuri', 10, true, null],
                ['EX-L4-08', 'Kemauan belajar & peningkatan kompetensi', 'Mengikuti pelatihan, menerapkan hal baru', 10, false, null],
            ],
            'CO' => [
                ['CO-L4-01', 'Kerjasama antar divisi', 'Respons terhadap permintaan divisi lain, ketepatan waktu penyerahan data/dokumen', 20, false, KpiIndicator::SOURCE_CROSS_ASSESSMENT],
                ['CO-L4-02', 'Kerjasama dalam tim sendiri', 'Mau berbagi beban, tidak menghindari tugas bersama', 14, true, null],
                ['CO-L4-03', 'Kedisiplinan & kehadiran', 'Absensi, keterlambatan, izin mendadak', 14, true, null],
                ['CO-L4-04', 'Integritas & kejujuran', 'Melaporkan kesalahan sendiri, tidak memanipulasi data', 12, false, null],
                ['CO-L4-05', 'Inisiatif perbaikan kecil', 'Usulan yang benar-benar diterapkan', 13, false, null],
                ['CO-L4-06', 'Berbagi pengetahuan & bantu rekan', 'Mengajari rekan, membuat catatan kerja', 10, false, null],
                ['CO-L4-07', 'Partisipasi kegiatan perusahaan', 'Kehadiran & keterlibatan aktif', 9, false, null],
                ['CO-L4-08', 'Kepedulian aset & kerapian area kerja', 'Perawatan alat, housekeeping', 8, false, null],
            ],
            'LD' => [
                ['LD-L4-01', 'Membimbing rekan baru / berbagi cara kerja', 'Kesediaan dan efektivitas membimbing', 40, true, null],
                ['LD-L4-02', 'Kesediaan jadi PIC tugas atau kegiatan kecil', 'Mau mengambil tanggung jawab tambahan', 30, false, null],
                ['LD-L4-03', 'Proaktif menyampaikan usul, masalah, atau risiko ke atasan', 'Tidak diam saat melihat masalah', 30, false, null],
            ],
        ],

        'L3' => [
            'EX' => [
                ['EX-L3-01', 'Pencapaian target output tim', 'Realisasi vs target unit', 20, true, null],
                ['EX-L3-02', 'Kualitas hasil kerja tim', 'Error rate, komplain internal/eksternal, temuan', 15, true, null],
                ['EX-L3-03', 'Ketepatan waktu penyelesaian pekerjaan tim', 'Backlog, keterlambatan berulang', 12, true, null],
                ['EX-L3-04', 'Produktivitas & efisiensi sumber daya', 'Output per orang, lembur, pemborosan bahan/waktu', 12, true, null],
                ['EX-L3-05', 'Kompetensi teknis & kemampuan verifikasi kerja anggota', 'Mampu mengoreksi, bukan sekadar meneruskan', 12, true, null],
                ['EX-L3-06', 'Kepatuhan SOP, K3, dan regulasi di area tim', 'Insiden, pelanggaran anggota', 10, false, null],
                ['EX-L3-07', 'Akurasi & ketepatan pelaporan ke atasan', 'Laporan benar, tepat waktu, tanpa diminta berulang', 10, false, null],
                ['EX-L3-08', 'Pengendalian risiko operasional harian', 'Antisipasi masalah sebelum membesar', 9, false, null],
            ],
            'CO' => [
                ['CO-L3-01', 'Kerjasama antar divisi pada proses rutin', 'Ketepatan handover, pemenuhan SLA internal, tidak menahan pekerjaan divisi lain', 18, false, KpiIndicator::SOURCE_CROSS_ASSESSMENT],
                ['CO-L3-02', 'Keterlibatan dalam proyek/tim lintas divisi', 'Kontribusi nyata, bukan sekadar hadir rapat', 14, false, KpiIndicator::SOURCE_CROSS_ASSESSMENT],
                ['CO-L3-03', 'Penyelesaian masalah antar divisi', 'Diselesaikan setingkat, tidak semua dilempar ke atasan', 12, false, KpiIndicator::SOURCE_CROSS_ASSESSMENT],
                ['CO-L3-04', 'Penyusunan & pembaruan SOP/instruksi kerja', 'Dokumen yang benar-benar dipakai', 14, true, null],
                ['CO-L3-05', 'Inisiatif perbaikan proses yang terealisasi', 'Ada bukti sebelum–sesudah', 14, true, null],
                ['CO-L3-06', 'Knowledge sharing & pelatihan internal', 'Materi, sesi berbagi, on-job training', 11, false, null],
                ['CO-L3-07', 'Kedisiplinan & keteladanan', 'Menjadi contoh, bukan pengecualian', 10, false, null],
                ['CO-L3-08', 'Partisipasi program perusahaan', 'Keterlibatan aktif', 7, false, null],
            ],
            'LD' => [
                ['LD-L3-01', 'Perencanaan & pembagian tugas', 'Beban merata, jelas siapa mengerjakan apa', 14, true, null],
                ['LD-L3-02', 'Monitoring & tindak lanjut', 'Tahu status pekerjaan tanpa harus ditanya atasan', 14, true, null],
                ['LD-L3-03', 'Coaching & umpan balik rutin', 'Terjadwal, ada catatan, bukan hanya saat salah', 14, true, null],
                ['LD-L3-04', 'Penanganan anggota berkinerja rendah', 'Ada pembinaan bertahap dan terdokumentasi', 12, false, null],
                ['LD-L3-05', 'Pengembangan kompetensi anggota', 'Ada anggota yang naik kemampuannya secara terukur', 10, false, null],
                ['LD-L3-06', 'Akuntabilitas hasil tim', 'Tidak menyalahkan anggota atau divisi lain', 12, false, null],
                ['LD-L3-07', 'Manajemen konflik & iklim kerja', 'Konflik internal tertangani, tim tetap solid', 11, false, null],
                ['LD-L3-08', 'Komunikasi & kejelasan arahan', 'Anggota paham prioritas dan alasannya', 13, false, null],
            ],
        ],

        'L2' => [
            'EX' => [
                ['EX-L2-01', 'Pencapaian KPI departemen', 'Realisasi vs sasaran tahunan', 22, true, null],
                ['EX-L2-02', 'Efisiensi & pengendalian anggaran', 'Realisasi biaya vs budget, penghematan nyata', 15, true, null],
                ['EX-L2-03', 'Mutu layanan/produk unit', 'Komplain, tingkat pengembalian, kepuasan pengguna', 13, true, null],
                ['EX-L2-04', 'Ketepatan waktu program & proyek departemen', 'Milestone tercapai', 12, true, null],
                ['EX-L2-05', 'Kepatuhan regulasi & hasil audit', 'Jumlah dan tingkat keparahan temuan', 12, false, null],
                ['EX-L2-06', 'Kualitas perencanaan & mitigasi risiko', 'Target realistis, risiko teridentifikasi lebih awal', 12, false, null],
                ['EX-L2-07', 'Pengelolaan aset & sumber daya', 'Utilisasi, perawatan, tidak ada aset menganggur', 8, false, null],
                ['EX-L2-08', 'Kualitas & ketepatan pelaporan ke direksi', 'Akurat, tepat waktu, disertai analisis', 6, false, null],
            ],
            'CO' => [
                ['CO-L2-01', 'Kolaborasi lintas departemen pada proses end-to-end', 'Kelancaran alur kerja yang melewati beberapa divisi', 22, false, KpiIndicator::SOURCE_CROSS_ASSESSMENT],
                ['CO-L2-02', 'Memimpin/terlibat proyek lintas divisi', 'Peran nyata dan hasilnya', 18, false, KpiIndicator::SOURCE_CROSS_ASSESSMENT],
                ['CO-L2-03', 'Keterbukaan berbagi data, informasi, sumber daya', 'Tidak menahan informasi, mau meminjamkan orang/alat', 15, false, KpiIndicator::SOURCE_CROSS_ASSESSMENT],
                ['CO-L2-04', 'Penyelesaian sengketa antar divisi secara konstruktif', 'Menyelesaikan, bukan mengeskalasi atau mendiamkan', 15, false, KpiIndicator::SOURCE_CROSS_ASSESSMENT],
                ['CO-L2-05', 'Inisiatif strategis berdampak lintas unit', 'Usulan yang diadopsi perusahaan', 15, true, null],
                ['CO-L2-06', 'Kontribusi pada budaya & citra perusahaan', 'Menegakkan nilai perusahaan', 8, true, null],
                ['CO-L2-07', 'Keterlibatan program eksternal/CSR/relasi mitra', 'Bila relevan dengan perannya', 7, false, null],
            ],
            'LD' => [
                ['LD-L2-01', 'Pengembangan SDM & kaderisasi', 'Ada calon pengganti yang siap; anggota naik level', 16, true, null],
                ['LD-L2-02', 'Kualitas pengambilan keputusan', 'Berbasis data, tepat waktu, tidak menggantung', 15, true, null],
                ['LD-L2-03', 'Akuntabilitas & ownership', 'Bertanggung jawab atas hasil, termasuk kegagalan', 13, true, null],
                ['LD-L2-04', 'Menerjemahkan arahan direksi jadi rencana operasional', 'Strategi turun menjadi program kerja yang jalan', 12, true, null],
                ['LD-L2-05', 'Retensi & keterlibatan tim', 'Turnover, absensi, hasil survei iklim kerja', 10, false, null],
                ['LD-L2-06', 'Manajemen perubahan', 'Mampu membawa tim melewati perubahan sistem/kebijakan', 10, false, null],
                ['LD-L2-07', 'Komunikasi dua arah (atas–bawah)', 'Informasi mengalir, masukan bawahan didengar', 9, false, null],
                ['LD-L2-08', 'Penegakan disiplin & keadilan', 'Konsisten, tidak pilih kasih', 8, false, null],
                ['LD-L2-09', 'Keteladanan & integritas', 'Perilaku sesuai yang dituntut ke bawahan', 7, false, null],
            ],
        ],
    ];

    /**
     * Butir penilaian silang Bab 7.5 (Lapis A — divisi) dan Bab 7.6 (Lapis B — individu).
     * Format: [kode, nama, pertanyaan ke penilai, bobot %].
     */
    private array $crossItems = [
        'A' => [
            ['XA-01', 'Responsivitas', 'Seberapa cepat divisi ini menanggapi permintaan, pertanyaan, atau keluhan dari kami?', 15],
            ['XA-02', 'Ketepatan waktu penyerahan', 'Seberapa sering data, dokumen, barang, atau pekerjaan mereka sampai ke kami tepat waktu?', 15],
            ['XA-03', 'Kelengkapan & akurasi serah terima', 'Seberapa sering hasil kerja mereka bisa langsung kami pakai tanpa perlu diperbaiki atau dilengkapi ulang?', 15],
            ['XA-04', 'Keterbukaan informasi', 'Seberapa mudah kami memperoleh informasi atau data yang kami butuhkan dari mereka?', 12],
            ['XA-05', 'Sikap saat ada masalah', 'Ketika terjadi masalah bersama, seberapa besar mereka ikut mencari solusi dibanding mencari siapa yang salah?', 15],
            ['XA-06', 'Kepatuhan pada kesepakatan', 'Seberapa konsisten mereka menepati kesepakatan, jadwal, atau SLA yang sudah disetujui bersama?', 12],
            ['XA-07', 'Kemudahan berkomunikasi', 'Seberapa jelas dan sopan komunikasi mereka? Apakah mudah dihubungi saat dibutuhkan?', 8],
            ['XA-08', 'Inisiatif membantu', 'Seberapa sering mereka membantu melebihi kewajiban formalnya, atau mengingatkan kami sebelum masalah terjadi?', 8],
        ],
        'B' => [
            ['XB-01', 'Dapat diandalkan', 'Kalau orang ini berjanji, apakah biasanya ditepati?', 20],
            ['XB-02', 'Bersedia berkompromi demi tujuan perusahaan', 'Apakah dia mau menyesuaikan kepentingan divisinya demi hasil yang lebih baik bagi perusahaan?', 18],
            ['XB-03', 'Menyelesaikan masalah setingkat', 'Apakah dia menyelesaikan persoalan langsung dengan kami, atau langsung melapor ke atasan?', 17],
            ['XB-04', 'Mau mendengar & menerima masukan', 'Apakah dia terbuka terhadap kritik atau usulan dari luar divisinya?', 15],
            ['XB-05', 'Integritas & konsistensi', 'Apakah yang dia katakan di rapat sama dengan yang dia lakukan setelahnya?', 15],
            ['XB-06', 'Sikap terhadap orang di luar divisinya', 'Apakah dia memperlakukan staf divisi lain dengan hormat, termasuk yang levelnya di bawahnya?', 15],
        ],
    ];

    public function run(): void
    {
        $this->assertWeightsAreSane();

        foreach (Company::query()->pluck('id') as $companyId) {
            $this->seedCompany((int) $companyId);
            $this->seedCrossItems((int) $companyId);
        }
    }

    private function seedCrossItems(int $companyId): void
    {
        foreach ($this->crossItems as $layer => $rows) {
            foreach ($rows as $index => [$code, $name, $question, $weight]) {
                KpiCrossItem::firstOrCreate(
                    ['company_id' => $companyId, 'code' => $code],
                    [
                        'layer' => $layer,
                        'name' => $name,
                        'question' => $question,
                        'weight' => $weight,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedCompany(int $companyId): void
    {
        foreach ($this->levels as $level) {
            $model = KpiLevel::firstOrCreate(
                ['company_id' => $companyId, 'code' => $level['code']],
                [
                    'name' => $level['name'],
                    'is_assessed' => $level['is_assessed'],
                    'weight_excellence' => $level['ex'],
                    'weight_contribution' => $level['co'],
                    'weight_leadership' => $level['ld'],
                    'sort_order' => $level['sort'],
                    'is_active' => true,
                ]
            );

            foreach ($this->indicators[$level['code']] ?? [] as $category => $rows) {
                $this->seedIndicators($model, $category, $rows);
            }
        }
    }

    private function seedIndicators(KpiLevel $level, string $category, array $rows): void
    {
        foreach ($rows as $index => [$code, $name, $description, $weight, $isCore, $autoSource]) {
            $indicator = KpiIndicator::firstOrCreate(
                ['company_id' => $level->company_id, 'code' => $code],
                [
                    'kpi_level_id' => $level->id,
                    'category' => $category,
                    'name' => $name,
                    'description' => $description,
                    'weight' => $weight,
                    'is_core' => $isCore,
                    'is_auto_filled' => $autoSource !== null,
                    'auto_source' => $autoSource,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );

            $this->seedRubrics($indicator);
        }
    }

    private function seedRubrics(KpiIndicator $indicator): void
    {
        $rubric = $this->specificRubrics[$indicator->code] ?? $this->genericRubric;

        foreach ($rubric as $score => $description) {
            KpiIndicatorRubric::firstOrCreate(
                ['kpi_indicator_id' => $indicator->id, 'score' => $score],
                ['description' => $description]
            );
        }
    }

    /**
     * Jumlah bobot indikator per (level, kategori) harus 100. Salah ketik satu angka di tabel
     * di atas menggeser seluruh nilai akhir dan tidak akan kelihatan sampai skor dipakai
     * menentukan bonus — jadi diperiksa saat seeding, bukan nanti.
     */
    private function assertWeightsAreSane(): void
    {
        foreach ($this->indicators as $levelCode => $categories) {
            foreach ($categories as $category => $rows) {
                $total = array_sum(array_column($rows, 3));

                if ($total !== 100) {
                    throw new \RuntimeException(
                        "Bobot indikator {$levelCode}/{$category} berjumlah {$total}, seharusnya 100."
                    );
                }
            }
        }

        foreach ($this->levels as $level) {
            $total = $level['ex'] + $level['co'] + $level['ld'];

            if ($level['is_assessed'] && $total !== 100) {
                throw new \RuntimeException(
                    "Bobot kategori {$level['code']} berjumlah {$total}, seharusnya 100."
                );
            }
        }

        foreach ($this->crossItems as $layer => $rows) {
            $total = array_sum(array_column($rows, 3));

            if ($total !== 100) {
                throw new \RuntimeException(
                    "Bobot butir penilaian silang Lapis {$layer} berjumlah {$total}, seharusnya 100."
                );
            }
        }
    }
}
