<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Timeline informasi kantor untuk dashboard karyawan: siapa yang izin/cuti/sakit pada
 * tanggal tertentu, dan siapa yang ulang tahun.
 *
 * Cakupan sengaja SATU PERUSAHAAN (bukan satu departemen): tujuan panel ini justru agar
 * karyawan tahu rekan lintas divisi yang sedang tidak bisa dihubungi.
 *
 * Batas privasi yang WAJIB dipertahankan:
 * - Kolom `leave_requests.reason` tidak pernah ikut di-select. Isinya bisa berupa alasan
 *   medis atau keluarga yang tidak layak diedarkan ke seluruh kantor. Query di bawah
 *   menyebut kolomnya satu per satu, bukan `select *`, supaya batas ini terlihat di kode
 *   dan tidak hilang tanpa sengaja.
 * - Jam clock-in/clock-out dan status terlambat TIDAK ditampilkan. Data itu hanya untuk
 *   manager (lihat TodayTeamStatus). Panel ini menjawab "siapa yang tidak di kantor",
 *   bukan "siapa yang sering telat".
 * - Tahun lahir tidak pernah dipakai. Tanggal ulang tahun yang ditampilkan adalah tanggal
 *   perayaan tahun ini, sehingga umur tidak bocor.
 *
 * Yang sengaja dikeluarkan dari daftar izin:
 * - Work From Home — orangnya tetap bekerja, kalau disatukan dengan sakit/cuti pembaca
 *   salah menyimpulkan dia tidak bisa dihubungi.
 * - Izin parsial (datang terlambat / pulang cepat) — terlalu rinci untuk panel yang
 *   dilihat seluruh kantor.
 */
class DashboardTimeline
{
    /**
     * Seberapa jauh ke depan ulang tahun diintip. Tidak ada batas ke belakang untuk izin:
     * seluruh riwayat ikut, dan yang membatasi tampilan adalah paginasi di kartunya.
     */
    public const BIRTHDAY_LOOKAHEAD_DAYS = 7;

    /**
     * @return array{company: array{name: string, logo: ?string}, entries: array<int, array<string, mixed>>}
     */
    public static function for(Employee $employee, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        $entries = array_merge(
            self::leaveEntries($employee, $today),
            self::birthdayEntries($employee, $today),
            self::announcementEntries($employee, $today),
        );

        // Terbaru di atas. Ulang tahun yang akan datang otomatis naik ke puncak — itu
        // memang yang paling berguna, ucapan baru ada gunanya sebelum harinya lewat.
        // Pada tanggal yang sama ulang tahun didahulukan: yang perlu ditindaklanjuti hari
        // itu adalah mengucapkan, bukan membaca daftar izin.
        // Pengumuman yang dipaku selalu di puncak, apa pun tanggalnya — kalau tidak, kabar
        // penting bisa tenggelam di bawah kartu ulang tahun yang tanggalnya lebih baru.
        usort($entries, function (array $a, array $b) {
            $pakuA = ! empty($a['pinned']);
            $pakuB = ! empty($b['pinned']);

            if ($pakuA !== $pakuB) {
                return $pakuA ? -1 : 1;
            }

            return strcmp($b['date'], $a['date'])
                ?: ($a['type'] === 'birthday' ? -1 : ($b['type'] === 'birthday' ? 1 : 0));
        });

        return [
            'company' => self::companyIdentity($employee),
            'entries' => $entries,
        ];
    }

    /**
     * Satu entri per TANGGAL, berisi semua orang yang izin pada tanggal itu — bukan satu
     * entri per pengajuan. Cuti 3 hari milik satu orang muncul di 3 tanggal, sama seperti
     * yang dilihat orang di kantor.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function leaveEntries(Employee $employee, Carbon $today): array
    {
        if (! Schema::hasTable('leave_requests')) {
            return [];
        }

        $leaves = LeaveRequest::query()
            // Kolom disebut eksplisit supaya `reason` tidak pernah terbawa. Jangan ubah
            // menjadi select penuh.
            ->select('id', 'employee_id', 'leave_type_id', 'start_date', 'end_date')
            ->where('status', 'approved')
            // Tanpa batas ke belakang: seluruh riwayat izin ikut. Hari yang belum tiba
            // dipotong karena izin yang belum dijalani bukan kabar, masih rencana.
            ->where('start_date', '<=', $today->toDateString())
            ->whereHas('employee', fn ($query) => $query
                ->where('company_id', $employee->company_id)
                ->where('is_active', true))
            ->with([
                'employee:id,full_name,department_id',
                'employee.department:id,name',
                'leaveType:id,name',
            ])
            ->get()
            ->reject(fn (LeaveRequest $leave) => AttendanceLateExcuse::isWfhLeave($leave)
                || AttendanceLateExcuse::isPartialDayLeave($leave));

        /** @var array<string, array<int, array<string, mixed>>> $perDate */
        $perDate = [];

