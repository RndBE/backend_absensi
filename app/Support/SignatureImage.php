<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Menyiapkan gambar tanda tangan untuk dokumen cetak.
 *
 * Masalahnya bukan CSS: file tanda tangan yang diunggah pegawai hampir selalu
 * punya ruang kosong (transparan/putih) di sekeliling tintanya — hasil scan atau
 * screenshot. Karena CSS menskalakan seluruh kanvas, ruang kosong itu ikut
 * membesar dan tanda tangan terlihat kecil serta menggantung jauh dari label.
 *
 * Kelas ini memangkas ruang kosong itu sekali lalu menyimpan hasilnya, jadi
 * ukuran tinta konsisten untuk file unggahan apa pun.
 */
final class SignatureImage
{
    /** Piksel dianggap kosong kalau nyaris transparan atau nyaris putih. */
    private const ALPHA_EMPTY = 100;   // 0 = pekat, 127 = transparan penuh
    private const WHITE_LEVEL = 235;   // 0-255

    /** Gambar terlalu besar dilewati — pemindaian per piksel tidak sepadan. */
    private const MAX_PIXELS = 4_000_000;

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $original = asset('storage/' . $path);

        if (! function_exists('imagecreatefromstring')) {
            return $original;
        }

        $cached = 'employees/signatures/trimmed/'
            . md5($path . '|' . $disk->lastModified($path)) . '.png';

        if ($disk->exists($cached)) {
            return asset('storage/' . $cached);
        }

        $png = self::trim((string) $disk->get($path));

        if ($png === null || ! $disk->put($cached, $png)) {
            return $original;
        }

        return asset('storage/' . $cached);
    }

    /** @return string|null PNG hasil pangkas, atau null kalau tak perlu/tak bisa dipangkas. */
    private static function trim(string $data): ?string
    {
        $image = @imagecreatefromstring($data);

        if ($image === false) {
            return null;
        }

        try {
            // Gambar berpalet mengembalikan indeks warna, bukan RGBA, dari imagecolorat().
            if (! imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }

            $width = imagesx($image);
            $height = imagesy($image);

            if ($width * $height > self::MAX_PIXELS) {
                return null;
            }

            $box = self::inkBounds($image, $width, $height);

            // Seluruh kanvas kosong, atau tinta sudah mepet tepi: biarkan apa adanya.
            if ($box === null) {
                return null;
            }

            [$left, $top, $right, $bottom] = $box;

            if ($left === 0 && $top === 0 && $right === $width - 1 && $bottom === $height - 1) {
                return null;
            }

            // Sisakan napas tipis supaya goresan tepi tidak terpotong rata.
            $pad = max(1, (int) round(($bottom - $top + 1) * 0.04));
            $left = max(0, $left - $pad);
            $top = max(0, $top - $pad);
            $right = min($width - 1, $right + $pad);
            $bottom = min($height - 1, $bottom + $pad);

            $cropped = imagecrop($image, [
                'x' => $left,
                'y' => $top,
                'width' => $right - $left + 1,
                'height' => $bottom - $top + 1,
            ]);

            if ($cropped === false) {
                return null;
            }

            try {
                imagealphablending($cropped, false);
                imagesavealpha($cropped, true);

                ob_start();
                $ok = imagepng($cropped);
                $png = (string) ob_get_clean();

                return $ok ? $png : null;
            } finally {
                imagedestroy($cropped);
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Kotak terkecil yang memuat seluruh tinta.
     *
     * @return array{0:int,1:int,2:int,3:int}|null null kalau tidak ada piksel tinta
     */
    private static function inkBounds(\GdImage $image, int $width, int $height): ?array
    {
        $left = $width;
        $top = $height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);

                if ((($rgba >> 24) & 0x7F) >= self::ALPHA_EMPTY) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if ($r >= self::WHITE_LEVEL && $g >= self::WHITE_LEVEL && $b >= self::WHITE_LEVEL) {
                    continue;
                }

                if ($x < $left)   { $left = $x; }
                if ($x > $right)  { $right = $x; }
                if ($y < $top)    { $top = $y; }
                if ($y > $bottom) { $bottom = $y; }
            }
        }

        return $right < 0 ? null : [$left, $top, $right, $bottom];
    }
}
