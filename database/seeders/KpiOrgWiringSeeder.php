<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiCrossAssessor;
use App\Models\KpiCrossLayerA;
use App\Models\KpiCrossLayerB;
use App\Models\KpiDivisionRelation;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Models\KpiWorkRelation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penyetelan organisasi KPI: garis penilai, penanda divisi, matriks relasi kerja, penilai
 * silang, dan periode uji coba pertama.
 *
 * Dipisahkan dari KpiFrameworkSeeder karena isinya beda sifat. KpiFrameworkSeeder berisi
 * master yang berlaku untuk perusahaan mana pun (level, indikator, rubrik, butir silang);
 * berkas ini berisi keputusan struktur organisasi PT. ARTA TEKNOLOGI COMUNINDO yang tidak
 * bisa diturunkan dari data mana pun dan harus ditetapkan manusia.
 *
 * Idempoten: aman dijalankan ulang. Nama yang tidak ditemukan dilewati tanpa menggagalkan
 * seeder — karyawan resign atau berganti nama tidak boleh membuat `db:seed` mati.
 *
 * ══ Kenapa garis penilai perlu diperbaiki ══
 *
 * Bab 2.1 kerangka menetapkan L4 dinilai Leader/SPV (L3) 70% dan Manajer (L2) 30%. Di data
 * awal seluruh staf L4 menunjuk langsung ke Manajer divisinya, sehingga App\Support\KpiAssessorMap
 * — yang membaca rantai `manager_id` — menaikkan semuanya satu tingkat: Manajer jadi penilai
 * utama dan Direktur tersedot jadi penilai pendukung 19 staf L4. Bab 2.1 tidak pernah
 * menempatkan L1 sebagai penilai L4, dan Bab 9.1 memperingatkan penilai akan kewalahan pada
 * beban sebesar itu (27 assessment × ±20 butir ≈ 550 kolom isian untuk satu orang).
 *
 * ══ Pengecualian yang disengaja ══
 *
 * RnD (Hardware) serta Purchasing dan Project Operation Admin (FAT) tetap menunjuk Manajernya.
 * Leader L3 di kedua divisi itu — Production dan Tax Officer — bukan atasan fungsional mereka,
 * jadi memaksakan pola Bab 2.1 di sana justru menghasilkan penilai yang tidak tahu pekerjaan
 * yang dinilai. Accounting & Finance tidak ikut dikecualikan: atasan fungsionalnya memang
 * Leader L3 di divisinya.
 */
class KpiOrgWiringSeeder extends Seeder
{
    /** Departemen yang berdiri sebagai divisi di matriks silang: kode + status layanan umum. */
    private array $divisions = [
        'FAT & SUPPLY CHAIN' => ['code' => 'FAT', 'shared_service' => true],
        'HRD & CORPORATE SERVICE' => ['code' => 'HRD', 'shared_service' => true],
        'SOFTWARE DIVISION' => ['code' => 'SOFT', 'shared_service' => false],
        'HARDWARE DIVISION' => ['code' => 'HARD', 'shared_service' => false],
        'MARKETING & SALES' => ['code' => 'MKT', 'shared_service' => false],
    ];

    /**
     * Departemen yang dicopot dari daftar divisi. Bab 7.3 melarang menilai divisi sendiri dan
     * Bab 2.1 tidak menilai L1 — Direksi dan akun sistem tidak punya peran di matriks silang,
     * dan membiarkannya bertanda divisi membuat halaman matriks selalu melaporkan masalah
     * "mitra kurang dari 3" yang tidak akan pernah bisa dibereskan.
     */
    private array $nonDivisions = ['BOARD OF DIRECTORS', 'SUPERADMIN'];

