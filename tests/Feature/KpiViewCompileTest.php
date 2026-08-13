<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Modul KPI punya belasan view. Kesalahan direktif Blade (@if tanpa @endif, tanda kurung
 * berlebih pada @checked) baru muncul saat halaman dibuka — terlalu telat kalau baru
 * ketahuan di produksi. Test ini mengompilasi seluruhnya tanpa perlu merender data.
 */
class KpiViewCompileTest extends TestCase
{
    public function test_every_kpi_view_compiles(): void
    {
        $files = $this->viewFiles();

        $this->assertGreaterThan(10, count($files), 'View KPI tidak ditemukan — jalur pencarian salah?');

        foreach ($files as $file) {
            $relative = str_replace(base_path().'/', '', $file);

            try {
                $compiled = Blade::compileString(file_get_contents($file));
            } catch (\Throwable $e) {
                $this->fail("Gagal mengompilasi {$relative}: {$e->getMessage()}");
            }

            // Blade menerjemahkan direktif menjadi PHP; kalau hasilnya tidak sah, view
            // tetap meledak saat dirender. Diperiksa dengan php -l pada hasil kompilasi.
            $this->assertPhpSyntaxIsValid($compiled, $relative);
        }
    }

    /** @return array<int, string> */
    private function viewFiles(): array
    {
        $directory = resource_path('views/admin/kpi');

        if (! is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')
                && ! str_starts_with($file->getFilename(), '._')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function assertPhpSyntaxIsValid(string $code, string $label): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'blade');
        file_put_contents($temp, $code);

        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1', $output, $exitCode);
        unlink($temp);

        $this->assertSame(0, $exitCode, "PHP hasil kompilasi {$label} tidak sah:\n".implode("\n", $output));
    }
}
