<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Employee;

class KaryawanPtArtaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('════════════════════════════════════════════');
        $this->command->info('  SEEDER KARYAWAN PT. ARTA TEKNOLOGI');
        $this->command->info('════════════════════════════════════════════');

        // ── 1. HAPUS DATA LAMA ──
        $this->command->warn('Menghapus data karyawan lama...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        foreach ([
            'leave_balances','attendances','leave_requests','overtime_requests',
            'attendance_requests','data_change_requests','approval_logs','notifications',
        ] as $t) { DB::table($t)->truncate(); }
        foreach ([
            'employee_approvers','employee_payrolls','employee_payroll_components',
            'request_attachments','payroll_run_details','employee_roles',
        ] as $t) { if (Schema::hasTable($t)) DB::table($t)->truncate(); }
        DB::table('employees')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $this->command->info('✓ Data lama dihapus.');

        // ── 2. COMPANY ──
        DB::table('companies')->updateOrInsert(['id' => 1], [
            'name'    => 'PT Arta Teknologi Comunindo',
            'address' => 'RT.007/RW.002, Kadirojo I, Purwomartani, Kec. Kalasan, Kabupaten Sleman, Daerah Istimewa Yogyakarta 55571',
            'phone'   => '0811-2632-151',
        ]);
        $this->command->info('✓ Company seeded.');

        $leaveTypes = [
            'Cuti Tahunan' => 12,
            'Cuti Sakit' => 14,
            'Izin Datang Terlambat' => 365,
            'Cuti Melahirkan' => 90,
        ];

        foreach ($leaveTypes as $name => $maxDays) {
            LeaveType::updateOrCreate(
                ['name' => $name],
                ['max_days' => $maxDays]
            );
        }

        $cutiTahunan = LeaveType::where('name', 'Cuti Tahunan')->first();
        if ($cutiTahunan) {
            LeavePolicy::updateOrCreate(
                [
                    'company_id' => 1,
                    'leave_type_id' => $cutiTahunan->id,
                ],
                [
                    'days_per_year' => 12,
                    'min_tenure_months' => 12,
                    'max_carry_over' => 0,
                    'is_prorated' => true,
                    'is_active' => true,
                ]
            );
        }
        $this->command->info('✓ Leave types & annual leave policy seeded.');

        // ── 3. DEPARTMENTS ──
        $now = now()->toDateTimeString();

        DB::table('shifts')->updateOrInsert(['company_id' => 1, 'name' => 'Pagi'], [
            'company_id' => 1,
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_hours' => 8,
            'auto_overtime' => false,
            'color' => '#3B82F6',
            'is_off' => false,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('shifts')->updateOrInsert(['company_id' => 1, 'name' => 'Off'], [
            'company_id' => 1,
            'name' => 'Off',
            'start_time' => null,
            'end_time' => null,
            'work_hours' => null,
            'auto_overtime' => false,
            'color' => '#6B7280',
            'is_off' => true,
            'sort_order' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $pagiShiftId = DB::table('shifts')->where('company_id', 1)->where('name', 'Pagi')->value('id');
        $offShiftId = DB::table('shifts')->where('company_id', 1)->where('name', 'Off')->value('id');

        DB::table('schedule_templates')->updateOrInsert(['id' => 1], [
            'company_id' => 1,
            'name' => '5 Hari Kerja (Pagi)',
            'description' => 'Senin-Jumat shift pagi, Sabtu-Minggu off',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('schedule_templates')->updateOrInsert(['id' => 2], [
            'company_id' => 1,
            'name' => '6 Hari Kerja (Pagi)',
            'description' => 'Senin-Sabtu shift pagi, Minggu off',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([1 => [$pagiShiftId, $pagiShiftId, $pagiShiftId, $pagiShiftId, $pagiShiftId, $offShiftId, $offShiftId], 2 => [$pagiShiftId, $pagiShiftId, $pagiShiftId, $pagiShiftId, $pagiShiftId, $pagiShiftId, $offShiftId]] as $templateId => $shiftIds) {
            foreach ($shiftIds as $index => $shiftId) {
                DB::table('schedule_template_days')->updateOrInsert(
                    ['template_id' => $templateId, 'day_of_week' => $index + 1],
                    ['shift_id' => $shiftId, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
        $this->command->info('Shift & template jadwal seeded.');

        $this->command->info('Seeding departments...');
        $deptRows = [
            ['id'=>5, 'name'=>'HRD & CORPORATE SERVICE', 'parent_id'=>null],
            ['id'=>18, 'name'=>'BOARD OF DIRECTORS', 'parent_id'=>null],
            ['id'=>19, 'name'=>'FAT & SUPPLY CHAIN', 'parent_id'=>null],
            ['id'=>20, 'name'=>'SOFTWARE DIVISION', 'parent_id'=>null],
            ['id'=>21, 'name'=>'HARDWARE DIVISION', 'parent_id'=>null],
            ['id'=>23, 'name'=>'MARKETING', 'parent_id'=>null],
        ];
        foreach ($deptRows as $row) {
            DB::table('departments')->updateOrInsert(['id'=>$row['id']], [
                'name'=>$row['name'], 'company_id'=>1, 'parent_id'=>null,
            ]);
        }
        $childRows = [
            ['id'=>22, 'name'=>'PRODUCTION', 'parent_id'=>21],
            ['id'=>24, 'name'=>'HSE', 'parent_id'=>5],
            ['id'=>25, 'name'=>'RESEARCH & DEVELOPMENT', 'parent_id'=>21],
            ['id'=>26, 'name'=>'PURCHASING', 'parent_id'=>19],
            ['id'=>27, 'name'=>'SECURITY', 'parent_id'=>5],
            ['id'=>28, 'name'=>'PUBLICATION', 'parent_id'=>23],
            ['id'=>29, 'name'=>'OPERATION & PROJECT', 'parent_id'=>19],
            ['id'=>30, 'name'=>'FINANCE, ACCOUNTING AND TAX', 'parent_id'=>19],
            ['id'=>32, 'name'=>'SUPPORTING STAFF', 'parent_id'=>5],
        ];
        foreach ($childRows as $row) {
            DB::table('departments')->updateOrInsert(['id'=>$row['id']], [
                'name'=>$row['name'], 'company_id'=>1, 'parent_id'=>$row['parent_id'],
            ]);
        }
        $this->command->info('✓ Departments seeded.');

        // ── 4. KARYAWAN ──
        $this->command->info('Seeding karyawan...');
        $pwd = Hash::make('password');
        $employees = [
            // Raden Tarjadi — Commissioner
            ['orig_id'=>1,'employee_code'=>'001/COM/IV/2022','company_id'=>1,'department_id'=>18,
             'full_name'=>'Raden Tarjadi','email'=>'taryadisoetarmo@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3404072604670001','npwp_15'=>'06.599.123.4-542.000','npwp_16'=>'3404072604670001',
             'ptkp'=>'K/2','bpjs_tk'=>'24115350845','bpjs_kesehatan'=>'0001660597053',
             'bank_account'=>'0460044563','bank_name'=>'BCA Syariah',
             'ktp_address'=>'JL. Rambutan No.26 Sambilegi Kidul RT 003 RW 056 Maguwoharjo, Depok, Sleman, DI Yogyakarta',
             'residential_address'=>'JL. Rambutan No.26 Sambilegi Kidul RT 003 RW 056 Maguwoharjo, Depok, Sleman, DI Yogyakarta',
             'position'=>'Commissioner','job_level'=>1,'employment_status'=>'permanent',
             'join_date'=>'2022-03-01','resign_date'=>null,'is_active'=>1,'role'=>'admin',
             'schedule_template_id'=>null,'orig_manager_id'=>null,'orig_approver_id'=>null],
            // Sofyan Ariyanto — Director
            ['orig_id'=>2,'employee_code'=>'001/DIR/I/2013','company_id'=>1,'department_id'=>18,
             'full_name'=>'Sofyan Ariyanto','email'=>'ariyanto.sofyan@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3319032501830003','npwp_15'=>'70.496.505.2-506.000','npwp_16'=>'3319032501830003',
             'ptkp'=>'K/1','bpjs_tk'=>'15023011073','bpjs_kesehatan'=>'0001527584589',
             'bank_account'=>'0373484111','bank_name'=>'BCA',
             'ktp_address'=>'Getas Pejaten RT 008  RW 002 Kelurahan Getas Pejaten, Kecamatan Jati, Kabupaten Kudus',
             'residential_address'=>'Getas Pejaten RT 008  RW 002 Kelurahan Getas Pejaten, Kecamatan Jati, Kabupaten Kudus',
             'position'=>'Director','job_level'=>1,'employment_status'=>'permanent',
             'join_date'=>'2022-03-01','resign_date'=>null,'is_active'=>1,'role'=>'superadmin',
             'schedule_template_id'=>null,'orig_manager_id'=>null,'orig_approver_id'=>null],
            // Wahyu Nurul Haryanto — Admin Manager
            ['orig_id'=>3,'employee_code'=>'001/FATSC/III/2013','company_id'=>1,'department_id'=>19,
             'full_name'=>'Wahyu Nurul Haryanto','email'=>'wh1098@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3310082505860005','npwp_15'=>'94.754.522.4-525.000','npwp_16'=>'3310082505860005',
             'ptkp'=>'K/3','bpjs_tk'=>'15023011024','bpjs_kesehatan'=>'0001527584545',
             'bank_account'=>'0306408750','bank_name'=>'BCA',
             'ktp_address'=>'Gatak RT 001 RW 001 Kelurahan Wonoboyo, Kecamatan Jogonalan, Kabupaten Klaten',
             'residential_address'=>'Gatak RT 001 RW 001 Kelurahan Wonoboyo, Kecamatan Jogonalan, Kabupaten Klaten',
             'position'=>'Admin Manager','job_level'=>2,'employment_status'=>'permanent',
             'join_date'=>'2022-03-01','resign_date'=>null,'is_active'=>1,'role'=>'manager',
             'schedule_template_id'=>1,'orig_manager_id'=>2,'orig_approver_id'=>2],
            // Nofiyanto — Software Manager
            ['orig_id'=>4,'employee_code'=>'001/SOFTW/I/2015','company_id'=>1,'department_id'=>20,
             'full_name'=>'Nofiyanto','email'=>'nofiyanto11@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3471101111880001','npwp_15'=>'58.538.564.4-545.000','npwp_16'=>'3471101111880001',
             'ptkp'=>'K/2','bpjs_tk'=>'15023011032','bpjs_kesehatan'=>'0001527584591',
             'bank_account'=>'4451343160','bank_name'=>'BCA',
             'ktp_address'=>'Bojongwetan RT 002 RW 013 Kelurahan Bojong, Kecamatan Mungkid, Kabupaten Magelang',
             'residential_address'=>'Bojongwetan RT 002 RW 013 Kelurahan Bojong, Kecamatan Mungkid, Kabupaten Magelang',
             'position'=>'Software Manager','job_level'=>2,'employment_status'=>'permanent',
             'join_date'=>'2022-03-01','resign_date'=>null,'is_active'=>1,'role'=>'manager',
             'schedule_template_id'=>1,'orig_manager_id'=>2,'orig_approver_id'=>2],
            // Muhammad Subarkah — Hardware Manager
            ['orig_id'=>5,'employee_code'=>'001/HARD/V/2016','company_id'=>1,'department_id'=>21,
             'full_name'=>'Muhammad Subarkah','email'=>'muhammadsubarkah3@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3403080312920002','npwp_15'=>'91.924.182.8-545.000','npwp_16'=>'3403080312920002',
             'ptkp'=>'K/1','bpjs_tk'=>'18000684920','bpjs_kesehatan'=>'0000095306624',
             'bank_account'=>'4451485584','bank_name'=>'BCA',
             'ktp_address'=>'Panggul Kulon RT 003   RW 006 Kelurahan Candirejo Kecamatan Semanu Kabupaten Gunungkidul',
             'residential_address'=>'Panggul Kulon RT 003   RW 006 Kelurahan Candirejo Kecamatan Semanu Kabupaten Gunungkidul',
             'position'=>'Hardware Manager','job_level'=>2,'employment_status'=>'permanent',
             'join_date'=>'2022-03-01','resign_date'=>null,'is_active'=>1,'role'=>'manager',
             'schedule_template_id'=>1,'orig_manager_id'=>2,'orig_approver_id'=>2],
            // Akhmad Zaeni Mustofa — Leader Marketing
            ['orig_id'=>6,'employee_code'=>'003/MAR/I/2023','company_id'=>1,'department_id'=>23,
             'full_name'=>'Akhmad Zaeni Mustofa','email'=>'zeniakhmadmustofa@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3323150402950001','npwp_15'=>'85.591.371.1-533.000','npwp_16'=>'3323150402950001',
             'ptkp'=>'K/1','bpjs_tk'=>'23149577555','bpjs_kesehatan'=>'0002420436789',
             'bank_account'=>'8020415089','bank_name'=>'BCA',
             'ktp_address'=>'Bebengan RT 003  005 Kelurahan Kertosari, Kecamatan Temanggung, Kabupaten Temanggung',
             'residential_address'=>'Bebengan RT 003  005 Kelurahan Kertosari, Kecamatan Temanggung, Kabupaten Temanggung',
             'position'=>'Leader Marketing','job_level'=>3,'employment_status'=>'contract',
             'join_date'=>'2023-01-03','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>18,'orig_approver_id'=>18],
            // Rhomadoni — Production
            ['orig_id'=>7,'employee_code'=>'003/HARD/XI/2020','company_id'=>1,'department_id'=>22,
             'full_name'=>'Rhomadoni','email'=>'donirhoma46@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'1606011312970005','npwp_15'=>'39.943.184.0-314.000','npwp_16'=>'1606011312970005',
             'ptkp'=>'K/0','bpjs_tk'=>'22008616603','bpjs_kesehatan'=>'0002570347247',
             'bank_account'=>'8610647431','bank_name'=>'BCA',
             'ktp_address'=>'Jl. Kol Wahid Udin LK. II RT 003 RW 002 Kelurahan Balai Agung, Kecamatan Sekayu, Kabupaten Musi Banyuasin',
             'residential_address'=>'Jl. Kol Wahid Udin LK. II RT 003 RW 002 Kelurahan Balai Agung, Kecamatan Sekayu, Kabupaten Musi Banyuasin',
             'position'=>'Production','job_level'=>3,'employment_status'=>'permanent',
             'join_date'=>'2022-03-01','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>5,'orig_approver_id'=>5],
            // Fadel Muhammad Irsyad — Software Division
            ['orig_id'=>8,'employee_code'=>'002/SOFTW/XI/2022','company_id'=>1,'department_id'=>20,
             'full_name'=>'Fadel Muhammad Irsyad','email'=>'fadelirsyad04@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3577011201010001','npwp_15'=>'50.003.480.6-621.000','npwp_16'=>'3577011201010001',
             'ptkp'=>'TK/0','bpjs_tk'=>'23149577563','bpjs_kesehatan'=>'0000787291119',
             'bank_account'=>'7315126282','bank_name'=>'BCA',
             'ktp_address'=>'Jl. Tawang Sari 83 RT 015 RW 005 Kelurahan Tawangrejo Kecamatan Kartoharjo Kota Madiun',
             'residential_address'=>'Jl. Tawang Sari 83 RT 015 RW 005 Kelurahan Tawangrejo Kecamatan Kartoharjo Kota Madiun',
             'position'=>'Software Division','job_level'=>3,'employment_status'=>'permanent',
             'join_date'=>'2022-11-17','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>4,'orig_approver_id'=>4],
            // Aaqilah Arum Sekarwati — Purchasing Division
            ['orig_id'=>9,'employee_code'=>'002/FATSC/X/2024','company_id'=>1,'department_id'=>26,
             'full_name'=>'Aaqilah Arum Sekarwati','email'=>'aaqilaharum1@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3404106104020001','npwp_15'=>'99.092.445.8-542.000','npwp_16'=>'3404106104020001',
             'ptkp'=>'TK/0','bpjs_tk'=>'24006244479','bpjs_kesehatan'=>'0002256903764',
             'bank_account'=>'6975555929','bank_name'=>'BCA',
             'ktp_address'=>'Perum Grasia Iia-2 Sambisari RT 008  RW 002 Kelurahan Purwomartani Kecamatan Kalasan Daerah Istimewa Yogyakarta',
             'residential_address'=>'Perum Grasia Iia-2 Sambisari RT 008  RW 002 Kelurahan Purwomartani Kecamatan Kalasan Daerah Istimewa Yogyakarta',
             'position'=>'Purchasing Division','job_level'=>3,'employment_status'=>'contract',
             'join_date'=>'2023-11-13','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>3,'orig_approver_id'=>3],
            // Dewi Pusporini — Tax Officer
            ['orig_id'=>10,'employee_code'=>'003/FATSC/VI/2024','company_id'=>1,'department_id'=>30,
             'full_name'=>'Dewi Pusporini','email'=>'dewipuspo1995@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3324026104950000','npwp_15'=>null,'npwp_16'=>'3.32403E+15',
             'ptkp'=>'TK/0','bpjs_tk'=>'22071640803','bpjs_kesehatan'=>'0001495701371',
             'bank_account'=>'8467072620','bank_name'=>'BCA',
             'ktp_address'=>'Kebonromo RT 032  RW 011 Giripurwo, Girimulyo, Kulon Progo, DI Yogyakarta',
             'residential_address'=>'Kebonromo RT 032  RW 011 Giripurwo, Girimulyo, Kulon Progo, DI Yogyakarta',
             'position'=>'Tax Officer','job_level'=>3,'employment_status'=>'permanent',
             'join_date'=>'2024-06-03','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>3,'orig_approver_id'=>3],
            // Avissa Nova Fauzistika — Hrd
            ['orig_id'=>11,'employee_code'=>'003/HRDCS/II/2025','company_id'=>1,'department_id'=>5,
             'full_name'=>'Avissa Nova Fauzistika','email'=>'avissanova1@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3324026104950000','npwp_15'=>null,'npwp_16'=>'3.40413E+15',
             'ptkp'=>'TK/0','bpjs_tk'=>'20048065328','bpjs_kesehatan'=>'0000096378928',
             'bank_account'=>'600927043','bank_name'=>'BCA',
             'ktp_address'=>'Sono Wetan RT 003 RW032 Merdikorejo Tempel Sleman DI Yogyakarta',
             'residential_address'=>'Sono Wetan RT 003 RW032 Merdikorejo Tempel Sleman DI Yogyakarta',
             'position'=>'Hrd','job_level'=>3,'employment_status'=>'contract',
             'join_date'=>'2025-02-10','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>2,'orig_approver_id'=>2],
            // Prastowo Dian Kristiyanto — Rnd
            ['orig_id'=>12,'employee_code'=>'002/HARD/XI/2021','company_id'=>1,'department_id'=>25,
             'full_name'=>'Prastowo Dian Kristiyanto','email'=>'sandal.sobek157@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3326070803980001','npwp_15'=>'39.943.380.4-502.000','npwp_16'=>'3326070803980001',
             'ptkp'=>'TK/0','bpjs_tk'=>'23149577688','bpjs_kesehatan'=>'0001528931417',
             'bank_account'=>'0374648663','bank_name'=>'BCA',
             'ktp_address'=>'DK. Kaum RT 002  004 Kelurahan Kulu Kecamatan Karanganyar Kabupaten Pekalongan',
             'residential_address'=>'DK. Kaum RT 002  004 Kelurahan Kulu Kecamatan Karanganyar Kabupaten Pekalongan',
             'position'=>'Rnd','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2022-03-01','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>5,'orig_approver_id'=>5],
            // Muhammad Fauzan — Supporting Staf
            ['orig_id'=>13,'employee_code'=>'001/HRDCS/XII/2024','company_id'=>1,'department_id'=>32,
             'full_name'=>'Muhammad Fauzan','email'=>'muhfauzan394@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3404051403850002','npwp_15'=>'50.024.997.4-542.000','npwp_16'=>'3404051403850002',
             'ptkp'=>'TK/0','bpjs_tk'=>'20058535236','bpjs_kesehatan'=>'0000655176442',
             'bank_account'=>'0600822918','bank_name'=>'BCA',
             'ktp_address'=>'Ngaglik VII, Nganggrung RT 005   RW 021 Kelurahan Margoagung Kecamatan Seyegan Kabupaten Sleman',
             'residential_address'=>'Ngaglik VII, Nganggrung RT 005   RW 021 Kelurahan Margoagung Kecamatan Seyegan Kabupaten Sleman',
             'position'=>'Supporting Staf','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2022-03-01','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>2,'orig_approver_id'=>11],
            // Rasyid Priyo Nugroho — Production
            ['orig_id'=>14,'employee_code'=>'004/HARD/IX/2022','company_id'=>1,'department_id'=>22,
             'full_name'=>'Rasyid Priyo Nugroho','email'=>'rasyidpriyo@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3402171806010001','npwp_15'=>'39.496.139.5-543.000','npwp_16'=>'3402171806010001',
             'ptkp'=>'TK/0','bpjs_tk'=>'23149577530','bpjs_kesehatan'=>'0002348807635',
             'bank_account'=>'8465788603','bank_name'=>'BCA',
             'ktp_address'=>'Kalijoho RT 004   RW 000 Kelurahan Argosari Kecamatan Sedayu Kabupaten Bantul',
             'residential_address'=>'Kalijoho RT 004   RW 000 Kelurahan Argosari Kecamatan Sedayu Kabupaten Bantul',
             'position'=>'Production','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2022-09-28','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>5,'orig_approver_id'=>7],
            // Endarto Nugroho — Welder
            ['orig_id'=>15,'employee_code'=>'005/HARD/XII/2022','company_id'=>1,'department_id'=>22,
             'full_name'=>'Endarto Nugroho','email'=>'endartonugroho26@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3310083003970001','npwp_15'=>'73.143.356.1-525.000','npwp_16'=>'3310083003970001',
             'ptkp'=>'K/1','bpjs_tk'=>'23149577589','bpjs_kesehatan'=>'0000559341786',
             'bank_account'=>'0300995870','bank_name'=>'BCA',
             'ktp_address'=>'Gatak RT 001  001 Kelurahan Wonoboyo Kecamatan Jogonalan Kabupaten Klaten',
             'residential_address'=>'Gatak RT 001  001 Kelurahan Wonoboyo Kecamatan Jogonalan Kabupaten Klaten',
             'position'=>'Welder','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2022-12-21','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>5,'orig_approver_id'=>7],
            // Sheera Pratjya Mutiara — Admin Rnd
            ['orig_id'=>16,'employee_code'=>'006/HARD/III/2024','company_id'=>1,'department_id'=>25,
             'full_name'=>'Sheera Pratjya Mutiara','email'=>'mutiarasheera@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3306076812000001','npwp_15'=>'12.795.140.8-542.000','npwp_16'=>'3306076812000001',
             'ptkp'=>'TK/0','bpjs_tk'=>'24047164694','bpjs_kesehatan'=>'0001624674958',
             'bank_account'=>'0601252901','bank_name'=>'BCA',
             'ktp_address'=>'Sanggrahan 007017 Tlogodadi Mlati',
             'residential_address'=>'Sanggrahan 007017 Tlogodadi Mlati',
             'position'=>'Admin Rnd','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2024-03-01','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>5,'orig_approver_id'=>5],
            // Widya Annisa Rahmahwati — Hse Officer
            ['orig_id'=>17,'employee_code'=>'002/HRDCS/III/2024','company_id'=>1,'department_id'=>24,
             'full_name'=>'Widya Annisa Rahmahwati','email'=>'annisawidyamawa@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3404085602000002','npwp_15'=>'05.576.007.8-542.000','npwp_16'=>'3404085602000002',
             'ptkp'=>'TK/0','bpjs_tk'=>'24047164710','bpjs_kesehatan'=>'0001035697972',
             'bank_account'=>'7315187648','bank_name'=>'BCA',
             'ktp_address'=>'Karangwetan 006031 Tegaltirto Berbah',
             'residential_address'=>'Karangwetan 006031 Tegaltirto Berbah',
             'position'=>'Hse Officer','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2024-03-01','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>2,'orig_approver_id'=>11],
            // Dewi Setiawati — Manager Marketing
             ['orig_id'=>18,'employee_code'=>'002/MAR/IV/2026','company_id'=>1,'department_id'=>23,
             'full_name'=>'Dewi Setiawati','email'=>'dewi.priyambodo@yahoo.com',
             'gender'=>'female','marital_status'=>'married',
             'nik'=>'','npwp_15'=>'','npwp_16'=>'',
             'ptkp'=>'','bpjs_tk'=>'','bpjs_kesehatan'=>'',
             'bank_account'=>'0306408750','bank_name'=>'BCA',
             'ktp_address'=>'',
             'residential_address'=>'',
             'position'=>'Manager Marketing','job_level'=>2,'employment_status'=>'contract',
             'join_date'=>'2026-04-14','resign_date'=>null,'is_active'=>1,'role'=>'manager',
             'schedule_template_id'=>1,'orig_manager_id'=>2,'orig_approver_id'=>2],
            // Meilisa Jibrani — Publication Division
            // ['orig_id'=>18,'employee_code'=>'002/MARBD/III/2024','company_id'=>1,'department_id'=>28,
            //  'full_name'=>'Meilisa Jibrani','email'=>'meilisa0982@gmail.com',
            //  'gender'=>'female','marital_status'=>'single',
            //  'nik'=>'3403044305020001','npwp_15'=>'12.772.583.6-545.000','npwp_16'=>'3403044305020001',
            //  'ptkp'=>'TK/0','bpjs_tk'=>'24047164710','bpjs_kesehatan'=>'0001691997028',
            //  'bank_account'=>'4561370898','bank_name'=>'BCA',
            //  'ktp_address'=>'Kerjan 003001 Beji Patuk',
            //  'residential_address'=>'Kerjan 003001 Beji Patuk',
            //  'position'=>'Publication Division','job_level'=>4,'employment_status'=>'contract',
            //  'join_date'=>'2024-03-02','resign_date'=>null,'is_active'=>1,'role'=>'employee',
            //  'schedule_template_id'=>1,'orig_manager_id'=>2,'orig_approver_id'=>2],
            // Akhmad Syarif Abdullah — Software Division
            ['orig_id'=>19,'employee_code'=>'003/SOFTW/VI/2024','company_id'=>1,'department_id'=>20,
             'full_name'=>'Akhmad Syarif Abdullah','email'=>'ahmadsyariif3000@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3308192312000001','npwp_15'=>null,'npwp_16'=>'3308192312000001',
             'ptkp'=>'TK/0','bpjs_tk'=>'24100594738','bpjs_kesehatan'=>'0000201926777',
             'bank_account'=>'3440464253','bank_name'=>'BCA',
             'ktp_address'=>'Sidowangi RT. 013  RW. 006 Tegalrejo Kecamatan Tegalrejo',
             'residential_address'=>'Sidowangi RT. 013  RW. 006 Tegalrejo Kecamatan Tegalrejo',
             'position'=>'Software Division','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2024-06-10','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>4,'orig_approver_id'=>8],
            // Maritza Isyaura Putri Rizma — Accounting
            ['orig_id'=>20,'employee_code'=>'004/FATSC/VII/2024','company_id'=>1,'department_id'=>30,
             'full_name'=>'Maritza Isyaura Putri Rizma','email'=>'maritzaputririzma@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3404104202040000','npwp_15'=>null,'npwp_16'=>'3.4041E+15',
             'ptkp'=>'TK/0','bpjs_tk'=>'23125553398','bpjs_kesehatan'=>'0002036353094',
             'bank_account'=>'601150645','bank_name'=>'BCA',
             'ktp_address'=>'Komperta Blok K-07 Bromonilan RT 012 RW 004, Purwomartani, Kalasan',
             'residential_address'=>'Komperta Blok K-07 Bromonilan RT 012 RW 004, Purwomartani, Kalasan',
             'position'=>'Accounting','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2024-07-24','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>3,'orig_approver_id'=>10],
            // Zainni Novena Santi — Project Operation Administrator
            ['orig_id'=>21,'employee_code'=>'005/FATSC/IX/2024','company_id'=>1,'department_id'=>29,
             'full_name'=>'Zainni Novena Santi','email'=>'nzainni@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3.40402E+15','npwp_15'=>null,'npwp_16'=>'3.40402E+15',
             'ptkp'=>'TK/0','bpjs_tk'=>'24165926213','bpjs_kesehatan'=>'0001479214743',
             'bank_account'=>'374506885','bank_name'=>'BCA',
             'ktp_address'=>'Tlogo, RT 004,  RW 028, Ambarketawang, Gamping, Sleman, D.I Yogyakarta',
             'residential_address'=>'Tlogo, RT 004,  RW 028, Ambarketawang, Gamping, Sleman, D.I Yogyakarta',
             'position'=>'Project Operation Administrator','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2024-09-23','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>3,'orig_approver_id'=>3],
            // Monica Lintang Maharani — Purchasing Division
            ['orig_id'=>22,'employee_code'=>'006/FATSC/XII/2024','company_id'=>1,'department_id'=>26,
             'full_name'=>'Monica Lintang Maharani','email'=>'monicalintang.mhrn@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3404107108010001','npwp_15'=>null,'npwp_16'=>'3404107108010001',
             'ptkp'=>'TK/0','bpjs_tk'=>'24209824549','bpjs_kesehatan'=>'0002205278346',
             'bank_account'=>'8614047060','bank_name'=>'BCA',
             'ktp_address'=>'Jarakan RT 002  RW 011, Tirtomartani, Kalasan, Sleman, D.I Yogyakarta',
             'residential_address'=>'Jarakan RT 002  RW 011, Tirtomartani, Kalasan, Sleman, D.I Yogyakarta',
             'position'=>'Purchasing Division','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2024-12-06','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>3,'orig_approver_id'=>9],
            // Putri Anggi Hapsari — Purchasing Division
            ['orig_id'=>23,'employee_code'=>'007/FATSC/XII/2024','company_id'=>1,'department_id'=>26,
             'full_name'=>'Putri Anggi Hapsari','email'=>'putrianggi840@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3404145008040001','npwp_15'=>null,'npwp_16'=>'3404145008040001',
             'ptkp'=>'TK/0','bpjs_tk'=>'24209824531','bpjs_kesehatan'=>'0003068694448',
             'bank_account'=>'8467081343','bank_name'=>'BCA',
             'ktp_address'=>'Sanggrahan RT 03  RW 16, Lumbungrejo, Tempel, Sleman',
             'residential_address'=>'Sanggrahan RT 03  RW 16, Lumbungrejo, Tempel, Sleman',
             'position'=>'Purchasing Division','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2024-12-09','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>3,'orig_approver_id'=>9],
            // Muh Yusuf Kristanto — Security
            ['orig_id'=>25,'employee_code'=>'004/HRDCS/II/2025','company_id'=>1,'department_id'=>27,
             'full_name'=>'Muh Yusuf Kristanto','email'=>'myusufkris@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3309112903980007','npwp_15'=>null,'npwp_16'=>'3309112903980007',
             'ptkp'=>'TK/0','bpjs_tk'=>'25016701960','bpjs_kesehatan'=>'0003752848361',
             'bank_account'=>'6975667301','bank_name'=>'BCA',
             'ktp_address'=>'Tawangrejo RT 006 RW 006 SobokeRTo, Ngemplak, Boyolali, Jawa Tengah',
             'residential_address'=>'Tawangrejo RT 006 RW 006 SobokeRTo, Ngemplak, Boyolali, Jawa Tengah',
             'position'=>'Security','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2025-02-10','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>null,'orig_manager_id'=>2,'orig_approver_id'=>11],
            // Supriyono — Security
            ['orig_id'=>26,'employee_code'=>'005/HRDCS/II/2025','company_id'=>1,'department_id'=>27,
             'full_name'=>'Supriyono','email'=>'yono15927@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3313092009900001','npwp_15'=>null,'npwp_16'=>'3313092009900001',
             'ptkp'=>'TK/0','bpjs_tk'=>'25016701994','bpjs_kesehatan'=>'0002677375732',
             'bank_account'=>'7315234506','bank_name'=>'BCA',
             'ktp_address'=>'Wagah RT 002 RW 003 Popongan, Karanganyar, Karanganyar, Jawa Tengah',
             'residential_address'=>'Wagah RT 002 RW 003 Popongan, Karanganyar, Karanganyar, Jawa Tengah',
             'position'=>'Security','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2025-02-10','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>null,'orig_manager_id'=>2,'orig_approver_id'=>11],
            // Agung Prabowo — Security
            ['orig_id'=>27,'employee_code'=>'006/HRDCS/II/2025','company_id'=>1,'department_id'=>27,
             'full_name'=>'Agung Prabowo','email'=>'prabowoagung613@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3404100609960001','npwp_15'=>null,'npwp_16'=>'3404100609960001',
             'ptkp'=>'K/2','bpjs_tk'=>'25028322490','bpjs_kesehatan'=>'0001398329717',
             'bank_account'=>'8614082078','bank_name'=>'BCA',
             'ktp_address'=>'Kadirojo I RT 007 RW 002 Purwomartani, Kalasan, Sleman, DI Yogyakarta',
             'residential_address'=>'Kadirojo I RT 007 RW 002 Purwomartani, Kalasan, Sleman, DI Yogyakarta',
             'position'=>'Security','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2025-02-19','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>null,'orig_manager_id'=>2,'orig_approver_id'=>11],
            // Afif Faishahuda — Corporate Account Manager
            ['orig_id'=>28,'employee_code'=>'001/MAR/XI/2025','company_id'=>1,'department_id'=>23,
             'full_name'=>'Afif Faishahuda','email'=>'afiffaishahuda@gmail.com',
             'gender'=>'male','marital_status'=>'married',
             'nik'=>'3324030109000002','npwp_15'=>null,'npwp_16'=>'3324030109000002',
             'ptkp'=>'K/0','bpjs_tk'=>'25180554500','bpjs_kesehatan'=>'0001526410168',
             'bank_account'=>'6975747305','bank_name'=>'BCA',
             'ktp_address'=>'Kalibogor RT 002 RW 002 Kel. Kalibocoh, Kec. Sukorejo, Kab. Kendal, Prov. Jawa Tengah',
             'residential_address'=>'Kalibogor RT 002 RW 002 Kel. Kalibocoh, Kec. Sukorejo, Kab. Kendal, Prov. Jawa Tengah',
             'position'=>'Staff Marketing','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2025-11-24','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>18,'orig_approver_id'=>6],
            // Alvian Riswandanu — Welder
            ['orig_id'=>29,'employee_code'=>'007/HARD/XII/2025','company_id'=>1,'department_id'=>22,
             'full_name'=>'Alvian Riswandanu','email'=>'alvianrwsreaal@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3402052010060001','npwp_15'=>null,'npwp_16'=>'3402052010060001',
             'ptkp'=>'TK/0','bpjs_tk'=>'26005451708','bpjs_kesehatan'=>'0000647957349',
             'bank_account'=>'4452731456','bank_name'=>'BCA',
             'ktp_address'=>'Sribit, Wonodoro RT 001 RW 000, Kel. Mulyodadi, Kec. Bambanglipuro, Kab. Bantul, Prov. DI Yogyakarta',
             'residential_address'=>'Sribit, Wonodoro RT 001 RW 000, Kel. Mulyodadi, Kec. Bambanglipuro, Kab. Bantul, Prov. DI Yogyakarta',
             'position'=>'Welder','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2025-12-04','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>5,'orig_approver_id'=>7],
            // Lina Widiastuti — Finance
            ['orig_id'=>30,'employee_code'=>'008/FATSC/XII/2025','company_id'=>1,'department_id'=>30,
             'full_name'=>'Lina Widiastuti','email'=>'linawd21@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3404106108020002','npwp_15'=>null,'npwp_16'=>'3404106108020002',
             'ptkp'=>'TK/0','bpjs_tk'=>'26021816421','bpjs_kesehatan'=>'0001159463349',
             'bank_account'=>'0374571326','bank_name'=>'BCA',
             'ktp_address'=>'Bulusawit, Sambiroto RT 007 RW 004 Kel. Purwomartani, Kec. Kalasan, Kab. Sleman, Prov. DI Yogyakarta',
             'residential_address'=>'Bulusawit, Sambiroto RT 007 RW 004 Kel. Purwomartani, Kec. Kalasan, Kab. Sleman, Prov. DI Yogyakarta',
             'position'=>'Finance','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2025-12-18','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>3,'orig_approver_id'=>10],
            // Shandy Bagus Ferdiansyah — Software Division
            ['orig_id'=>31,'employee_code'=>'004/SOFTW/XII/2025','company_id'=>1,'department_id'=>20,
             'full_name'=>'Shandy Bagus Ferdiansyah','email'=>'shandybagus2@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3308102606030006','npwp_15'=>null,'npwp_16'=>'3308102606030006',
             'ptkp'=>'TK/0','bpjs_tk'=>'26021816447','bpjs_kesehatan'=>'0001601571611',
             'bank_account'=>'1222340823','bank_name'=>'BCA',
             'ktp_address'=>'Klarisan RT 001 RW 004 Kel. Donorojo, Kec. Mertoyudan, Kab. Magelang, Prov. Jawa Tengah',
             'residential_address'=>'Klarisan RT 001 RW 004 Kel. Donorojo, Kec. Mertoyudan, Kab. Magelang, Prov. Jawa Tengah',
             'position'=>'Software Division','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2025-12-22','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>4,'orig_approver_id'=>8],
            // Tata Azkia Azzahra — Ui/Ux Designer
            ['orig_id'=>32,'employee_code'=>'005/SOFTW/XII/2025','company_id'=>1,'department_id'=>20,
             'full_name'=>'Tata Azkia Azzahra','email'=>'tataazkia123@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3401056109020002','npwp_15'=>null,'npwp_16'=>'3401056109020002',
             'ptkp'=>'TK/0','bpjs_tk'=>'26021816470','bpjs_kesehatan'=>'0003598083696',
             'bank_account'=>'8611130082','bank_name'=>'BCA',
             'ktp_address'=>'Ledok RT 016 RW 000 Kel. Sidorejo, Kec. Lendah, Kab. Kulon Progo, Prov. DI Yogyakarta',
             'residential_address'=>'Ledok RT 016 RW 000 Kel. Sidorejo, Kec. Lendah, Kab. Kulon Progo, Prov. DI Yogyakarta',
             'position'=>'Ui/Ux Designer','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2025-12-29','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>4,'orig_approver_id'=>8],
            // Fransiscus Xaverius Okka Septa Pratama — Publication Division
            ['orig_id'=>33,'employee_code'=>'004/PUB/I/2026','company_id'=>1,'department_id'=>28,
             'full_name'=>'Fransiscus Xaverius Okka Septa Pratama','email'=>'okkajunior1@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3402161709020002','npwp_15'=>null,'npwp_16'=>'3402161709020002',
             'ptkp'=>'TK/0','bpjs_tk'=>'26021816397','bpjs_kesehatan'=>'0001434391604',
             'bank_account'=>'4452766756','bank_name'=>'BCA',
             'ktp_address'=>'Keparakan Lor Mg I672 YK RT 037 RW 008 Kel. Keprakan, Kec. Mergangsan, Kota Yogyakarta, Prov. DI Yogyakarta',
             'residential_address'=>'Keparakan Lor Mg I672 YK RT 037 RW 008 Kel. Keprakan, Kec. Mergangsan, Kota Yogyakarta, Prov. DI Yogyakarta',
             'position'=>'Publication Division','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2026-01-12','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>1,'orig_manager_id'=>18,'orig_approver_id'=>6],
            // Anisa Febriyanti — Admin Production
            ['orig_id'=>34,'employee_code'=>'008/HARD/II/2026','company_id'=>1,'department_id'=>22,
             'full_name'=>'Anisa Febriyanti','email'=>'anisafebriyanti000@gmail.com',
             'gender'=>'female','marital_status'=>'single',
             'nik'=>'3310135902030001','npwp_15'=>null,'npwp_16'=>'3310135902030001',
             'ptkp'=>'TK/0','bpjs_tk'=>'26021816462','bpjs_kesehatan'=>'0002740062497',
             'bank_account'=>'8614083236','bank_name'=>'BCA',
             'ktp_address'=>'Dukuhsari Sidokerto RT 006 RW 002 Purwomartani, Kalasan, Sleman, DI Yogyakarta',
             'residential_address'=>'Dukuhsari Sidokerto RT 006 RW 002 Purwomartani, Kalasan, Sleman, DI Yogyakarta',
             'position'=>'Admin Production','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2026-02-13','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>5,'orig_approver_id'=>7],
            // Ilham Yoga Pratama — Rnd
            ['orig_id'=>35,'employee_code'=>'009/HARD/III/2026','company_id'=>1,'department_id'=>25,
             'full_name'=>'Ilham Yoga Pratama','email'=>'ilhamyoga7485.com@gmail.com',
             'gender'=>'male','marital_status'=>'single',
             'nik'=>'3216022504040008','npwp_15'=>null,'npwp_16'=>'3216022504040008',
             'ptkp'=>'TK/0','bpjs_tk'=>null,'bpjs_kesehatan'=>null,
             'bank_account'=>'6975754603','bank_name'=>'BCA',
             'ktp_address'=>'Vila Mutiara gading 3 Blok K 1/67 RT. 004 RW. 026 Kebalen, Babelan, Kab. Bekasi, Prov. Jawa Barat',
             'residential_address'=>'Vila Mutiara gading 3 Blok K 1/67 RT. 004 RW. 026 Kebalen, Babelan, Kab. Bekasi, Prov. Jawa Barat',
             'position'=>'Rnd','job_level'=>4,'employment_status'=>'contract',
             'join_date'=>'2026-03-06','resign_date'=>null,'is_active'=>1,'role'=>'employee',
             'schedule_template_id'=>2,'orig_manager_id'=>5,'orig_approver_id'=>5],
        ];

        $insertedIds = [];
        foreach ($employees as $row) {
            $origId = $row['orig_id'];
            $origMgr = $row['orig_manager_id'];
            $origApp = $row['orig_approver_id'];
            $roleSlug = $row['role'] === 'admin' ? 'hr_admin' : $row['role'];
            unset($row['orig_id'], $row['orig_manager_id'], $row['orig_approver_id']);
            $row['password'] = $pwd;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $newId = DB::table('employees')->insertGetId($row);
            $insertedIds[$origId] = ['new_id' => $newId, 'orig_mgr' => $origMgr, 'orig_app' => $origApp, 'role' => $roleSlug];
            $this->command->line("  ✓ [{$row['employee_code']}] {$row['full_name']}");
        }

        // ── 5. APPROVAL CHAIN ──
        if (Schema::hasTable('roles') && Schema::hasTable('employee_roles')) {
            $this->command->info('Menyusun role karyawan...');
            $roleLabels = [
                'superadmin' => 'Superadmin',
                'hr_admin' => 'HR Admin',
                'payroll_admin' => 'Payroll Admin',
                'finance_admin' => 'Finance Admin',
                'manager' => 'Manager',
                'employee' => 'Employee',
            ];

            foreach ($roleLabels as $slug => $label) {
                DB::table('roles')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => $label, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }

            $roleIds = DB::table('roles')->pluck('id', 'slug');
            foreach ($insertedIds as $meta) {
                $roleId = $roleIds[$meta['role']] ?? $roleIds['employee'] ?? null;
                if ($roleId) {
                    DB::table('employee_roles')->insert([
                        'employee_id' => $meta['new_id'],
                        'role_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $this->command->info('Menyusun hierarki approval...');
        foreach ($insertedIds as $origId => $meta) {
            $newMgrId = $meta['orig_mgr'] ? ($insertedIds[$meta['orig_mgr']]['new_id'] ?? null) : null;
            $newAppId = $meta['orig_app'] ? ($insertedIds[$meta['orig_app']]['new_id'] ?? null) : null;
            DB::table('employees')->where('id', $meta['new_id'])->update([
                'manager_id'  => $newMgrId,
                'approver_id' => $newAppId,
            ]);
        }
        $this->command->info('✓ Approval chain selesai.');

        // ── 6. LEAVE BALANCES ──
        $this->command->info('Membuat saldo cuti tahunan...');
        $cutiTahunan = LeaveType::where('name', 'Cuti Tahunan')->first();
        if ($cutiTahunan) {
            foreach (Employee::where('is_active', true)->get() as $emp) {
                LeaveBalance::firstOrCreate(
                    ['employee_id' => $emp->id, 'leave_type_id' => $cutiTahunan->id, 'year' => now()->year],
                    ['total_days'=>12, 'carry_over'=>0, 'used_days'=>0, 'remaining_days'=>12]
                );
            }
        }

        $count = count($employees);
        $this->command->info('════════════════════════════════════════════');
        $this->command->info("  SELESAI! {$count} karyawan berhasil di-seed.");
        $this->command->info('════════════════════════════════════════════');
    }
}
