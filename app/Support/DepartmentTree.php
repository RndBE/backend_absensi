<?php

namespace App\Support;

use App\Models\Department;

/**
 * Departemen di sistem ini berbentuk pohon (`departments.parent_id`), dan manager biasanya
 * menempel di simpul INDUK sementara anak buahnya tersebar di simpul-simpul anaknya.
 *
 * Contoh nyata: "FAT & SUPPLY CHAIN" membawahi PURCHASING, OPERATION & PROJECT, dan
 * FINANCE, ACCOUNTING AND TAX. Managernya berada di induk, tak seorang pun karyawan lain
 * ber-`department_id` sama dengannya. Perbandingan `department_id` yang persis akan membuat
 * manager itu tidak melihat siapa pun.
 */
class DepartmentTree
{
    /**
     * ID departemen $rootId beserta SELURUH keturunannya (rekursif), termasuk dirinya sendiri.
     * Satu query untuk seluruh pohon; penelusurannya di memori — jauh lebih murah daripada
     * satu query per tingkat, dan pohonnya kecil (belasan simpul).
     *
     * @return array<int, int>
     */
    public static function withDescendants(?int $rootId): array
    {
        if (! $rootId) {
            return [];
        }

        $anak = [];
        foreach (Department::whereNotNull('parent_id')->get(['id', 'parent_id']) as $dept) {
            $anak[(int) $dept->parent_id][] = (int) $dept->id;
        }

        $ids = [(int) $rootId];
        $antre = [(int) $rootId];

        while ($antre !== []) {
            $current = array_pop($antre);
            foreach ($anak[$current] ?? [] as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $antre[] = $childId;
                }
            }
        }

        return $ids;
    }
}
