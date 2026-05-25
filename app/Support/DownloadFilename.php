<?php

namespace App\Support;

final class DownloadFilename
{
    public static function sanitize(string $filename): string
    {
        $filename = str_replace(['/', '\\'], '-', $filename);
        $filename = preg_replace('/[\x00-\x1F\x7F]+/', '', $filename) ?? $filename;

        return trim($filename) !== '' ? trim($filename) : 'download';
    }
}
