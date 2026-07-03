<?php

namespace App\Http\Controllers\Api\Tessa;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Sesi Tessa: menukar identitas karyawan HRIS menjadi token per-user.
 *
 * Dua cara kenali penanya (keduanya dijaga service key TESSA_API_KEY — hanya server
 * Tessa yang boleh menukar identitas jadi token):
 *  - by phone   : { "phone": "+62812..." }  → cocokkan ke employees.phone (untuk bot WhatsApp).
 *  - credential : { "email", "password" }    → verifikasi kata sandi.
 *
 * Token terikat ke akun karyawan, jadi aksi selanjutnya PERSIS mengikuti role HRIS-nya
 * (tidak bisa naik pangkat).
 */
class TessaSessionController extends Controller
{
    public function login(Request $request)
    {
        // Nomor HP (tanpa password) → alur by-phone; selain itu → alur kredensial.
        if (filled($request->input('phone')) && ! filled($request->input('password'))) {
            return $this->loginByPhone($request);
        }

        return $this->loginByCredential($request);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Token Tessa dicabut.']);
    }

    // =====================================================================
    // Alur login
    // =====================================================================

    private function loginByPhone(Request $request)
    {
        $request->validate(['phone' => 'required|string|max:30']);

        $canonical = $this->normalizePhone((string) $request->input('phone'));
        if (! $canonical) {
            return response()->json(['success' => false, 'message' => 'Nomor HP tidak valid.'], 422);
        }

        $result = $this->resolveByPhone($canonical, $this->companyScope());
        if (is_string($result)) {
            return response()->json(['success' => false, 'message' => $result], 409);
        }
        if (! $result) {
            return response()->json(['success' => false, 'message' => 'Nomor ini belum terdaftar sebagai karyawan.'], 404);
        }
        if (! $result->is_active) {
            return response()->json(['success' => false, 'message' => 'Akun karyawan tidak aktif.'], 403);
        }

        return $this->issueToken($result);
    }

    private function loginByCredential(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $employee = Employee::where('email', $request->input('email'))->first();

        if (! $employee || ! $employee->password || ! Hash::check($request->input('password'), $employee->password)) {
            return response()->json(['success' => false, 'message' => 'Email atau kata sandi salah.'], 401);
        }
        if (! $employee->is_active) {
            return response()->json(['success' => false, 'message' => 'Akun karyawan tidak aktif.'], 403);
        }

        $scope = $this->companyScope();
        if ($scope && (int) $employee->company_id !== $scope) {
            return response()->json(['success' => false, 'message' => 'Akun di luar cakupan perusahaan Tessa.'], 403);
        }

        return $this->issueToken($employee);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /** Terbitkan token Tessa (satu aktif per karyawan) + info role. */
    private function issueToken(Employee $employee)
    {
        $employee->tokens()->where('name', 'tessa')->delete();
        $token = $employee->createToken('tessa', ['tessa'])->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'role' => $employee->role,
                'company_id' => $employee->company_id,
            ],
            // Penentu akses: admin (boleh fitur admin) vs employee (hanya self-service).
            'is_admin' => app(AdminPermission::class)->isAdminUser($employee),
        ]);
    }

    /** Batas perusahaan Tessa (TESSA_COMPANY_ID), atau null bila tak dibatasi. */
    private function companyScope(): ?int
    {
        $scope = config('services.tessa.company_id');

        return $scope ? (int) $scope : null;
    }

    /**
     * Normalkan nomor HP ke format kanonik digit berkode negara (mis. "628123456789").
     * Menangani "0812…", "+62812…", "62812…", dan pemisah spasi/tanda hubung.
     */
    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw); // buang semua non-digit
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);       // 0812… → 62812…
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;                  // 812… → 62812…
        }

        return strlen($digits) >= 9 ? $digits : null; // tolak yang terlalu pendek
    }

    /**
     * Cari karyawan berdasarkan nomor kanonik. Bandingkan versi ternormalkan (tahan
     * beda format di DB). Kembalikan Employee, null (tak ada), atau string (ganda → error).
     */
    private function resolveByPhone(string $canonical, ?int $scope): Employee|string|null
    {
        $tail = substr($canonical, -8); // 8 digit terakhir untuk mempersempit kandidat

        $candidates = Employee::query()
            ->whereNotNull('phone')
            ->when($scope, fn ($q) => $q->where('company_id', $scope))
            ->where('phone', 'like', '%'.$tail.'%')
            ->get();

        $matches = $candidates
            ->filter(fn (Employee $e) => $this->normalizePhone((string) $e->phone) === $canonical)
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }
        if ($matches->count() > 1) {
            return 'Nomor ini terdaftar di lebih dari satu karyawan; hubungi admin HRIS.';
        }

        return $matches->first();
    }
}
