<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataChangeRequest;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function uploadFacePhoto(Request $request)
    {
        $request->validate([
            'photo'        => 'nullable|image|max:5120',
            'photo_base64' => 'nullable|string',
        ]);

        $employee = $request->user();

        if (!$request->hasFile('photo') && !$request->photo_base64) {
            return response()->json(['success' => false, 'message' => 'Foto wajah harus diisi.'], 422);
        }

        // Hapus foto lama
        if ($employee->face_photo) {
            Storage::disk('public')->delete($employee->face_photo);
        }

        $path = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('employees/face-photos', 'public');
        } elseif ($request->photo_base64) {
            $imageData = base64_decode($request->photo_base64);
            $fileName  = 'employees/face-photos/' . uniqid() . '.jpg';
            Storage::disk('public')->put($fileName, $imageData);
            $path = $fileName;
        }

        $employee->update(['face_photo' => $path]);

        return response()->json([
            'success'    => true,
            'message'    => 'Foto verifikasi wajah berhasil disimpan.',
            'face_photo' => $path ? asset('storage/' . $path) : null,
        ]);
    }

    public function deleteFacePhoto(Request $request)
    {
        $employee = $request->user();

        if ($employee->face_photo) {
            Storage::disk('public')->delete($employee->face_photo);
            $employee->update(['face_photo' => null]);
        }

        return response()->json(['success' => true, 'message' => 'Foto verifikasi wajah dihapus.']);
    }

    public function index(Request $request)
    {
        $employee = $request->user()->load(['department', 'company', 'workSchedule', 'manager', 'approver']);

        return response()->json([
            'success' => true,
            'data' => [
                'personal' => [
                    'full_name' => $employee->full_name,
                    'phone' => $employee->phone,
                    'email' => $employee->email,
                    'birth_place' => $employee->birth_place,
                    'birth_date' => $employee->birth_date?->format('Y-m-d'),
                    'gender' => $employee->gender,
                    'marital_status' => $employee->marital_status,
                    'blood_type' => $employee->blood_type,
                    'religion' => $employee->religion,
                    'nik' => $employee->nik,
                    'postal_code' => $employee->postal_code,
                    'ktp_address' => $employee->ktp_address,
                    'residential_address' => $employee->residential_address,
                ],
                'face_photo'  => $employee->face_photo ? asset('storage/' . $employee->face_photo) : null,
                'has_face_photo' => (bool) $employee->face_photo,
                'employment' => [
                    'employee_code' => $employee->employee_code,
                    'company' => $employee->company?->name,
                    'department' => $employee->department?->name,
                    'position' => $employee->position,
                    'job_level' => $employee->job_level,
                    'employment_status' => $employee->employment_status,
                    'join_date' => $employee->join_date?->format('Y-m-d'),
                    'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
                    'masa_kerja' => $employee->masa_kerja,
                    'approver' => $employee->approver ? [
                        'employee_code' => $employee->approver->employee_code,
                        'full_name' => $employee->approver->full_name,
                    ] : null,
                    'manager' => $employee->manager ? [
                        'employee_code' => $employee->manager->employee_code,
                        'full_name' => $employee->manager->full_name,
                    ] : null,
                ],
            ],
        ]);
    }

    public function personal(Request $request)
    {
        $employee = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'full_name' => $employee->full_name,
                'phone' => $employee->phone,
                'email' => $employee->email,
                'birth_place' => $employee->birth_place,
                'birth_date' => $employee->birth_date?->format('Y-m-d'),
                'gender' => $employee->gender,
                'marital_status' => $employee->marital_status,
                'blood_type' => $employee->blood_type,
                'religion' => $employee->religion,
                'nik' => $employee->nik,
                'postal_code' => $employee->postal_code,
                'ktp_address' => $employee->ktp_address,
                'residential_address' => $employee->residential_address,
            ],
        ]);
    }

    public function employment(Request $request)
    {
        $employee = $request->user()->load(['department', 'company', 'manager', 'approver']);

        return response()->json([
            'success' => true,
            'data' => [
                'employee_code' => $employee->employee_code,
                'company' => $employee->company?->name,
                'department' => $employee->department?->name,
                'position' => $employee->position,
                'job_level' => $employee->job_level,
                'employment_status' => $employee->employment_status,
                'join_date' => $employee->join_date?->format('Y-m-d'),
                'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
                'masa_kerja' => $employee->masa_kerja,
                'approver' => $employee->approver ? "{$employee->approver->employee_code} - {$employee->approver->full_name}" : null,
                'manager' => $employee->manager ? "{$employee->manager->employee_code} - {$employee->manager->full_name}" : null,
            ],
        ]);
    }

    public function requestDataChange(Request $request)
    {
        $request->validate([
            'field_name' => 'required|string',
            'new_value' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        $employee = $request->user();
        $oldValue = $employee->{$request->field_name} ?? '';

        $changeRequest = DataChangeRequest::create([
            'employee_id' => $employee->id,
            'field_name' => $request->field_name,
            'old_value' => $oldValue,
            'new_value' => $request->new_value,
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

        // Notify superadmin(s) instead of approver chain
        $superadmins = \App\Models\Employee::where('role', 'superadmin')
            ->where('is_active', true)
            ->get();

        foreach ($superadmins as $superadmin) {
            Notification::create([
                'employee_id' => $superadmin->id,
                'title' => 'Pengajuan Perubahan Data',
                'message' => "{$employee->full_name} mengajukan perubahan data {$request->field_name}",
                'type' => 'approval',
                'reference_type' => DataChangeRequest::class,
                'reference_id' => $changeRequest->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan perubahan data berhasil',
            'data' => $changeRequest->load('attachments'),
        ], 201);
    }

    public function dataChangeRequests(Request $request)
    {
        $requests = DataChangeRequest::where('employee_id', $request->user()->id)
            ->with('attachments')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);

        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['success' => true, 'message' => 'FCM token updated']);
    }
}
