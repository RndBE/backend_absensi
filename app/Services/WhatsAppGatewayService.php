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

        if (! $endpoint) {
            throw new InvalidArgumentException('WhatsApp endpoint belum dikonfigurasi.');
        }

        $phone = $this->normalizePhone($phone);

        if (! $phone) {
            throw new InvalidArgumentException('Nomor WhatsApp tidak valid.');
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $apiKey = config('services.whatsapp.api_key');
        if (filled($apiKey)) {
            $headers['x-api-key'] = $apiKey;
        }

        return Http::withHeaders($headers)
            ->timeout((int) config('services.whatsapp.timeout', 15))
            ->post($endpoint, [
                'chatId' => "{$phone}@c.us",
                'contentType' => 'string',
                'content' => $message,
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