    /** Staf L4 → Leader L3 divisinya (Bab 2.1). Kunci: yang dinilai, nilai: atasan langsung baru. */
    private array $managerLines = [
        // Hardware Division — Leader produksi memimpin produksi, welder, dan admin produksi.
        'ENDARTO NUGROHO' => 'RHOMADONI',
        'RASYID PRIYO NUGROHO' => 'RHOMADONI',
        'ALVIAN RISWANDANU' => 'RHOMADONI',
        'ANISA FEBRIYANTI' => 'RHOMADONI',

        // HRD & Corporate Service — tidak punya L2; HRD (L3) memimpin security dan supporting staff.
        'MUHAMMAD FAUZAN' => 'AVISSA NOVA FAUZISTIKA',
        'MUH YUSUF KRISTANTO' => 'AVISSA NOVA FAUZISTIKA',
        'AGUNG PRABOWO' => 'AVISSA NOVA FAUZISTIKA',

        // Marketing & Sales
        'AFIF FAISHAHUDA' => 'AKHMAD ZAENI MUSTOFA',
        'FRANSISCUS XAVERIUS OKKA SEPTA PRATAMA' => 'AKHMAD ZAENI MUSTOFA',

        // Software Division
        'SHANDY BAGUS FERDIANSYAH' => 'FADEL MUHAMMAD IRSYAD',
        'TATA AZKIA AZZAHRA' => 'FADEL MUHAMMAD IRSYAD',
        'AKHMAD SYARIF ABDULLAH' => 'FADEL MUHAMMAD IRSYAD',

        // FAT & Supply Chain — hanya Accounting & Finance. Purchasing dan Project Operation
        // Admin tetap ke Manajer; Tax Officer bukan atasan fungsional keduanya.
        'MARITZA ISYAURA PUTRI RIZMA' => 'DEWI PUSPORINI',
    ];

    /**
     * Level KPI untuk karyawan yang `job_level`-nya tidak bisa dipakai sebagai dasar.
     *
     * `kpi_level_id` di-backfill dari `job_level` (lihat migration 2026_08_10_000003), jadi
     * karyawan dengan job_level kosong tidak pernah dapat level dan selalu tersangkut di daftar
     * "belum bisa dinilai". Diisi di sini, bukan dengan mengisi `job_level`-nya: kolom itu dasar
     * App\Models\ApprovalRule::min_approver_level, dan mengubahnya akan menggeser alur
     * persetujuan izin dan lembur tanpa diminta.
     */
    private array $kpiLevelOverrides = [
        'HARYANTO' => 'L4', // Security alih daya — tetap dinilai, setara security lain.
    ];

    /**
     * Staf L4 lintas fungsi (Bab 7.2) — masuk Lapis B walaupun bukan L2/L3, karena tugas
     * hariannya memang berhubungan dengan divisi lain. Ikut menentukan campuran A/B: L4 biasa
     * murni mengikuti nilai divisi, L4 lintas fungsi 50/50 (KpiCrossScoreCalculator::MIX).
     *
     * Daftar ini OTORITATIF: nama yang tidak tercantum akan di-set false. Kalau hanya menambah,
     * penanda orang yang dicabut dari daftar akan menempel selamanya di basis data dan berbeda
     * dari berkas ini — persoalan serius karena Bab 7.2 menuntut penandaan bisa diaudit sebagai
     * keadaan di awal periode.
     *
     * JANGAN jalankan seeder ini di tengah periode yang sudah dibuka. `is_cross_functional`
     * dibaca langsung, tidak ikut dibekukan ke snapshot periode, jadi mengubahnya setelah
     * pengisian jalan akan menggeser siapa yang dinilai personal beserta campuran A/B-nya —
     * persis yang dilarang Bab 7.2.
     */
    private array $crossFunctional = [
        // Penghubung terberat: menyentuh tiga divisi atau lebih setiap hari.
        'ZAINNI NOVENA SANTI',         // Project Operation Admin — invoice, SPK, harga modal CRM
        'SHANDY BAGUS FERDIANSYAH',    // Software — pegang inventory, CRM, dan HRIS
        'MARITZA ISYAURA PUTRI RIZMA', // Accounting & Finance — faktur, pembayaran klien, harga modal
        'AFIF FAISHAHUDA',             // Corporate Account Manager — pembayaran, target proyek, CRM

        // Titik serah terima ke satu atau dua divisi.
        'LINA WIDIASTUTI',             // Purchasing — bahan produksi dan barang riset ke Hardware
        'ANISA FEBRIYANTI',            // Admin Production — serah terima pekerjaan, inventory
        'PRASTOWO DIAN KRISTIYANTO',   // RnD — riset dengan Software, kustomisasi item, laporan riset
        'ILHAM YOGA PRATAMA',          // RnD — sama seperti Prastowo
        'RASYID PRIYO NUGROHO',        // Production — target penyelesaian proyek dengan Marketing
        'TATA AZKIA AZZAHRA',          // UI/UX Designer — desain untuk RnD; relasi paling tipis di daftar ini
    ];

