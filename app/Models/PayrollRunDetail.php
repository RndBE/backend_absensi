<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunDetail extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id',
        'basic_salary', 'total_earning', 'total_deduction', 'net_salary',
        'components', 'is_manual_edited', 'manual_overrides',
        'is_held', 'hold_reason', 'held_at', 'held_by',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'total_earning' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'components' => 'array',
            'is_manual_edited' => 'boolean',
            'manual_overrides' => 'array',
            'is_held' => 'boolean',
            'held_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'held_by');
    }

    /**
     * Slip yang boleh dilihat karyawan: run-nya sudah published/locked dan slipnya
     * tidak sedang ditahan. Dipakai bersama oleh web, API mobile, dan email slip
     * supaya ketiganya tidak pernah berbeda pendapat soal slip mana yang terbuka.
     */
    public function scopeVisibleToEmployee(Builder $query): Builder
    {
        return $query
            ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', PayrollRun::EMPLOYEE_VISIBLE_STATUSES))
            ->where('is_held', false);
    }

    public function isVisibleToEmployee(): bool
    {
        return in_array($this->payrollRun?->status, PayrollRun::EMPLOYEE_VISIBLE_STATUSES, true)
            && ! $this->is_held;
    }

    /**
     * Detail bisa diedit/di-regenerate selama run masih draft. Setelah finalize atau
     * publish, hanya slip yang ditahan yang masih boleh diubah: slip itu belum sampai
     * ke karyawan, jadi memperbaikinya tidak mengganggu slip lain di run yang sama.
     */
    public function isEditableIn(PayrollRun $run): bool
    {
        if ($run->status === 'draft') {
            return true;
        }

        return $this->is_held && in_array($run->status, PayrollRun::HOLDABLE_STATUSES, true);
    }
}
