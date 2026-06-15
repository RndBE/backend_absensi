<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FacePhotoController extends Controller
{
    public function show(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.face-photo.show', [
            'employee' => $employee,
            'faceVerificationEnabled' => Setting::getValue('face_verification_enabled', '1') === '1',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'photo_base64' => 'required|string',
        ]);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $imageData = $this->decodeBase64Image($request->photo_base64);

        if ($imageData === null) {
            return response()->json([
                'success' => false,
                'message' => 'Foto wajah tidak valid.',
            ], 422);
        }

        if ($employee->face_photo) {
            Storage::disk('public')->delete($employee->face_photo);
        }

        $path = 'employees/face-photos/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $imageData);
        $employee->update(['face_photo' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Foto verifikasi wajah berhasil disimpan.',
            'face_photo' => asset('storage/'.$path),
        ]);
    }

    public function destroy(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        if ($employee->face_photo) {
            Storage::disk('public')->delete($employee->face_photo);
            $employee->update(['face_photo' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto verifikasi wajah dihapus.',
        ]);
    }

    private function decodeBase64Image(string $base64): ?string
    {
        $payload = trim($base64);

        if (str_contains($payload, ',')) {
            [, $payload] = explode(',', $payload, 2);
        }

        $decoded = base64_decode($payload, true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }
}