    /** Bukan orang: akun demo dan akun sistem tidak pernah masuk penilaian. */
    private array $excluded = ['Apple Demo Account'];

    /**
     * Penilai silang resmi per divisi (Bab 7.4). Hanya L2 dan L3 yang menilai Lapis B (individu);
     * staf L4 mengisi Lapis A saja — manajer dan leader yang mengoordinasikan seluruh rantai kerja
     * lintas divisi, jadi mereka memang punya dasar menilai orangnya.
     *
     * Staf L4 di sini dipilih dari rantai kerja yang benar-benar berjalan, bukan dari jabatan
     * (Bab 7.4: "penilai dipilih berdasarkan intensitas interaksi kerja, bukan jabatan semata"):
     *
     *   Zainni    invoice, SPK, harga modal CRM      → Marketing, Hardware, Software
     *   Shandy    inventory, CRM, HRIS               → Hardware, FAT, Marketing, HRD
     *   Maritza   faktur, pembayaran, harga modal    → Marketing, Software
     *   Afif      pembayaran, target proyek, CRM     → FAT, Hardware, Software
     *   Lina      bahan produksi, barang riset       → Hardware
     *   Prastowo  riset, kustomisasi item            → Software, Marketing
     *   Ilham     riset, kustomisasi item            → Software, Marketing
     *   Anisa     serah terima pekerjaan, inventory  → FAT, Software
     *   Tata      desain untuk RnD                   → Hardware
     *
     * Okka dan Syarif tidak dimasukkan karena tidak berada di rantai lintas divisi mana pun.
     * Rasyid keluar karena kuota Hardware penuh — lihat catatan di divisi itu.
     */
    private array $crossAssessors = [
        'FAT & SUPPLY CHAIN' => [
            ['WAHYU NURUL HARYANTO', true],
            ['DEWI PUSPORINI', true],
            ['ZAINNI NOVENA SANTI', false],
            ['MARITZA ISYAURA PUTRI RIZMA', false],
            ['LINA WIDIASTUTI', false],
        ],
        /*
         * Kedua staf RnD masuk atas keputusan manajemen, meski peran keduanya setara.
         *
         * Konsekuensinya Rasyid keluar — kuota Bab 7.4 lima penilai per divisi sudah penuh.
         * Relasi yang dia bawa, target penyelesaian proyek dengan Marketing, sudah diwakili
         * Rhomadoni: Leader Production, penilai A+B, atasan langsungnya. Anisa dipertahankan
         * karena serah terima pekerjaan fungsi yang berbeda, dan justru dialah yang punya dasar
         * paling kuat menilai butir XA-03 soal kelengkapan dan akurasi serah terima.
         */
        'HARDWARE DIVISION' => [
            ['MUHAMMAD SUBARKAH', true],
            ['RHOMADONI', true],
            ['PRASTOWO DIAN KRISTIYANTO', false],
            ['ILHAM YOGA PRATAMA', false],
            ['ANISA FEBRIYANTI', false],
        ],
        'SOFTWARE DIVISION' => [
            ['NOFIYANTO', true],
            ['FADEL MUHAMMAD IRSYAD', true],
            ['SHANDY BAGUS FERDIANSYAH', false],
            ['TATA AZKIA AZZAHRA', false],
        ],
        'MARKETING & SALES' => [
            ['DEWI SETIAWATI', true],
            ['AKHMAD ZAENI MUSTOFA', true],
            ['AFIF FAISHAHUDA', false],
        ],
        /*
         * Satu penilai saja — di bawah anjuran tiga Bab 7.4, dan itu memang keadaannya.
         *
         * Dari lima orang HRD, hanya Avissa yang punya kontak lintas divisi: rekrutmen,
         * penanganan masalah, payroll, HRIS, dan laporan hasil riset. Empat sisanya
         * (supporting staff dan security) bekerja ke dalam.
         *
         * Mengisi kuota dengan mereka justru merusak datanya. Penilai yang tidak punya dasar
         * cenderung menjawab seragam, dan KpiCrossAbuseDetector membuang kuesioner seragam
         * sebagai straight_lining — jadi barisnya tetap tidak terhitung, sementara divisi lain
         * ikut menanggung kesan bahwa HRD sudah menilai. Lebih jujur mengakui satu penilai.
         *
         * Kuorum tidak terganggu: kuorum dihitung pada divisi yang DINILAI, dan HRD sebagai
         * sasaran tetap dinilai belasan penilai dari empat divisi mitra.
         */
        'HRD & CORPORATE SERVICE' => [
            ['AVISSA NOVA FAUZISTIKA', true],
        ],
    ];

