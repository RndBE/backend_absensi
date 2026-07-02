<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EmployeePortalMagicLinkMail;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\EmployeePayroll;
use App\Models\EmployeePayrollComponent;
use App\Models\Role;
use App\Models\WorkSchedule;
use App\Services\BpjsCalculator;
use App\Services\EmployeePortalMagicLinkService;
use App\Services\Pph21Calculator;
use App\Services\WhatsAppGatewayService;
use App\Support\AdminPermission;
use App\Support\SimpleXlsxExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    private const APPROVAL_REQUEST_TYPES = ['leave', 'overtime', 'attendance', 'budget', 'travel_report', 'lpj'];

    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $employees = $this->employeeListQuery($request, $admin)
            ->with(['department:id,name', 'workSchedule:id,name'])
            ->orderBy('full_name')
            ->get();
        $departments = Department::where('company_id', $admin->company_id)->get();

        return view('admin.employees.index', compact('employees', 'departments'));
    }

    public function export(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $employees = $this->employeeListQuery($request, $admin)
            ->with(['department:id,name', 'workSchedule:id,name', 'manager:id,full_name'])
            ->orderBy('full_name')
            ->get();

        $headers = [
            'Kode',
            'Nama',
            'Email',
            'No. HP',
            'Departemen',
            'Posisi / Jabatan',
            'Level',
            'Status Kepegawaian',
            'Role',
            'Status Aktif',
            'Jadwal Kerja',
            'Atasan / Manager',
            'Tanggal Bergabung',
            'Kontrak Mulai',
            'Kontrak Berakhir',
            'Lama Bekerja',
            'Sisa Kontrak',
            'NIK KTP',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Agama',
            'Status Perkawinan',
            'Gol. Darah',
            'Alamat KTP',
            'Alamat Domisili',
            'Kode Pos',
            'PTKP',
            'NPWP 15 Digit',
            'NITKU / NPWP 16 Digit',
            'BPJS Ketenagakerjaan',
            'BPJS Kesehatan',
            'Nama Bank',
            'No. Rekening',
            'Institusi / Universitas',
            'Pembimbing Institusi',
            'Pembimbing Lapangan / Kantor',
            'Catatan Magang',
        ];

        $rows = $employees->map(fn (Employee $employee) => [
            $employee->employee_code ?? '-',
            $employee->full_name ?? '-',
            $employee->email ?? '-',
            $employee->phone ?? '-',
            $employee->department?->name ?? '-',
            $employee->position ?? '-',
            $employee->job_level ?? '-',
            $this->employmentStatusLabel($employee->employment_status),
            $employee->role ?? '-',
            $employee->is_active ? 'Aktif' : 'Nonaktif',
            $employee->workSchedule?->name ?? '-',
            $employee->manager?->full_name ?? '-',
            $this->dateLabel($employee->join_date),
            $this->dateLabel($employee->contract_start_date),
            $this->dateLabel($employee->contract_end_date),
            $this->workDurationLabel($employee),
            $this->contractRemainingLabel($employee),
            $employee->nik ?? '-',
            $this->genderLabel($employee->gender),
            $employee->birth_place ?? '-',
            $this->dateLabel($employee->birth_date),
            $employee->religion ?? '-',
            $this->maritalStatusLabel($employee->marital_status),
            $employee->blood_type ?? '-',
            $employee->ktp_address ?? '-',
            $employee->residential_address ?? '-',
            $employee->postal_code ?? '-',
            $employee->ptkp ?? '-',
            $employee->npwp_15 ?? '-',
            $employee->npwp_16 ?? '-',
            $employee->bpjs_tk ?? '-',
            $employee->bpjs_kesehatan ?? '-',
            $employee->bank_name ?? '-',
            $employee->bank_account ?? '-',
            $employee->internship_institution ?? '-',
            $employee->internship_supervisor ?? '-',
            $employee->internship_field_supervisor ?? '-',
            $employee->internship_notes ?? '-',
        ]);

        return response()->streamDownload(function () use ($headers, $rows) {
            echo SimpleXlsxExporter::make($headers, $rows, 'Data Karyawan');
        }, 'karyawan_'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function create()
    {
        $admin = Employee::find(session('admin_id'));
        $departments = Department::where('company_id', $admin->company_id)->get();
        $workSchedules = WorkSchedule::where('company_id', $admin->company_id)->get();
        $managers = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->orderBy('job_level')->orderBy('full_name')
            ->get();

        $adminRoles = app(AdminPermission::class)->roles();

        return view('admin.employees.create', compact('departments', 'workSchedules', 'managers', 'adminRoles'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $request->validate([
            'employee_code' => 'required|unique:employees',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:8',
            'department_id' => 'required|exists:departments,id',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'position' => 'nullable|string',
            'job_level' => 'nullable|integer',
            'employment_status' => 'required|in:permanent,contract,intern,probation,outsourcing',
            'join_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'internship_institution' => 'nullable|string|max:255',
            'internship_supervisor' => 'nullable|string|max:255',
            'internship_field_supervisor' => 'nullable|string|max:255',
            'internship_notes' => 'nullable|string|max:1000',
            'role' => 'required|in:superadmin,hr_admin,payroll_admin,finance_admin,manager,employee',
            'manager_id' => 'nullable|exists:employees,id',
            'approver_id' => 'nullable|exists:employees,id',
            'photo' => 'nullable|image|max:2048',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'blood_type' => 'nullable|in:A,B,AB,O',
            'religion' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:10',
            'ktp_address' => 'nullable|string',
            'residential_address' => 'nullable|string',
        ]);

        $data = array_merge($request->except('photo'), [
            'company_id' => $admin->company_id,
            'is_active' => true,
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('employees/photos', 'public');
        }

        $employee = Employee::create($data);
        $this->syncPrimaryRole($employee, $request->input('role'));

        return redirect()->route('admin.employees.index')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function show($id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)
            ->with(['department', 'workSchedule', 'manager'])->findOrFail($id);

        // Load approval chains
        $approvalChains = [];
        foreach (self::APPROVAL_REQUEST_TYPES as $type) {
            $approvalChains[$type] = EmployeeApprover::getChain($id, $type);
        }

        return view('admin.employees.show', compact('employee', 'approvalChains'));
    }

    public function edit($id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)
            ->with('roles:id,slug,name')->findOrFail($id);
        $departments = Department::where('company_id', $admin->company_id)->get();
        $workSchedules = WorkSchedule::where('company_id', $admin->company_id)->get();
        $managers = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('id', '!=', $id)
            ->orderBy('job_level')->orderBy('full_name')
            ->get();

        // Load approval chains
        $approvalChains = [];
        foreach (self::APPROVAL_REQUEST_TYPES as $type) {
            $approvalChains[$type] = EmployeeApprover::getChain($id, $type);
        }

        $adminRoles = app(AdminPermission::class)->roles();

        return view('admin.employees.edit', compact('employee', 'departments', 'workSchedules', 'managers', 'approvalChains', 'adminRoles'));
    }

    public function sendPortalLink($id, EmployeePortalMagicLinkService $magicLinks)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)->findOrFail($id);

        if (! $employee->is_active) {
            return redirect()->back(302, [], route('admin.employees.index'))
                ->with('error', 'Link portal hanya bisa dikirim ke karyawan aktif.');
        }

        if (! $employee->email) {
            return redirect()->back(302, [], route('admin.employees.index'))
                ->with('error', 'Karyawan ini belum memiliki email.');
        }

        $magicLink = $magicLinks->create($employee);

        Mail::to($employee->email)->send(new EmployeePortalMagicLinkMail(
            $employee,
            $magicLink['url'],
            $magicLink['link']->expires_at
        ));

        return redirect()->back(302, [], route('admin.employees.index'))
            ->with('success', 'Link portal employee berhasil dikirim ke '.$employee->email.'.');
    }

    public function sendPortalLinkToAll(EmployeePortalMagicLinkService $magicLinks)
    {
        $admin = Employee::find(session('admin_id'));
        $employees = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('role', 'employee')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('full_name')
            ->get();

        if ($employees->isEmpty()) {
            return redirect()->back(302, [], route('admin.employees.index'))
                ->with('error', 'Tidak ada karyawan aktif dengan email untuk dikirimi link portal.');
        }

        foreach ($employees as $employee) {
            $magicLink = $magicLinks->create($employee);

            Mail::to($employee->email)->send(new EmployeePortalMagicLinkMail(
                $employee,
                $magicLink['url'],
                $magicLink['link']->expires_at
            ));
        }

        return redirect()->back(302, [], route('admin.employees.index'))
            ->with('success', 'Link portal employee berhasil dikirim ke '.$employees->count().' karyawan.');
    }

    public function sendPortalLinkWhatsApp($id, EmployeePortalMagicLinkService $magicLinks, WhatsAppGatewayService $whatsApp)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)->findOrFail($id);

        if (! $employee->is_active) {
            return redirect()->back(302, [], route('admin.employees.index'))
                ->with('error', 'Link portal hanya bisa dikirim ke karyawan aktif.');
        }

        if (! $employee->phone) {
            return redirect()->back(302, [], route('admin.employees.index'))
                ->with('error', 'Karyawan ini belum memiliki nomor WhatsApp.');
        }

        $magicLink = $magicLinks->create($employee);
        $phone = $whatsApp->normalizePhone($employee->phone);

        $response = $whatsApp->sendText($employee->phone, $this->portalWhatsAppMessage($employee, $magicLink['url']));

        if (! $response->successful()) {
            return redirect()->back(302, [], route('admin.employees.index'))
                ->with('error', 'Gagal mengirim link portal via WhatsApp ke '.$phone.'.');
        }

        return redirect()->back(302, [], route('admin.employees.index'))
            ->with('success', 'Link portal employee berhasil dikirim via WhatsApp ke '.$phone.'.');
    }

    public function sendPortalLinkWhatsAppToAll(EmployeePortalMagicLinkService $magicLinks, WhatsAppGatewayService $whatsApp)
    {
        $admin = Employee::find(session('admin_id'));
        $employees = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('role', 'employee')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('full_name')
            ->get();

        if ($employees->isEmpty()) {
            return redirect()->back(302, [], route('admin.employees.index'))
                ->with('error', 'Tidak ada karyawan aktif dengan nomor WhatsApp untuk dikirimi link portal.');
        }

        $sent = 0;
        foreach ($employees as $employee) {
            $magicLink = $magicLinks->create($employee);
            $response = $whatsApp->sendText($employee->phone, $this->portalWhatsAppMessage($employee, $magicLink['url']));

            if ($response->successful()) {
                $sent++;
            }
        }

        if ($sent === 0) {
            return redirect()->back(302, [], route('admin.employees.index'))
                ->with('error', 'Gagal mengirim link portal via WhatsApp.');
        }

        return redirect()->back(302, [], route('admin.employees.index'))
            ->with('success', 'Link portal employee berhasil dikirim via WhatsApp ke '.$sent.' karyawan.');
    }

    public function update(Request $request, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)->findOrFail($id);

        $request->validate([
            'employee_code' => 'required|unique:employees,employee_code,'.$id,
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email,'.$id,
            'department_id' => 'required|exists:departments,id',
            'employment_status' => 'required|in:permanent,contract,intern,probation,outsourcing',
            'join_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'internship_institution' => 'nullable|string|max:255',
            'internship_supervisor' => 'nullable|string|max:255',
            'internship_field_supervisor' => 'nullable|string|max:255',
            'internship_notes' => 'nullable|string|max:1000',
            'role' => 'required|in:superadmin,hr_admin,payroll_admin,finance_admin,manager,employee',
            'photo' => 'nullable|image|max:2048',
            'signature' => 'nullable|image|max:2048',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'blood_type' => 'nullable|in:A,B,AB,O',
            'religion' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:10',
            'ktp_address' => 'nullable|string',
            'residential_address' => 'nullable|string',
        ]);

        $data = $request->except(['password', 'photo', 'remove_photo', 'signature', 'remove_signature']);
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        // Handle photo
        if ($request->boolean('remove_photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = null;
        } elseif ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = $request->file('photo')->store('employees/photos', 'public');
        }

        // Handle signature
        if ($request->boolean('remove_signature')) {
            if ($employee->signature) {
                Storage::disk('public')->delete($employee->signature);
            }
            $data['signature'] = null;
        } elseif ($request->hasFile('signature')) {
            if ($employee->signature) {
                Storage::disk('public')->delete($employee->signature);
            }
            $data['signature'] = $request->file('signature')->store('employees/signatures', 'public');
        }

        $employee->update($data);
        $this->syncPrimaryRole($employee, $request->input('role'));

        return redirect()->route('admin.employees.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }

    private function syncPrimaryRole(Employee $employee, string $roleSlug): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('employee_roles')) {
            return;
        }

        $roleId = Role::where('slug', $roleSlug)->value('id');
        if ($roleId) {
            $employee->roles()->sync([$roleId]);
        }
    }

    private function portalWhatsAppMessage(Employee $employee, string $magicUrl): string
    {
        return "Halo {$employee->full_name},\n\n"
            ."Berikut link akses Employee Portal HRIS Beacon:\n"
            ."{$magicUrl}\n\n"
            ."Link berlaku 30 menit dan hanya bisa digunakan satu kali.\n\n"
            ."HRIS Beacon";
    }

    public function destroy($id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)->findOrFail($id);

        if (! $employee->is_active) {
            return redirect()->route('admin.employees.index')
                ->with('error', 'Karyawan ini sudah tidak aktif.');
        }

        DB::transaction(function () use ($employee, $id) {
            $employee->update(['is_active' => false]);

            EmployeePayrollComponent::where('employee_id', $id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date' => now()->toDateString(),
                ]);

            EmployeePayroll::where('employee_id', $id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        });

        return redirect()->route('admin.employees.index')
            ->with('success', 'Karyawan berhasil dinonaktifkan.');
    }

    public function resign($id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)
            ->with(['department', 'activePayroll'])->findOrFail($id);

        if (! $employee->is_active) {
            return redirect()->route('admin.employees.index')
                ->with('error', 'Karyawan ini sudah tidak aktif.');
        }

        // Calculate months worked this year for PPh21 preview
        $joinThisYear = Carbon::parse($employee->join_date ?? now()->startOfYear());
        $startOfYear = now()->startOfYear();
        $monthsWorked = (int) max(1, $startOfYear->lt($joinThisYear)
            ? $joinThisYear->diffInMonths(now()) + 1
            : now()->month);

        $pph21Preview = null;
        $payroll = $employee->activePayroll;
        if ($payroll) {
            $bpjsCalc = new BpjsCalculator(now()->format('Y-m-d'));
            $bpjs = $bpjsCalc->calculate((float) $payroll->basic_salary);

            $taxAlreadyPaid = DB::table('payroll_run_details as details')
                ->join('payroll_runs as runs', 'runs.id', '=', 'details.payroll_run_id')
                ->where('details.employee_id', $employee->id)
                ->where('runs.period', 'like', now()->year.'-%')
                ->where('runs.period', '<', now()->format('Y-m'))
                ->where('runs.status', '!=', 'draft')
                ->get('details.components')
                ->sum(function ($detail) {
                    return $this->pph21PaidFromComponents(json_decode($detail->components ?? '[]', true) ?: []);
                });

            $pph21Calc = new Pph21Calculator(now()->format('Y-m-d'));
            $pph21Preview = $pph21Calc->calculateFinalMonth(
                avgBrutoMonthly : (float) $payroll->basic_salary,
                ptkpStatus      : $payroll->ptkp_status ?? 'TK/0',
                taxMethod       : $payroll->tax_method ?? 'gross',
                bpjsEmployee    : $bpjs['employee_total'],
                monthsWorked    : $monthsWorked,
                taxAlreadyPaid  : $taxAlreadyPaid
            );
        }

        return view('admin.employees.resign', compact('employee', 'monthsWorked', 'pph21Preview'));
    }

    private function pph21PaidFromComponents(array $components): float
    {
        $pphDeduction = collect($components)
            ->filter(function (array $component) {
                $name = strtolower($component['name'] ?? '');

                return ($component['type'] ?? '') === 'deduction'
                    && str_contains($name, 'pph')
                    && str_contains($name, '21');
            })
            ->sum(fn (array $component) => (float) ($component['amount'] ?? 0));

        if ($pphDeduction != 0) {
            return (float) $pphDeduction;
        }

        return (float) collect($components)
            ->filter(function (array $component) {
                if (($component['type'] ?? '') !== 'earning') {
                    return false;
                }

                $name = strtolower($component['name'] ?? '');

                return str_contains($name, 'tax allowance')
                    || str_contains($name, 'tunjangan pajak');
            })
            ->sum(fn (array $component) => max((float) ($component['amount'] ?? 0), 0));
    }

    public function processResign(Request $request, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)
            ->with(['activePayroll', 'payrollComponents'])->findOrFail($id);

        $request->validate([
            'resign_date' => 'required|date',
            'last_working_date' => 'required|date|after_or_equal:resign_date',
            'resign_reason' => 'required|in:voluntary,termination,contract_end,retirement,passed_away',
            'resign_notes' => 'nullable|string|max:1000',
        ]);

        // 1. Update employee status
        $employee->update([
            'is_active' => false,
            'resign_date' => $request->resign_date,
            'last_working_date' => $request->last_working_date,
            'resign_reason' => $request->resign_reason,
            'resign_notes' => $request->resign_notes,
        ]);

        // 2. Deactivate all payroll component assignments
        EmployeePayrollComponent::where('employee_id', $id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'end_date' => $request->last_working_date,
            ]);

        // 3. Deactivate active EmployeePayroll
        EmployeePayroll::where('employee_id', $id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return redirect()->route('admin.employees.show', $id)
            ->with('success', 'Proses resign berhasil dicatat. Karyawan telah dinonaktifkan.');
    }

    private function employeeListQuery(Request $request, Employee $admin)
    {
        $query = Employee::where('company_id', $admin->company_id);

        if ($request->department_id) {
            $deptIds = Department::where('id', $request->department_id)
                ->orWhere('parent_id', $request->department_id)
                ->pluck('id');
            $query->whereIn('department_id', $deptIds);
        }

        if ($request->status) {
            $query->where('employment_status', $request->status);
        }

        return $query;
    }

    private function employmentStatusLabel(?string $status): string
    {
        return match ($status) {
            'permanent' => 'Tetap',
            'contract' => 'Kontrak',
            'intern' => 'Magang',
            'outsourcing' => 'Outsourcing',
            'probation' => 'Probation',
            default => '-',
        };
    }

    private function genderLabel(?string $gender): string
    {
        return match ($gender) {
            'male' => 'Laki-laki',
            'female' => 'Perempuan',
            default => '-',
        };
    }

    private function maritalStatusLabel(?string $status): string
    {
        return match ($status) {
            'single' => 'Belum Menikah',
            'married' => 'Menikah',
            'divorced' => 'Cerai',
            'widowed' => 'Cerai Mati',
            default => '-',
        };
    }

    private function dateLabel($date): string
    {
        return $date ? Carbon::parse($date)->format('d M Y') : '-';
    }

    private function workDurationLabel(Employee $employee): string
    {
        if (! $employee->join_date) {
            return '-';
        }

        $diff = Carbon::parse($employee->join_date)->diff(now());
        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y.' tahun';
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m.' bulan';
        }
        if ($diff->y === 0 && $diff->m === 0) {
            $parts[] = $diff->d.' hari';
        }

        return implode(' ', $parts);
    }

    private function contractRemainingLabel(Employee $employee): string
    {
        if (! in_array($employee->employment_status, ['contract', 'intern', 'probation', 'outsourcing'], true)
            || ! $employee->contract_end_date) {
            return '-';
        }

        $endDate = Carbon::parse($employee->contract_end_date);
        if (now()->gt($endDate)) {
            return 'Sudah habis';
        }

        $remaining = now()->diff($endDate);
        $parts = [];
        if ($remaining->y > 0) {
            $parts[] = $remaining->y.' thn';
        }
        if ($remaining->m > 0) {
            $parts[] = $remaining->m.' bln';
        }
        if ($remaining->d > 0) {
            $parts[] = $remaining->d.' hr';
        }

        return implode(' ', $parts) ?: '0 hr';
    }
}
