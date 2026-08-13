<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Siapa yang dianggap mengetahui sebuah rantai kerja, diturunkan dari garis `manager_id`.
 *
 * Tidak disimpan di basis data. "Atasan mengetahui koordinasi anak buahnya" bukan hubungan antar
 * dua orang melainkan akibat wajar dari struktur organisasi, jadi mencatatnya per pasangan hanya
 * menduplikasi `manager_id` dalam bentuk yang bisa basi dan pasti bolong — lihat migration
 * 2026_08_12_000021 soal kerusakan yang ditimbulkannya.
 *
 * Dua keputusan yang membentuk hasilnya:
 *
 * 1. **Berhenti di Manajer (L2), tidak sampai Direksi.** Direktur secara harfiah mengetahui semua
 *    hal; menampilkannya di setiap rantai tidak memberi keterangan apa pun. L1 juga tidak masuk
 *    penilaian KPI (Bab 2.1), jadi kehadirannya di peta ini tidak punya konsekuensi.
 * 2. **Peserta langsung tidak diulang sebagai pengawas.** Rhomadoni yang menerima bahan produksi
 *    sekaligus atasan Anisa cukup tampil sekali, di tabel pasangan — mengulangnya di daftar
 *    "diketahui" membuat pembaca ragu apakah itu dua peran berbeda.
 */
class KpiWorkChainOverseers
{
    /** Pengaman rantai `manager_id` yang menunjuk berputar; lebih dalam dari ini tidak wajar. */
    private const MAX_DEPTH = 5;

    /**
     * @param  Collection<int, \App\Models\KpiWorkRelation>  $pairs  seluruh pasangan satu rantai
     * @return Collection<int, Employee>  atasan yang dianggap mengetahui, diurutkan Leader lebih dulu
     */
    public function for(Collection $pairs): Collection
    {
        $participants = $pairs
            ->flatMap(fn ($pair) => [$pair->from, $pair->to])
            ->filter()
            ->unique('id');

        $participantIds = $participants->pluck('id')->all();

        return $participants
            ->flatMap(fn (Employee $person) => $this->superiorsOf($person))
            ->unique('id')
            ->reject(fn (Employee $superior) => in_array($superior->id, $participantIds, true))
            // Leader lebih dulu: dia yang paling dekat dengan pekerjaannya, jadi paling berguna
            // dibaca lebih awal saat meninjau rantai.
            ->sortBy([
                fn (Employee $a, Employee $b) => $this->levelRank($a) <=> $this->levelRank($b),
                fn (Employee $a, Employee $b) => $a->full_name <=> $b->full_name,
            ])
            ->values();
    }

    /**
     * Atasan seseorang naik sampai Manajer (L2). L1 memotong penelusuran tanpa ikut masuk daftar.
     *
     * @return array<int, Employee>
     */
    private function superiorsOf(Employee $person): array
    {
        $found = [];
        $current = $person;

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $manager = $current->manager;

            if (! $manager || $manager->id === $current->id) {
                break;
            }

            $code = $manager->kpiLevel?->code;

            if ($code === 'L1') {
                break; // Direksi tahu segalanya — menampilkannya tidak menerangkan apa pun
            }

            $found[] = $manager;
            $current = $manager;

            if ($code === 'L2') {
                break; // Manajer adalah batas atas yang berarti
            }
        }

        return $found;
    }

    private function levelRank(Employee $employee): int
    {
        return match ($employee->kpiLevel?->code) {
            'L3' => 0,
            'L2' => 1,
            default => 2,
        };
    }
}
