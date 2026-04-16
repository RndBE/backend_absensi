<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FcmService
{
    /**
     * Send push notification to a specific employee via FCM.
     */
    public static function sendToEmployee(Employee $employee, string $title, string $body, array $data = []): bool
    {
        if (empty($employee->fcm_token)) {
            return false;
        }

        return self::send($employee->fcm_token, $title, $body, $data);
    }

    /**
     * Send push notification via FCM HTTP v1 API.
     */
    public static function send(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        try {
            $accessToken = self::getAccessToken();
            if (!$accessToken) {
                Log::warning('FCM: Could not obtain access token');
                return false;
            }

            $credentials = self::getCredentials();
            $projectId = $credentials['project_id'] ?? null;
            if (!$projectId) {
                Log::warning('FCM: project_id not found in credentials');
                return false;
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $message = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $message);

            if ($response->failed()) {
                Log::error('FCM send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('FCM send exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get OAuth2 access token using service account credentials.
     */
    private static function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3500, function () {
            try {
                $credentials = self::getCredentials();
                if (!$credentials) {
                    return null;
                }

                $now = time();
                $header = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $payload = self::base64url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));

                $signatureInput = "{$header}.{$payload}";
                $privateKey = openssl_pkey_get_private($credentials['private_key']);
                if (!$privateKey) {
                    Log::error('FCM: Invalid private key');
                    return null;
                }

                openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                $jwt = "{$signatureInput}." . self::base64url($signature);

                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('FCM: Token exchange failed', ['body' => $response->body()]);
                return null;
            } catch (\Exception $e) {
                Log::error('FCM: getAccessToken exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    private static function getCredentials(): ?array
    {
        $path = env('FIREBASE_CREDENTIALS');
        if (!$path) {
            return null;
        }

        $fullPath = base_path($path);
        if (!file_exists($fullPath)) {
            Log::error('FCM: Credentials file not found', ['path' => $fullPath]);
            return null;
        }

        return json_decode(file_get_contents($fullPath), true);
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
