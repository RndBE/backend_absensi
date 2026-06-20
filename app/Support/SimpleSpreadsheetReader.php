<?php

namespace App\Support;

use ZipArchive;

class SimpleSpreadsheetReader
{
    public static function rows(string $path, string $preferredSheet = 'Sheet1', ?string $extension = null): array
    {
        $extension = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'txt'], true)) {
            return self::csvRows($path);
        }

        return self::xlsxRows($path, $preferredSheet);
    }

    private static function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($rows === [] && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private static function xlsxRows(string $path, string $preferredSheet): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = self::readSharedStrings($zip);
        $sheetXml = $zip->getFromName(self::worksheetPath($zip, $preferredSheet));
        $zip->close();

        if (! $sheetXml) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $xmlRow) {
            $row = [];
            $maxColumnIndex = -1;

            foreach ($xmlRow->c as $cell) {
                $attributes = $cell->attributes();
                $columnIndex = self::columnIndex((string) ($attributes['r'] ?? ''));
                $maxColumnIndex = max($maxColumnIndex, $columnIndex);
                $type = (string) ($attributes['t'] ?? '');
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $row[$columnIndex] = $value;
            }

            if ($row !== []) {
                ksort($row);
                $denseRow = [];
                for ($index = 0; $index <= $maxColumnIndex; $index++) {
                    $denseRow[] = $row[$index] ?? '';
                }
                $rows[] = $denseRow;
            }
        }

        return $rows;
    }

    private static function worksheetPath(ZipArchive $zip, string $preferredSheet): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (! $workbookXml || ! $relsXml) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $relations = simplexml_load_string($relsXml);
        $targets = [];

        foreach ($relations->Relationship ?? [] as $relation) {
            $attributes = $relation->attributes();
            $targets[(string) $attributes['Id']] = (string) $attributes['Target'];
        }

        $fallbackPath = null;
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $attributes = $sheet->attributes();
            $relationAttributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $path = self::normalizeWorksheetPath($targets[(string) ($relationAttributes['id'] ?? '')] ?? '');

            $fallbackPath ??= $path;
            if (strcasecmp((string) ($attributes['name'] ?? ''), $preferredSheet) === 0) {
                return $path;
            }
        }

        return $fallbackPath ?: 'xl/worksheets/sheet1.xml';
    }

    private static function normalizeWorksheetPath(string $target): string
    {
        if ($target === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        return 'xl/'.ltrim($target, '/');
    }

    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! $xml) {
            return [];
        }

        $strings = [];
        $shared = simplexml_load_string($xml);

        foreach ($shared->si ?? [] as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r ?? [] as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private static function columnIndex(string $cellRef): int
    {
        preg_match('/^[A-Z]+/', $cellRef, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
