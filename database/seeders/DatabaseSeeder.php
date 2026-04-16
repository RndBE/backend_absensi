<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Department;
use App\Models\WorkSchedule;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\Shift;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateDay;
use App\Models\Holiday;
use App\Models\LeavePolicy;
use App\Models\Setting;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ═══════════════════════════════════════════════════
        // COMPANY
        // ═══════════════════════════════════════════════════
        $company = Company::create([
            'name' => 'CV Arta Solusindo',
            'address' => 'Batam, Kepulauan Riau',
            'phone' => '0778-123456',
        ]);

        // ═══════════════════════════════════════════════════
        // DEPARTMENTS — 5 Divisi + Sub-Divisi
        // ═══════════════════════════════════════════════════
        $c = $company->id;

        // Divisi utama
        $fat       = Department::create(['company_id' => $c, 'name' => 'FAT']);
        $software  = Department::create(['company_id' => $c, 'name' => 'SOFTWARE']);
        $hardware  = Department::create(['company_id' => $c, 'name' => 'HARDWARE']);
        $marketing = Department::create(['company_id' => $c, 'name' => 'MARKETING']);
        $hrd       = Department::create(['company_id' => $c, 'name' => 'HRD']);

        // Sub-divisi FAT
        $fatProject  = Department::create(['company_id' => $c, 'parent_id' => $fat->id, 'name' => 'FAT - PROJECT']);
        $fatSupport  = Department::create(['company_id' => $c, 'parent_id' => $fat->id, 'name' => 'FAT - SUPPORT']);

        // Sub-divisi Software
        $swFrontend = Department::create(['company_id' => $c, 'parent_id' => $software->id, 'name' => 'SW - FRONTEND']);
        $swBackend  = Department::create(['company_id' => $c, 'parent_id' => $software->id, 'name' => 'SW - BACKEND']);
        $swMobile   = Department::create(['company_id' => $c, 'parent_id' => $software->id, 'name' => 'SW - MOBILE']);

        // Sub-divisi Hardware
        $hwRnd      = Department::create(['company_id' => $c, 'parent_id' => $hardware->id, 'name' => 'HW - RND']);
        $hwProduksi = Department::create(['company_id' => $c, 'parent_id' => $hardware->id, 'name' => 'HW - PRODUKSI']);
        $hwQc       = Department::create(['company_id' => $c, 'parent_id' => $hardware->id, 'name' => 'HW - QC']);

        // Sub-divisi Marketing
        $mktSales   = Department::create(['company_id' => $c, 'parent_id' => $marketing->id, 'name' => 'MKT - SALES']);
        $mktDigital = Department::create(['company_id' => $c, 'parent_id' => $marketing->id, 'name' => 'MKT - DIGITAL']);

        // Sub-divisi HRD
        $hrdRecruit = Department::create(['company_id' => $c, 'parent_id' => $hrd->id, 'name' => 'HRD - REKRUTMEN']);
        $hrdAdmin   = Department::create(['company_id' => $c, 'parent_id' => $hrd->id, 'name' => 'HRD - ADMIN']);

        // ═══════════════════════════════════════════════════
        // WORK SCHEDULE
        // ═══════════════════════════════════════════════════
        $schedule = WorkSchedule::create([
            'company_id' => $c,
            'name' => '5 Hari Kerja',
            'work_days' => 5,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $s = $schedule->id;
        $defaults = [
            'company_id' => $c,
            'work_schedule_id' => $s,
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
        ];

        // ═══════════════════════════════════════════════════
        // LEVEL 1 — DIREKTUR (1 orang, puncak chain)
        // ═══════════════════════════════════════════════════
        $direktur = Employee::create(array_merge($defaults, [
            'employee_code' => '001/DIR/I/2015',
            'department_id' => $fat->id,
            'full_name' => 'NOFIYANTO',
            'email' => 'nofiyanto@artasolusindo.com',
            'phone' => '081234567890',
            'position' => 'DIREKTUR',
            'job_level' => 1,
            'join_date' => '2015-01-05',
            'role' => 'admin',
        ]));

        // ═══════════════════════════════════════════════════
        // LEVEL 2 — MANAGER (4 orang, per divisi kecuali HRD)
        // approver_id → Direktur
        // ═══════════════════════════════════════════════════
        $mgrFat = Employee::create(array_merge($defaults, [
            'employee_code' => '002/FAT/III/2016',
            'department_id' => $fat->id,
            'approver_id' => $direktur->id,
            'manager_id' => $direktur->id,
            'full_name' => 'HENDRA WIJAYA',
            'email' => 'hendra.wijaya@artasolusindo.com',
            'phone' => '081200000001',
            'position' => 'MANAGER FAT',
            'job_level' => 2,
            'join_date' => '2016-03-01',
            'role' => 'manager',
        ]));

        $mgrSw = Employee::create(array_merge($defaults, [
            'employee_code' => '003/SW/V/2017',
            'department_id' => $software->id,
            'approver_id' => $direktur->id,
            'manager_id' => $direktur->id,
            'full_name' => 'BUDI SANTOSO',
            'email' => 'budi.santoso@artasolusindo.com',
            'phone' => '081200000002',
            'position' => 'MANAGER SOFTWARE',
            'job_level' => 2,
            'join_date' => '2017-05-01',
            'role' => 'manager',
        ]));

        $mgrHw = Employee::create(array_merge($defaults, [
            'employee_code' => '004/HW/I/2017',
            'department_id' => $hardware->id,
            'approver_id' => $direktur->id,
            'manager_id' => $direktur->id,
            'full_name' => 'AHMAD RIZAL',
            'email' => 'ahmad.rizal@artasolusindo.com',
            'phone' => '081200000003',
            'position' => 'MANAGER HARDWARE',
            'job_level' => 2,
            'join_date' => '2017-01-15',
            'role' => 'manager',
        ]));

        $mgrMkt = Employee::create(array_merge($defaults, [
            'employee_code' => '005/MKT/VII/2018',
            'department_id' => $marketing->id,
            'approver_id' => $direktur->id,
            'manager_id' => $direktur->id,
            'full_name' => 'DEWI LESTARI',
            'email' => 'dewi.lestari@artasolusindo.com',
            'phone' => '081200000004',
            'position' => 'MANAGER MARKETING',
            'job_level' => 2,
            'join_date' => '2018-07-01',
            'role' => 'manager',
        ]));

        // HRD tidak punya Level 2 (no manager)

        // ═══════════════════════════════════════════════════
        // LEVEL 3 — LEADER (per sub-divisi)
        // approver_id → Manager divisi masing-masing
        // HRD Leader → langsung Direktur (no Mgr)
        // ═══════════════════════════════════════════════════

        // FAT Leaders
        $ldrProject = Employee::create(array_merge($defaults, [
            'employee_code' => '010/FAT/VI/2019',
            'department_id' => $fatProject->id,
            'approver_id' => $mgrFat->id,
            'manager_id' => $mgrFat->id,
            'full_name' => 'RIKO PRASETYA',
            'email' => 'riko.prasetya@artasolusindo.com',
            'phone' => '081200000010',
            'position' => 'LEADER PROJECT',
            'job_level' => 3,
            'join_date' => '2019-06-01',
            'role' => 'employee',
        ]));

        $ldrSupport = Employee::create(array_merge($defaults, [
            'employee_code' => '011/FAT/VIII/2019',
            'department_id' => $fatSupport->id,
            'approver_id' => $mgrFat->id,
            'manager_id' => $mgrFat->id,
            'full_name' => 'YANTO ARIFIN',
            'email' => 'yanto.arifin@artasolusindo.com',
            'phone' => '081200000011',
            'position' => 'LEADER SUPPORT',
            'job_level' => 3,
            'join_date' => '2019-08-01',
            'role' => 'employee',
        ]));

        // Software Leaders
        $ldrFe = Employee::create(array_merge($defaults, [
            'employee_code' => '012/SW/III/2019',
            'department_id' => $swFrontend->id,
            'approver_id' => $mgrSw->id,
            'manager_id' => $mgrSw->id,
            'full_name' => 'RINA WATI',
            'email' => 'rina.wati@artasolusindo.com',
            'phone' => '081200000012',
            'position' => 'LEADER FRONTEND',
            'job_level' => 3,
            'join_date' => '2019-03-01',
            'role' => 'employee',
        ]));

        $ldrBe = Employee::create(array_merge($defaults, [
            'employee_code' => '013/SW/IV/2019',
            'department_id' => $swBackend->id,
            'approver_id' => $mgrSw->id,
            'manager_id' => $mgrSw->id,
            'full_name' => 'DONI PRASETYO',
            'email' => 'doni.prasetyo@artasolusindo.com',
            'phone' => '081200000013',
            'position' => 'LEADER BACKEND',
            'job_level' => 3,
            'join_date' => '2019-04-01',
            'role' => 'employee',
        ]));

        // SW Mobile: tidak punya leader → staff langsung ke Manager SW

        // Hardware Leaders (RND tidak punya leader!)
        $ldrProduksi = Employee::create(array_merge($defaults, [
            'employee_code' => '015/HW/II/2020',
            'department_id' => $hwProduksi->id,
            'approver_id' => $mgrHw->id,
            'manager_id' => $mgrHw->id,
            'full_name' => 'AGUS SETIAWAN',
            'email' => 'agus.setiawan@artasolusindo.com',
            'phone' => '081200000015',
            'position' => 'LEADER PRODUKSI',
            'job_level' => 3,
            'join_date' => '2020-02-01',
            'role' => 'employee',
        ]));

        $ldrQc = Employee::create(array_merge($defaults, [
            'employee_code' => '016/HW/V/2020',
            'department_id' => $hwQc->id,
            'approver_id' => $mgrHw->id,
            'manager_id' => $mgrHw->id,
            'full_name' => 'ANDI PRASETYO',
            'email' => 'andi.prasetyo@artasolusindo.com',
            'phone' => '081200000016',
            'position' => 'LEADER QC',
            'job_level' => 3,
            'join_date' => '2020-05-01',
            'role' => 'employee',
        ]));

        // Marketing Leaders
        $ldrSales = Employee::create(array_merge($defaults, [
            'employee_code' => '017/MKT/III/2020',
            'department_id' => $mktSales->id,
            'approver_id' => $mgrMkt->id,
            'manager_id' => $mgrMkt->id,
            'full_name' => 'BAMBANG SUTRISNO',
            'email' => 'bambang.sutrisno@artasolusindo.com',
            'phone' => '081200000017',
            'position' => 'LEADER SALES',
            'job_level' => 3,
            'join_date' => '2020-03-01',
            'role' => 'employee',
        ]));

        // MKT Digital: tidak punya leader → langsung ke Manager MKT

        // HRD Leaders → approver langsung Direktur (no Manager)
        $ldrHrdRecruit = Employee::create(array_merge($defaults, [
            'employee_code' => '018/HRD/I/2018',
            'department_id' => $hrdRecruit->id,
            'approver_id' => $direktur->id,
            'manager_id' => $direktur->id,
            'full_name' => 'SARI MULYANI',
            'email' => 'sari.mulyani@artasolusindo.com',
            'phone' => '081200000018',
            'position' => 'LEADER REKRUTMEN',
            'job_level' => 3,
            'join_date' => '2018-01-15',
            'role' => 'employee',
        ]));

        $ldrHrdAdmin = Employee::create(array_merge($defaults, [
            'employee_code' => '019/HRD/VI/2019',
            'department_id' => $hrdAdmin->id,
            'approver_id' => $direktur->id,
            'manager_id' => $direktur->id,
            'full_name' => 'LINA SUSANTI',
            'email' => 'lina.susanti@artasolusindo.com',
            'phone' => '081200000019',
            'position' => 'LEADER ADMIN HRD',
            'job_level' => 3,
            'join_date' => '2019-06-01',
            'role' => 'employee',
        ]));

        // ═══════════════════════════════════════════════════
        // LEVEL 4 — STAFF (2-3 per sub-divisi)
        // ═══════════════════════════════════════════════════
        $contractDefaults = array_merge($defaults, ['employment_status' => 'contract', 'role' => 'employee', 'job_level' => 4]);

        $staff = [
            // ── FAT - Project (→ Leader Project → Mgr FAT) ──
            ['code' => '030/FAT/I/2022', 'dept' => $fatProject->id, 'approver' => $ldrProject->id,
             'name' => 'DIMAS ADITYA', 'email' => 'dimas.aditya@artasolusindo.com',
             'phone' => '081200000030', 'position' => 'PROJECT ENGINEER', 'join' => '2022-01-15'],
            ['code' => '031/FAT/V/2022', 'dept' => $fatProject->id, 'approver' => $ldrProject->id,
             'name' => 'WAHYU HIDAYAT', 'email' => 'wahyu.hidayat@artasolusindo.com',
             'phone' => '081200000031', 'position' => 'PROJECT ENGINEER', 'join' => '2022-05-01'],

            // ── FAT - Support (→ Leader Support → Mgr FAT) ──
            ['code' => '032/FAT/III/2022', 'dept' => $fatSupport->id, 'approver' => $ldrSupport->id,
             'name' => 'TEGUH PRASETYO', 'email' => 'teguh.prasetyo@artasolusindo.com',
             'phone' => '081200000032', 'position' => 'SUPPORT ENGINEER', 'join' => '2022-03-01'],

            // ── SW Frontend (→ Leader FE → Mgr SW) ──
            ['code' => '033/SW/XI/2022', 'dept' => $swFrontend->id, 'approver' => $ldrFe->id,
             'name' => 'FADEL MUHAMMAD IRSYAD', 'email' => 'fadelirsyad04@gmail.com',
             'phone' => '089514761334', 'position' => 'FRONTEND DEVELOPER', 'join' => '2022-11-17'],
            ['code' => '034/SW/II/2023', 'dept' => $swFrontend->id, 'approver' => $ldrFe->id,
             'name' => 'NINA KARTIKA', 'email' => 'nina.kartika@artasolusindo.com',
             'phone' => '081200000034', 'position' => 'UI/UX DESIGNER', 'join' => '2023-02-01'],

            // ── SW Backend (→ Leader BE → Mgr SW) ──
            ['code' => '035/SW/VI/2022', 'dept' => $swBackend->id, 'approver' => $ldrBe->id,
             'name' => 'ARIF RAHMAN', 'email' => 'arif.rahman@artasolusindo.com',
             'phone' => '081200000035', 'position' => 'BACKEND DEVELOPER', 'join' => '2022-06-01'],
            ['code' => '036/SW/IX/2023', 'dept' => $swBackend->id, 'approver' => $ldrBe->id,
             'name' => 'PUTRI HANDAYANI', 'email' => 'putri.handayani@artasolusindo.com',
             'phone' => '081200000036', 'position' => 'BACKEND DEVELOPER', 'join' => '2023-09-01'],

            // ── SW Mobile (NO LEADER → langsung Mgr SW) ──
            ['code' => '037/SW/IV/2023', 'dept' => $swMobile->id, 'approver' => $mgrSw->id,
             'name' => 'BAYU ADI NUGROHO', 'email' => 'bayu.adi@artasolusindo.com',
             'phone' => '081200000037', 'position' => 'MOBILE DEVELOPER', 'join' => '2023-04-01'],
            ['code' => '038/SW/VII/2023', 'dept' => $swMobile->id, 'approver' => $mgrSw->id,
             'name' => 'RIZKI AMALIA', 'email' => 'rizki.amalia@artasolusindo.com',
             'phone' => '081200000038', 'position' => 'MOBILE DEVELOPER', 'join' => '2023-07-01'],

            // ── HW RND (NO LEADER → langsung Mgr HW) ──
            ['code' => '039/HW/III/2023', 'dept' => $hwRnd->id, 'approver' => $mgrHw->id,
             'name' => 'RUDI HERMAWAN', 'email' => 'rudi.hermawan@artasolusindo.com',
             'phone' => '081200000039', 'position' => 'RND ENGINEER', 'join' => '2023-03-01'],
            ['code' => '040/HW/VIII/2023', 'dept' => $hwRnd->id, 'approver' => $mgrHw->id,
             'name' => 'GUNAWAN EFFENDI', 'email' => 'gunawan.effendi@artasolusindo.com',
             'phone' => '081200000040', 'position' => 'RND ENGINEER', 'join' => '2023-08-01'],

            // ── HW Produksi (→ Leader Produksi → Mgr HW) ──
            ['code' => '041/HW/IX/2023', 'dept' => $hwProduksi->id, 'approver' => $ldrProduksi->id,
             'name' => 'EKO PRASETYA', 'email' => 'eko.prasetya@artasolusindo.com',
             'phone' => '081200000041', 'position' => 'PRODUCTION STAFF', 'join' => '2023-09-15'],
            ['code' => '042/HW/X/2023', 'dept' => $hwProduksi->id, 'approver' => $ldrProduksi->id,
             'name' => 'WAWAN SETIAWAN', 'email' => 'wawan.setiawan@artasolusindo.com',
             'phone' => '081200000042', 'position' => 'PRODUCTION STAFF', 'join' => '2023-10-01'],

            // ── HW QC (→ Leader QC → Mgr HW) ──
            ['code' => '043/HW/XI/2023', 'dept' => $hwQc->id, 'approver' => $ldrQc->id,
             'name' => 'SLAMET RIYADI', 'email' => 'slamet.riyadi@artasolusindo.com',
             'phone' => '081200000043', 'position' => 'QC INSPECTOR', 'join' => '2023-11-01'],

            // ── MKT Sales (→ Leader Sales → Mgr MKT) ──
            ['code' => '044/MKT/II/2023', 'dept' => $mktSales->id, 'approver' => $ldrSales->id,
             'name' => 'LISA PERMATA', 'email' => 'lisa.permata@artasolusindo.com',
             'phone' => '081200000044', 'position' => 'SALES EXECUTIVE', 'join' => '2023-02-15'],
            ['code' => '045/MKT/V/2023', 'dept' => $mktSales->id, 'approver' => $ldrSales->id,
             'name' => 'TONO SUHARJO', 'email' => 'tono.suharjo@artasolusindo.com',
             'phone' => '081200000045', 'position' => 'SALES EXECUTIVE', 'join' => '2023-05-01'],

            // ── MKT Digital (NO LEADER → langsung Mgr MKT) ──
            ['code' => '046/MKT/VIII/2023', 'dept' => $mktDigital->id, 'approver' => $mgrMkt->id,
             'name' => 'MAYA SARI', 'email' => 'maya.sari@artasolusindo.com',
             'phone' => '081200000046', 'position' => 'DIGITAL MARKETING', 'join' => '2023-08-15'],

            // ── HRD Rekrutmen (→ Leader HRD Recruit → Direktur) ──
            ['code' => '047/HRD/IV/2023', 'dept' => $hrdRecruit->id, 'approver' => $ldrHrdRecruit->id,
             'name' => 'SITI NURHALIZA', 'email' => 'siti.nurhaliza@artasolusindo.com',
             'phone' => '085612345678', 'position' => 'HR RECRUITER', 'join' => '2023-04-01'],

            // ── HRD Admin (→ Leader HRD Admin → Direktur) ──
            ['code' => '048/HRD/VI/2023', 'dept' => $hrdAdmin->id, 'approver' => $ldrHrdAdmin->id,
             'name' => 'RATNA DEWI', 'email' => 'ratna.dewi@artasolusindo.com',
             'phone' => '081200000048', 'position' => 'HR ADMIN', 'join' => '2023-06-01'],
            ['code' => '049/HRD/IX/2023', 'dept' => $hrdAdmin->id, 'approver' => $ldrHrdAdmin->id,
             'name' => 'JOKO SUSILO', 'email' => 'joko.susilo@artasolusindo.com',
             'phone' => '081200000049', 'position' => 'PAYROLL ADMIN', 'join' => '2023-09-01'],
        ];

        foreach ($staff as $s) {
            Employee::create(array_merge($contractDefaults, [
                'employee_code' => $s['code'],
                'department_id' => $s['dept'],
                'approver_id' => $s['approver'],
                'manager_id' => $s['approver'],
                'full_name' => $s['name'],
                'email' => $s['email'],
                'phone' => $s['phone'],
                'position' => $s['position'],
                'join_date' => $s['join'],
            ]));
        }

        // ═══════════════════════════════════════════════════
        // LEAVE TYPES
        // ═══════════════════════════════════════════════════
        $cutiTahunan = LeaveType::create(['name' => 'Cuti Tahunan', 'max_days' => 12]);
        LeaveType::create(['name' => 'Cuti Sakit', 'max_days' => 14]);
        LeaveType::create(['name' => 'Izin Datang Terlambat', 'max_days' => 365]);
        LeaveType::create(['name' => 'Cuti Melahirkan', 'max_days' => 90]);

        // Leave Policies
        LeavePolicy::create([
            'company_id' => $c,
            'leave_type_id' => $cutiTahunan->id,
            'days_per_year' => 12,
            'min_tenure_months' => 12,
            'max_carry_over' => 0,
            'is_prorated' => true,
            'is_active' => true,
        ]);

        // Leave Balances
        foreach (Employee::all() as $emp) {
            LeaveBalance::create([
                'employee_id' => $emp->id,
                'leave_type_id' => $cutiTahunan->id,
                'year' => now()->year,
                'total_days' => 12,
                'carry_over' => 0,
                'used_days' => 0,
                'remaining_days' => 12,
            ]);
        }

        // ═══════════════════════════════════════════════════
        // SETTINGS
        // ═══════════════════════════════════════════════════
        Setting::setValue('office_latitude', '1.0456');
        Setting::setValue('office_longitude', '104.0305');
        Setting::setValue('office_radius_meters', '100');
        Setting::setValue('office_address', 'Jl. Contoh No. 123');
        Setting::setValue('require_photo', '1');
        Setting::setValue('require_gps', '1');
        Setting::setValue('allow_remote_clockin', '0');
        Setting::setValue('remote_requires_approval', '1');
        Setting::setValue('remote_requires_notes', '1');
        Setting::setValue('clockin_reminder_enabled', '0');
        Setting::setValue('clockin_reminder_time', '07:45');
        Setting::setValue('auto_clockout_enabled', '0');
        Setting::setValue('auto_clockout_time', '18:00');
        Setting::setValue('max_attachment_size_mb', '10');

        // ═══════════════════════════════════════════════════
        // SHIFTS (Master)
        // ═══════════════════════════════════════════════════
        $pagiShift = Shift::create(['company_id' => $c, 'name' => 'Pagi',     'start_time' => '08:00', 'end_time' => '16:00', 'color' => '#3B82F6', 'sort_order' => 1]);
        Shift::create(['company_id' => $c, 'name' => 'Siang',    'start_time' => '14:00', 'end_time' => '22:00', 'color' => '#F59E0B', 'sort_order' => 2]);
        Shift::create(['company_id' => $c, 'name' => 'Malam',    'start_time' => '22:00', 'end_time' => '06:00', 'color' => '#8B5CF6', 'sort_order' => 3]);
        Shift::create(['company_id' => $c, 'name' => 'Security', 'start_time' => '07:00', 'end_time' => '19:00', 'color' => '#EF4444', 'sort_order' => 4]);
        $offShift = Shift::create(['company_id' => $c, 'name' => 'Off',      'is_off' => true,         'color' => '#6B7280', 'sort_order' => 5]);

        // ═══════════════════════════════════════════════════
        // SCHEDULE TEMPLATES
        // ═══════════════════════════════════════════════════
        $tpl5 = ScheduleTemplate::create(['company_id' => $c, 'name' => '5 Hari Kerja (Pagi)', 'description' => 'Senin-Jumat shift pagi, Sabtu-Minggu off']);
        foreach ([1 => $pagiShift, 2 => $pagiShift, 3 => $pagiShift, 4 => $pagiShift, 5 => $pagiShift, 6 => $offShift, 7 => $offShift] as $dow => $sh) {
            ScheduleTemplateDay::create(['template_id' => $tpl5->id, 'day_of_week' => $dow, 'shift_id' => $sh->id]);
        }

        $tpl6 = ScheduleTemplate::create(['company_id' => $c, 'name' => '6 Hari Kerja (Pagi)', 'description' => 'Senin-Sabtu shift pagi, Minggu off']);
        foreach ([1 => $pagiShift, 2 => $pagiShift, 3 => $pagiShift, 4 => $pagiShift, 5 => $pagiShift, 6 => $pagiShift, 7 => $offShift] as $dow => $sh) {
            ScheduleTemplateDay::create(['template_id' => $tpl6->id, 'day_of_week' => $dow, 'shift_id' => $sh->id]);
        }

        // ═══════════════════════════════════════════════════
        // NATIONAL HOLIDAYS 2026
        // ═══════════════════════════════════════════════════
        $holidays = [
            // Libur Nasional
            ['2026-01-01', 'Tahun Baru Masehi'],
            ['2026-01-16', 'Isra Mi\'raj Nabi Muhammad SAW'],
            ['2026-02-17', 'Tahun Baru Imlek 2577'],
            ['2026-03-19', 'Hari Suci Nyepi'],
            ['2026-03-21', 'Hari Raya Idul Fitri 1447 H'],
            ['2026-03-22', 'Hari Raya Idul Fitri 1447 H'],
            ['2026-04-03', 'Wafat Yesus Kristus'],
            ['2026-05-01', 'Hari Buruh Internasional'],
            ['2026-05-14', 'Kenaikan Yesus Kristus'],
            ['2026-05-27', 'Hari Raya Idul Adha 1447 H'],
            ['2026-05-31', 'Hari Raya Waisak 2570 BE'],
            ['2026-06-01', 'Hari Lahir Pancasila'],
            ['2026-06-16', 'Tahun Baru Islam 1448 H'],
            ['2026-08-17', 'Hari Kemerdekaan RI'],
            ['2026-08-25', 'Maulid Nabi Muhammad SAW'],
            ['2026-12-25', 'Hari Raya Natal'],
        ];

        foreach ($holidays as [$date, $name]) {
            Holiday::create([
                'company_id' => $c,
                'date' => $date,
                'name' => $name,
                'is_national' => true,
            ]);
        }
    }
}
