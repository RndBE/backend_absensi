<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Field yang boleh diajukan perubahannya oleh karyawan.
     * Harus sama dengan daftar field yang di-apply admin saat approve
     * (lihat Admin\ApprovalController::onFinalApproval).
     */
    public const CHANGEABLE_FIELDS = [
        'full_name' => 'Nama Lengkap',
        'email' => 'Email',
        'phone' => 'No. Telepon',
        'nik' => 'NIK',
        'religion' => 'Agama',
        'marital_status' => 'Status Pernikahan',
        'blood_type' => 'Golongan Darah',
        'postal_code' => 'Kode Pos',
        'ktp_address' => 'Alamat KTP',
        'residential_address' => 'Alamat Domisili',
    ];

    public function show(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.profile.show', [
            'employee' => $employee,
        ]);
    }

    public function updatePhoto(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ], [], [
            'photo' => 'foto profil',
        ]);

        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
        }

        $path = $request->file('photo')->store('employees/photos', 'public');
        $employee->update(['photo' => $path]);

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function destroyPhoto(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
            $employee->update(['photo' => null]);
        }

        return back()->with('success', 'Foto profil berhasil dihapus.');
    }

    public function personal(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.profile.personal', [
            'employee' => $employee,
        ]);
    }

    public function employment(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.profile.employment', [
            'employee' => $employee,
        ]);
    }

    public function editPassword(Request $request)
    {
        return view('employee.profile.password');
    }

    public function updatePassword(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'current_password' => 'password saat ini',
            'new_password' => 'password baru',
        ]);

        if (! $employee->password || ! Hash::check($validated['current_password'], $employee->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $employee->update(['password' => $validated['new_password']]);

        return back()->with('success', 'Password berhasil diperbarui.');
    }

    public function dataChange(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $requests = DataChangeRequest::where('employee_id', $employee->id)
            ->with('attachments')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('employee.profile.data-change', [
            'employee' => $employee,
            'requests' => $requests,
            'fields' => self::CHANGEABLE_FIELDS,
        ]);
    }

    public function storeDataChange(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $validated = $request->validate([
            'field_name' => ['required', 'string', Rule::in(array_keys(self::CHANGEABLE_FIELDS))],
            'new_value' => ['required', 'string', 'max:255'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
        ], [], [
            'field_name' => 'data yang diubah',
            'new_value' => 'nilai baru',
        ]);

        $changeRequest = DataChangeRequest::create([
            'employee_id' => $employee->id,
            'field_name' => $validated['field_name'],
            'old_value' => (string) ($employee->{$validated['field_name']} ?? ''),
            'new_value' => $validated['new_value'],
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/data-changes', 'public');
                $changeRequest->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        $fieldLabel = self::CHANGEABLE_FIELDS[$validated['field_name']];
        $superadmins = Employee::where('company_id', $employee->company_id)
            ->where('role', 'superadmin')
            ->where('is_active', true)
            ->get();

        foreach ($superadmins as $superadmin) {
            Notification::create([
                'employee_id' => $superadmin->id,
                'title' => 'Pengajuan Perubahan Data',
                'message' => "{$employee->full_name} mengajukan perubahan data {$fieldLabel}",
                'type' => 'approval',
                'reference_type' => DataChangeRequest::class,
                'reference_id' => $changeRequest->id,
            ]);
        }

        return redirect()->route('employee.profile.data-change')
            ->with('success', 'Pengajuan perubahan data berhasil dikirim. Menunggu persetujuan admin.');
    }
}