    /**
     * Rantai kerja nyata antar orang, sesuai keterangan manajemen. TIDAK menentukan penilaian
     * apa pun — hanya peta alur kerja untuk ditinjau (lihat App\Models\KpiWorkRelation).
     *
     * `from` menyerahkan/mengoordinasikan sesuatu kepada `to`. Arahnya dicatat karena berguna
     * saat menelusuri hambatan, meski di graf digambar sebagai satu garis.
     *
     * Pasangan dihasilkan dari perkalian `from` × `to`. Beberapa pasangan di dalamnya adalah
     * turunan wajar dari keterangan tingkat divisi, bukan disebut satu per satu — misalnya
     * "FAT berkoordinasi dengan Marketing soal faktur" menghasilkan sembilan pasangan. Tandai
     * ulang kalau ada yang sebenarnya tidak pernah terjadi.
     *
     * Manajer tetap muncul di daftar ini kalau dia memang pelaku serah terima — Wahyu di rantai
     * pembayaran klien & faktur, Nofiyanto sebagai lawan bicara SPK software. Yang tidak dicatat
     * hanyalah kehadiran sebagai pihak yang mengetahui.
     *
     * ══ Satu label boleh muncul lebih dari sekali ══
     *
     * Karena pasangan dihasilkan dari perkalian `from` × `to`, satu baris tidak bisa menyatakan
     * "Zainni ke Marketing" tanpa sekaligus membuat "Maritza ke Marketing" kalau keduanya berada
     * di `from` yang sama. Rantai yang punya beberapa sisi berbeda karena itu dipecah jadi
     * beberapa baris berlabel sama — buildWorkChains() memang menerimanya, dan indeks uniknya
     * (from, to, label) tetap aman selama tidak ada pasangan yang terulang.
     *
     * Jangan gabungkan baris berlabel sama "supaya rapi": itu akan memunculkan pasangan yang
     * tidak pernah dikonfirmasi siapa pun.
     *
     * ══ Pengawasan tidak dicatat di sini ══
     *
     * Berkas ini hanya memuat serah terima nyata. Atasan yang mengetahui koordinasi anak buahnya
     * diturunkan dari `employees.manager_id` saat halaman dibuka — lihat
     * App\Support\KpiWorkChainOverseers dan migration 2026_08_12_000021. Jangan menambahkan
     * baris penanda pengawasan ke daftar di bawah: hasilnya menduplikasi struktur organisasi,
     * dan penandaan tangan seperti itu sudah pernah terbukti bolong.
     */
    private array $workChains = [
        // Bahan tidak turun ke lantai produksi lewat Lina — hanya ke Leader dan Admin Production.
        // Rasyid dan kedua welder dicabut 2026-08-12 atas koreksi manajemen.
        ['Bahan produksi', ['LINA WIDIASTUTI'], ['RHOMADONI', 'ANISA FEBRIYANTI']],

        ['Barang riset & proyek', ['LINA WIDIASTUTI'], ['PRASTOWO DIAN KRISTIYANTO', 'ILHAM YOGA PRATAMA']],

        ['Pembayaran klien & faktur', ['WAHYU NURUL HARYANTO', 'DEWI PUSPORINI', 'MARITZA ISYAURA PUTRI RIZMA'], ['DEWI SETIAWATI', 'AKHMAD ZAENI MUSTOFA', 'AFIF FAISHAHUDA']],

        // Dewi Pusporini dari sisi pajak, Maritza dari sisi finance.
        ['Invoice', ['ZAINNI NOVENA SANTI', 'DEWI PUSPORINI', 'MARITZA ISYAURA PUTRI RIZMA'], ['DEWI SETIAWATI', 'AKHMAD ZAENI MUSTOFA', 'AFIF FAISHAHUDA']],

        ['SPK', ['ZAINNI NOVENA SANTI'], ['DEWI SETIAWATI', 'AKHMAD ZAENI MUSTOFA', 'AFIF FAISHAHUDA', 'RHOMADONI', 'ANISA FEBRIYANTI', 'FADEL MUHAMMAD IRSYAD', 'NOFIYANTO']],

        ['Harga modal di CRM', ['ZAINNI NOVENA SANTI', 'MARITZA ISYAURA PUTRI RIZMA'], ['SHANDY BAGUS FERDIANSYAH']],
        ['Harga modal di CRM', ['ZAINNI NOVENA SANTI'], ['DEWI SETIAWATI', 'AKHMAD ZAENI MUSTOFA', 'AFIF FAISHAHUDA']],

        ['Website inventory', ['SHANDY BAGUS FERDIANSYAH'], ['RHOMADONI', 'RASYID PRIYO NUGROHO', 'ANISA FEBRIYANTI', 'LINA WIDIASTUTI', 'MARITZA ISYAURA PUTRI RIZMA', 'DEWI PUSPORINI']],
        // "Lina ke produksi" dibaca Leader + Admin Production, sama seperti koreksi bahan produksi
        // di atas — bukan ke lantai produksi. Tandai ulang kalau Rasyid ternyata termasuk.
        ['Website inventory', ['LINA WIDIASTUTI'], ['RHOMADONI', 'ANISA FEBRIYANTI']],
        ['Website inventory', ['ZAINNI NOVENA SANTI'], ['SHANDY BAGUS FERDIANSYAH']],

        ['CRM penawaran', ['SHANDY BAGUS FERDIANSYAH'], ['DEWI SETIAWATI', 'AKHMAD ZAENI MUSTOFA', 'AFIF FAISHAHUDA', 'ZAINNI NOVENA SANTI']],

        ['HRIS absensi & payroll', ['SHANDY BAGUS FERDIANSYAH'], ['AVISSA NOVA FAUZISTIKA', 'MARITZA ISYAURA PUTRI RIZMA', 'DEWI PUSPORINI']],
        // Pajak payroll — satu-satunya rantai yang berangkat dari HRD, bukan menuju HRD.
        ['HRIS absensi & payroll', ['AVISSA NOVA FAUZISTIKA'], ['DEWI PUSPORINI']],

        ['Riset', ['PRASTOWO DIAN KRISTIYANTO', 'ILHAM YOGA PRATAMA'], ['FADEL MUHAMMAD IRSYAD', 'SHANDY BAGUS FERDIANSYAH', 'TATA AZKIA AZZAHRA', 'AKHMAD SYARIF ABDULLAH', 'NOFIYANTO']],
        ['Riset', ['NOFIYANTO'], ['MUHAMMAD SUBARKAH']],

        ['Laporan hasil riset', ['PRASTOWO DIAN KRISTIYANTO', 'ILHAM YOGA PRATAMA', 'ANISA FEBRIYANTI'], ['AVISSA NOVA FAUZISTIKA', 'WAHYU NURUL HARYANTO']],

        // Rasyid dicabut 2026-08-12: target penyelesaian dibicarakan Marketing dengan Leader
        // Production dan Manajernya, tidak turun ke pelaksana.
        ['Target penyelesaian proyek', ['DEWI SETIAWATI', 'AKHMAD ZAENI MUSTOFA', 'AFIF FAISHAHUDA'], ['RHOMADONI']],

        ['Kustomisasi item', ['DEWI SETIAWATI', 'AKHMAD ZAENI MUSTOFA', 'AFIF FAISHAHUDA'], ['PRASTOWO DIAN KRISTIYANTO', 'ILHAM YOGA PRATAMA']],
        // Rantai "Desain untuk RnD" (Tata → Prastowo, Ilham) dicabut 2026-08-12 atas keputusan
        // manajemen. Tata tetap ada di peta lewat rantai riset.
    ];

