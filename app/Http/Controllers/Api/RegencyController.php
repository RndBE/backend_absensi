<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Regency;
use App\Support\RegencyMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar kabupaten/kota untuk isian otomatis kota tujuan di app.
 */
class RegencyController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);

        // Migrasi kabupaten/kota belum dijalankan: tanpa saran, app tetap bisa diketik bebas.
        if (! Schema::hasTable('regencies')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $regencies = RegencyMatcher::search((string) $request->q)
            ->map(fn (Regency $regency) => $regency->toSummary())
            ->values();

        return response()->json(['success' => true, 'data' => $regencies]);
    }
}
