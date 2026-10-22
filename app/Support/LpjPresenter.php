<?php

namespace App\Support;

use App\Models\Lpj;
use App\Models\LpjItem;

/**
 * Bentuk JSON LPJ untuk app mobile, dipakai bersama oleh daftar/detail milik pemohon
 * (Api\LpjController) dan detail untuk approver (Api\ApprovalController). Angka uang dikirim
 * sebagai angka, bukan teks desimal, supaya app tidak perlu menebak bentuknya.
 */
class LpjPresenter
{
    public const PROOF_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Relasi yang harus dimuat sebelum memanggil detail(). */
    public const DETAIL_RELATIONS = [
        'budgetRequest:id,title,total_amount,surat_tugas_no,surat_tugas_date',
        'travelReport:id,destination_city',
        'items',
        'approvalLogs.approver:id,full_name,photo',
    ];

    /** Baris ringkas satu LPJ, dipakai di daftar dan sebagai kepala detail. */
    public static function summary(Lpj $lpj): array
    {
        return [
            'id'               => $lpj->id,
            'nomor_lpj'        => $lpj->nomor_lpj,
            'status'           => $lpj->status,
            'rejection_reason' => $lpj->rejection_reason,
            'total_anggaran'   => (float) $lpj->total_anggaran,
            'total_realisasi'  => (float) $lpj->total_realisasi,
            'sisa'             => (float) $lpj->sisa,
            'created_at'       => $lpj->created_at?->toIso8601String(),
            'budget_request'   => $lpj->budgetRequest ? [
                'id'           => $lpj->budgetRequest->id,
                'title'        => $lpj->budgetRequest->title,
                'total_amount' => (float) $lpj->budgetRequest->total_amount,
            ] : null,
            'travel_report'    => $lpj->travelReport ? [
                'id'               => $lpj->travelReport->id,
                'destination_city' => $lpj->travelReport->destination_city,
            ] : null,
        ];
    }

    public static function item(LpjItem $item): array
    {
        $extension = strtolower(pathinfo((string) $item->bukti_file, PATHINFO_EXTENSION));

        return [
            'id'             => $item->id,
            'uraian'         => $item->uraian,
            'kategori'       => $item->kategori,
            'kategori_label' => $item->kategori_label,
            'satuan'         => $item->satuan,
            'volume'         => (float) $item->volume,
            'realisasi'      => (float) $item->realisasi,
            'keterangan'     => $item->keterangan,
            'bukti_url'      => $item->bukti_file ? asset('storage/'.$item->bukti_file) : null,
            'bukti_is_image' => $item->bukti_file ? in_array($extension, self::PROOF_IMAGE_EXTENSIONS, true) : false,
        ];
    }

    /** Detail lengkap satu LPJ. Relasi di DETAIL_RELATIONS harus sudah dimuat. */
    public static function detail(Lpj $lpj): array
    {
        $report = LpjSummary::for($lpj);

        return [
            ...self::summary($lpj),
            'catatan' => $lpj->catatan,
            // Tanggal murni (tanpa jam dan zona), diisi finance saat menyetujui.
            'transfer_date' => $lpj->transfer_date?->toDateString(),
            'budget_request' => [
                'id' => $lpj->budgetRequest?->id,
                'title' => $lpj->budgetRequest?->title,
                'total_amount' => (float) $lpj->budgetRequest?->total_amount,
                'surat_tugas_no' => $lpj->budgetRequest?->surat_tugas_no,
            ],
            'items' => $lpj->items->map(fn (LpjItem $item) => self::item($item))->values(),
            'summary' => [
                'pemasukan' => $report['pemasukan'],
                'total_pemasukan' => $report['total_pemasukan'],
                'total_pengeluaran' => $report['total_pengeluaran'],
                'per_kategori' => $report['per_kategori'],
                'total_over_budget' => $report['total_over_budget'],
                'saldo' => $report['saldo'],
            ],
            'approval_logs' => $lpj->approvalLogs->map(fn ($log) => [
                'action' => $log->action,
                'step_order' => $log->step_order,
                'notes' => $log->notes,
                'created_at' => $log->created_at?->toIso8601String(),
                'approver' => ['full_name' => $log->approver?->full_name],
            ])->values(),
        ];
    }
}