    /**
     * Periode uji coba pertama (Bab 11.1) — dibuat berstatus draft supaya admin tetap yang
     * memutuskan kapan bobot dibekukan. Rentang yang dinilai enam bulan terakhir sesuai
     * Bab 7.7, bukan kejadian terbaru saja.
     */
    private array $trialPeriod = [
        'name' => 'Uji Coba Semester I 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-30',
        'cross_fill_start' => '2026-08-11',
        'cross_fill_end' => '2026-08-24',
        'fill_start' => '2026-08-25',
        'fill_end' => '2026-09-05',
    ];

    public function run(): void
    {
        $this->assertAssessorQuota();

        foreach (Company::all() as $company) {
            $this->markDepartments($company->id);
            $this->buildRelationMatrix($company->id);
            $this->fixManagerLines($company->id);
            $this->markEmployeeFlags($company->id);
            $this->buildWorkChains($company->id);

            $period = $this->ensureTrialPeriod($company->id);

            if ($period) {
                $this->seedCrossAssessors($company->id, $period);
            }
        }
    }

    /**
     * KpiCrossMatrixController::storeAssessor menolak penilai ke-6, tetapi seeder ini menulis
     * lewat updateOrCreate sehingga tidak melewati penjagaan itu. Tanpa pemeriksaan di sini,
     * menambah satu nama ke daftar bisa menghasilkan keadaan yang tidak mungkin dibuat lewat
     * antarmuka — dan admin baru tahu saat mencoba mengubahnya dan malah ditolak.
     */
    private function assertAssessorQuota(): void
    {
        foreach ($this->crossAssessors as $departmentName => $rows) {
            if (count($rows) > KpiCrossAssessor::MAX_PER_DIVISION) {
                throw new RuntimeException(sprintf(
                    'Penilai silang %s ada %d orang, melewati batas %d per divisi (Bab 7.4).',
                    $departmentName,
                    count($rows),
                    KpiCrossAssessor::MAX_PER_DIVISION
                ));
            }
        }
    }

