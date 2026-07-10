<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class WhatsAppGatewayService
{
    public function sendText(string $phone, string $message): Response
    {
        $endpoint = config('services.whatsapp.endpoint');
        $username = config('services.whatsapp.username');
        $password = config('services.whatsapp.password');
        $deviceId = config('services.whatsapp.device_id');

        if (! $endpoint) {
            throw new InvalidArgumentException('WhatsApp endpoint belum dikonfigurasi.');
        }

        if (! $username || ! $password) {
            throw new InvalidArgumentException('WhatsApp basic auth belum dikonfigurasi.');
        }

        if (! $deviceId) {
            throw new InvalidArgumentException('WhatsApp device ID belum dikonfigurasi.');
        }

        $phone = $this->normalizePhone($phone);

        if (! $phone) {
            throw new InvalidArgumentException('Nomor WhatsApp tidak valid.');
        }

        return Http::asJson()
            ->withBasicAuth($username, $password)
            ->withHeaders([
                'X-Device-Id' => $deviceId,
            ])
            ->timeout((int) config('services.whatsapp.timeout', 15))
            ->post($endpoint, [
                'phone' => "{$phone}@s.whatsapp.net",
                'message' => $message,
            ]);
    }

    public function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        if (str_starts_with($phone, '8')) {
            return '62'.$phone;
        }

        return $phone;
    }
}