        foreach ($leaves as $leave) {
            $from = Carbon::parse($leave->start_date)->startOfDay();
            $to = Carbon::parse($leave->end_date)->startOfDay()->min($today);

            for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay()) {
                $perDate[$date->toDateString()][] = [
                    'name' => (string) $leave->employee?->full_name,
                    'department' => $leave->employee?->department?->name,
                    'detail' => $leave->leaveType?->name ?: 'Izin',
                ];
            }
        }

        $entries = [];

        foreach ($perDate as $dateString => $people) {
            usort($people, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

            $entries[] = self::entry('leave', Carbon::parse($dateString), $today, $people);
        }

        return $entries;
    }

    /**
     * Setiap karyawan yang punya `birth_date` muncul TEPAT SATU KALI: perayaan terakhirnya
     * yang tidak melewati batas intip ke depan.
     *
     * Cara ini dipilih daripada "semua perayaan sepanjang riwayat" karena ulang tahun
     * berulang tiap tahun — tanpa batas, satu orang akan muncul sebanyak umurnya. Dan
     * dipilih daripada "hanya sepekan ke depan" karena kartunya kini berpaginasi, jadi
     * perayaan yang sudah lewat tidak lagi menghabiskan ruang di halaman pertama.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function birthdayEntries(Employee $employee, Carbon $today): array
    {
        // Batas atas, bukan titik potong: perayaan sesudah tanggal ini dianggap belum
        // relevan, jadi yang dipakai adalah perayaan tahun sebelumnya.
        $horizon = $today->copy()->addDays(self::BIRTHDAY_LOOKAHEAD_DAYS);

        $employees = Employee::query()
            ->select('id', 'full_name', 'department_id', 'birth_date')
            ->where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->with('department:id,name')
            ->get();

        /** @var array<string, array<int, array<string, mixed>>> $perDate */
        $perDate = [];

        // Perhitungan dikerjakan di PHP, bukan lewat DATE_FORMAT di SQL: jumlah karyawan
        // per perusahaan ratusan, dan query-nya tetap portabel.
        foreach ($employees as $person) {
            $birth = $person->birth_date;

            if ($birth === null) {
                continue;
            }

            $occurrence = self::latestBirthdayOccurrence($birth, $horizon);

            $perDate[$occurrence->toDateString()][] = [
                'name' => (string) $person->full_name,
                'department' => $person->department?->name,
                'detail' => null,
            ];
        }

        $entries = [];

        foreach ($perDate as $dateString => $people) {
            usort($people, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

            $entries[] = self::entry('birthday', Carbon::parse($dateString), $today, $people);
        }

        return $entries;
    }

    /**
     * Pengumuman HR yang sedang tayang untuk perusahaan karyawan ini.
     *
     * Berbeda dari cuti dan ulang tahun, entri ini tidak diturunkan dari data lain — ia
     * ditulis manusia, jadi ia satu-satunya jenis yang punya `title`, `body`, dan `pinned`.
     *
     * Yang belum terbit dan yang sudah kedaluwarsa disaring di query (scope `tayang`),
     * bukan di PHP: jumlah pengumuman lama hanya akan bertambah dari waktu ke waktu.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function announcementEntries(Employee $employee, Carbon $today): array
    {
        if (! Schema::hasTable('announcements')) {
            return [];
        }

        return Announcement::query()
            // `$today` diteruskan, bukan dibiarkan memakai waktu nyata. Tanpa ini pengumuman
            // terjadwal tidak pernah bisa diuji, dan blok ini jadi satu-satunya bagian
            // Timeline yang mengabaikan tanggal yang diminta pemanggilnya.
            ->tayang($today->copy()->endOfDay())
            ->where('company_id', $employee->company_id)
            // department_id NULL = seluruh perusahaan. Baris bertarget departemen hanya
            // tampil bagi anggota departemen itu.
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $employee->department_id))
            ->with('penulis:id,full_name')
            ->orderByDesc('published_at')
            ->get()
            ->map(function (Announcement $pengumuman) use ($today) {
                $tanggal = $pengumuman->published_at->copy()->startOfDay();

                return array_merge(
                    self::entry('announcement', $tanggal, $today, []),
                    [
                        'title' => $pengumuman->title,
                        'body' => $pengumuman->body,
                        'pinned' => $pengumuman->is_pinned,
                        'author' => $pengumuman->penulis?->full_name,
                        // Foto dan berkas disajikan lewat rute berizin, bukan URL storage
                        // langsung — berkasnya memang disimpan di disk privat.
                        'image_url' => $pengumuman->image_path
                            ? route('employee.announcements.image', $pengumuman->id)
                            : null,
                        'file_url' => $pengumuman->file_path
                            ? route('employee.announcements.file', $pengumuman->id)
                            : null,
                        'file_name' => $pengumuman->file_name,
                        'file_size' => $pengumuman->ukuranBerkas(),
                        'link_url' => $pengumuman->link_url,
                    ]
                );
            })
            ->all();
    }

    /**
     * Perayaan terakhir dari sebuah tanggal lahir yang tidak melewati `$horizon`.
     *
     * 29 Februari ditangani khusus: memasang tahun non-kabisat pada tanggal itu membuat
     * Carbon melimpah ke 1 Maret, sehingga orang yang lahir 29 Februari akan diucapkan
     * pada tanggal yang salah tiga dari empat tahun.
     */
    private static function latestBirthdayOccurrence(Carbon $birth, Carbon $horizon): Carbon
    {
        $build = function (int $year) use ($birth): Carbon {
            $month = (int) $birth->month;
            $day = (int) $birth->day;

            if ($month === 2 && $day === 29 && ! Carbon::create($year, 1, 1)->isLeapYear()) {
                $day = 28;
            }

            return Carbon::create($year, $month, $day)->startOfDay();
        };

        $occurrence = $build((int) $horizon->year);

        return $occurrence->greaterThan($horizon)
            ? $build((int) $horizon->year - 1)
            : $occurrence;
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     * @return array<string, mixed>
     */
    private static function entry(string $type, Carbon $date, Carbon $today, array $people): array
    {
        return [
            'type' => $type,
            'date' => $date->toDateString(),
            'date_short' => $date->format('d/m/Y'),
            'date_label' => $date->locale('id')->translatedFormat('j F Y'),
            'relative_label' => self::relativeLabel($date, $today),
            'people' => $people,
        ];
    }

    /**
     * Patokannya TANGGAL KEJADIAN, bukan tanggal pengajuan — kartu "24/08/2026" tidak
     * boleh berbunyi "4 hari yang lalu" saat hari ini tanggal 24.
     */
    private static function relativeLabel(Carbon $date, Carbon $today): string
    {
        $diff = (int) $today->diffInDays($date, false);

        return match (true) {
            $diff === 0 => 'Hari ini',
            $diff === 1 => 'Besok',
            $diff === -1 => 'Kemarin',
            $diff > 1 && $diff <= 30 => $diff.' hari lagi',
            $diff < -1 && $diff >= -30 => abs($diff).' hari yang lalu',
            // Riwayat izin kini tak berbatas, dan "418 hari yang lalu" tidak terbaca oleh
            // siapa pun. Lewat sebulan, serahkan ke satuan yang lebih besar.
            //
            // DIFF_RELATIVE_TO_NOW wajib: tanpa itu Carbon memakai bentuk perbandingan
            // dan hasilnya "2 bulan sebelumnya", bukan "2 bulan yang lalu".
            default => $date->locale('id')->diffForHumans($today, [
                'parts' => 1,
                'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
            ]),
        };
    }

    /**
     * @return array{name: string, logo: ?string}
     */
    private static function companyIdentity(Employee $employee): array
    {
        $company = $employee->company ?? Company::first();

        return [
            'name' => (string) ($company?->name ?? config('app.name')),
            'logo' => $company?->logo ? asset('storage/'.$company->logo) : null,
        ];
    }
}