    private function markDepartments(int $companyId): void
    {
        foreach ($this->divisions as $name => $meta) {
            Department::where('company_id', $companyId)
                ->where('name', $name)
                ->update([
                    'is_division' => true,
                    'is_shared_service' => $meta['shared_service'],
                    'kpi_code' => $meta['code'],
                ]);
        }

        Department::where('company_id', $companyId)
            ->whereIn('name', $this->nonDivisions)
            ->update(['is_division' => false, 'is_shared_service' => false]);
    }

    /**
     * Lima divisi saling menilai seluruhnya: 4 mitra per divisi, masih di antara batas Bab 7.3
     * (minimal 3, maksimal 6). Dengan hanya lima divisi, memangkasnya lagi akan menjatuhkan
     * sebagian divisi di bawah kuorum tiga penilai dari dua divisi berbeda (Bab 7.7).
     *
     * Relasi ditulis dua arah, sama seperti KpiCrossMatrixController::update — divisi layanan
     * umum (FAT, HRD) dinilai semua divisi tetapi juga berhak menilai balik.
     */
    private function buildRelationMatrix(int $companyId): void
    {
        $divisionIds = Department::where('company_id', $companyId)
            ->whereIn('name', array_keys($this->divisions))
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($companyId, $divisionIds) {
            foreach ($divisionIds as $from) {
                foreach ($divisionIds as $to) {
                    if ($from === $to) {
                        continue; // Bab 7.3 — tidak boleh menilai divisi sendiri.
                    }

                    KpiDivisionRelation::updateOrCreate(
                        ['department_id' => $from, 'partner_department_id' => $to],
                        ['company_id' => $companyId, 'is_active' => true]
                    );
                }
            }
        });
    }

    private function fixManagerLines(int $companyId): void
    {
        foreach ($this->managerLines as $subordinate => $manager) {
            $employee = $this->employee($companyId, $subordinate);
            $newManager = $this->employee($companyId, $manager);

            if (! $employee || ! $newManager || $employee->id === $newManager->id) {
                continue;
            }

            // `approver_id` sengaja tidak disentuh. Kolom itu milik alur persetujuan dokumen
            // (App\Models\ApprovalRule) dan boleh berbeda dari garis komando; mengubahnya di
            // sini akan mengalihkan persetujuan izin dan lembur tanpa diminta.
            $employee->update(['manager_id' => $newManager->id]);
        }
    }

    private function markEmployeeFlags(int $companyId): void
    {
        foreach ($this->kpiLevelOverrides as $fullName => $levelCode) {
            $level = KpiLevel::where('company_id', $companyId)->where('code', $levelCode)->first();
            $employee = $this->employee($companyId, $fullName);

            // Level yang sudah diisi admin tidak ditimpa — seeder hanya menambal yang kosong.
            if (! $level || ! $employee || $employee->kpi_level_id) {
                continue;
            }

            $employee->update(['kpi_level_id' => $level->id]);
        }

        Employee::where('company_id', $companyId)
            ->whereIn('full_name', $this->crossFunctional)
            ->update(['is_cross_functional' => true]);

        Employee::where('company_id', $companyId)
            ->whereNotIn('full_name', $this->crossFunctional)
            ->where('is_cross_functional', true)
            ->update(['is_cross_functional' => false]);

        Employee::where('company_id', $companyId)
            ->whereIn('full_name', $this->excluded)
            ->update(['is_kpi_excluded' => true]);
    }

    /**
     * Peta rantai kerja OTORITATIF: pasangan yang tidak lagi tercantum di berkas ini DIHAPUS.
     *
     * Riwayatnya tetap ada — di git, pada berkas ini. Itu sumber yang lebih jujur daripada baris
     * nonaktif di basis data: `$workChains` menyimpan alasan perubahan dalam komentarnya, sementara
     * baris nonaktif hanya menyimpan fakta bahwa dulu pernah ada. Lihat migration
     * 2026_08_12_000018.
     *
     * Kekuasaannya berhenti pada baris `source = seeder`. Rantai yang dibuat admin lewat halaman
     * Rantai Kerja tidak disentuh — kalau tidak, setiap `db:seed` akan menghapus pekerjaan admin
     * tanpa pesan apa pun. Lihat migration 2026_08_12_000017.
     */
    private function buildWorkChains(int $companyId): void
    {
        $keep = [];

        foreach ($this->workChains as [$label, $fromNames, $toNames]) {
            foreach ($fromNames as $fromName) {
                $from = $this->employee($companyId, $fromName);

                if (! $from) {
                    continue;
                }

                foreach ($toNames as $toName) {
                    $to = $this->employee($companyId, $toName);

                    if (! $to || $to->id === $from->id) {
                        continue;
                    }

                    $relation = KpiWorkRelation::updateOrCreate(
                        [
                            'from_employee_id' => $from->id,
                            'to_employee_id' => $to->id,
                            'label' => $label,
                        ],
                        [
                            'company_id' => $companyId,
                            'source' => KpiWorkRelation::SOURCE_SEEDER,
                        ]
                    );

                    $keep[] = $relation->id;
                }
            }
        }

        KpiWorkRelation::where('company_id', $companyId)
            ->where('source', KpiWorkRelation::SOURCE_SEEDER)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * Hanya dibuat kalau perusahaan belum punya periode sama sekali. Setelah admin mulai
     * membuat periode sendiri, seeder tidak boleh ikut menambah baris — daftar periode adalah
     * catatan resmi siklus penilaian, bukan tempat data contoh.
     */
    private function ensureTrialPeriod(int $companyId): ?KpiPeriod
    {
        $existing = KpiPeriod::where('company_id', $companyId)->orderBy('start_date')->first();

        if ($existing) {
            return $existing;
        }

        $creator = Employee::where('company_id', $companyId)
            ->where('role', 'superadmin')
            ->orderBy('id')
            ->first()
            ?? Employee::where('company_id', $companyId)->orderBy('id')->first();

        return KpiPeriod::create($this->trialPeriod + [
            'company_id' => $companyId,
            'status' => KpiPeriod::STATUS_DRAFT,
            'is_trial' => true,
            'created_by' => $creator?->id,
        ]);
    }

    /**
     * Daftar penilai OTORITATIF: penilai yang tidak tercantum dicabut dari periode ini. Kalau
     * hanya menambah, penilai yang sudah tidak dianggap tepat akan tetap melihat formulir dan
     * ikut terhitung — dan daftar di berkas ini berhenti mencerminkan keadaan sebenarnya.
     *
     * Pencabutan dilewati untuk penilai yang sudah pernah mengirim kuesioner. Barisnya di
     * kpi_cross_layer_a / layer_b menunjuk ke `employees`, bukan ke baris penilai, jadi
     * menghapusnya tidak ikut menghapus jawaban — yang tersisa justru jawaban dari orang yang
     * tidak lagi tercatat berwenang, dan itu lebih sulit dijelaskan saat audit daripada
     * membiarkan penilainya.
     */
    private function seedCrossAssessors(int $companyId, KpiPeriod $period): void
    {
        if ($period->isFinal()) {
            return;
        }

        $keep = [];

        foreach ($this->crossAssessors as $departmentName => $rows) {
            $department = Department::where('company_id', $companyId)
                ->where('name', $departmentName)
                ->first();

            if (! $department) {
                continue;
            }

            foreach ($rows as [$name, $canAssessIndividual]) {
                $employee = $this->employee($companyId, $name);

                if (! $employee) {
                    continue;
                }

                KpiCrossAssessor::updateOrCreate(
                    ['kpi_period_id' => $period->id, 'employee_id' => $employee->id],
                    [
                        'department_id' => $department->id,
                        'can_assess_individual' => $canAssessIndividual,
                    ]
                );

                $keep[] = $employee->id;
            }
        }

        $stale = KpiCrossAssessor::where('kpi_period_id', $period->id)
            ->whereNotIn('employee_id', $keep)
            ->get();

        foreach ($stale as $assessor) {
            $hasSubmitted = KpiCrossLayerA::where('kpi_period_id', $period->id)
                ->where('assessor_id', $assessor->employee_id)
                ->exists()
                || KpiCrossLayerB::where('kpi_period_id', $period->id)
                    ->where('assessor_id', $assessor->employee_id)
                    ->exists();

            if ($hasSubmitted) {
                continue;
            }

            $assessor->delete();
        }
    }

    private function employee(int $companyId, string $fullName): ?Employee
    {
        return Employee::where('company_id', $companyId)
            ->where('full_name', $fullName)
            ->first();
    }
}
