<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TessaScheduleImportParser
{
    private const HEADER_MAP = [
        'employee_id' => ['employee_id', 'employeeid', 'idkaryawan', 'idpegawai'],
        'employee_code' => ['employee_code', 'employeecode', 'kodekaryawan', 'kodepegawai', 'nik', 'noinduk'],
        'employee' => ['employee', 'nama', 'namakaryawan', 'namapegawai', 'karyawan', 'pegawai'],
        'date' => ['date', 'tanggal', 'tgl', 'haritanggal'],
        'shift_id' => ['shift_id', 'shiftid', 'idshift'],
        'shift' => ['shift', 'jadwal', 'namashift', 'shiftkerja'],
        'notes' => ['notes', 'note', 'catatan', 'keterangan'],
    ];

    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            throw new InvalidArgumentException('PDF belum bisa diparse otomatis. Kirim file Excel (.xlsx/.xls) atau CSV untuk import jadwal via Tessa.');
        }

        if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            throw new InvalidArgumentException('Format file jadwal harus .xlsx, .xls, atau .csv.');
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return [];
        }

        $headers = $this->headers(array_shift($rows));
        $assignments = [];

        foreach ($rows as $row) {
            $assignment = $this->assignment($row, $headers);
            if ($assignment !== []) {
                $assignments[] = $assignment;
            }
        }

        return $assignments;
    }

    private function headers(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            foreach (self::HEADER_MAP as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $headers[$column] = $field;
                    break;
                }
            }
        }

        return $headers;
    }

    private function assignment(array $row, array $headers): array
    {
        $assignment = [];

        foreach ($headers as $column => $field) {
            $value = $row[$column] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $assignment[$field] = $field === 'date'
                ? $this->dateValue($value)
                : trim((string) $value);
        }

        return array_filter($assignment, fn ($value) => $value !== null && $value !== '');
    }

    private function dateValue(mixed $value): ?string
    {
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return trim((string) $value);
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?: '';
    }
}
