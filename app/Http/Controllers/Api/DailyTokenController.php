<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DailyTokenController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $employee = $request->user();
        $email = $employee?->email;

        if (! $email) {
            return response()->json([
                'success' => false,
                'message' => 'Email akun absensi belum tersedia.',
            ], 422);
        }

        $baseUrl = rtrim((string) config('services.daily.url'), '/');
        $secret = (string) config('services.daily.internal_secret');

        if ($baseUrl === '' || $secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi koneksi Daily belum lengkap.',
            ], 500);
        }

        try {
            $dailyResponse = Http::acceptJson()
                ->withHeader('X-Internal-Secret', $secret)
                ->timeout(15)
                ->post($baseUrl.'/api/internal/mobile-token', [
                    'email' => $email,
                ]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat terhubung ke server Daily.',
            ], 502);
        }

        return response()->json(
            $dailyResponse->json() ?? [
                'success' => false,
                'message' => 'Respons server Daily tidak valid.',
            ],
            $dailyResponse->status()
        );
    }
}
