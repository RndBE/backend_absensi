<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApprovalHistory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Riwayat keputusan pemberi persetujuan untuk app mobile (tab Riwayat di Inbox). Isi dan aturannya
 * ada di ApprovalHistory.
 */
class ApprovalHistoryController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'approved', 'rejected'])],
            'type' => ['nullable', Rule::in(ApprovalHistory::filterableTypes())],
            'period' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ApprovalHistory::MAX_PER_PAGE],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? ApprovalHistory::PER_PAGE);

        $result = ApprovalHistory::page(
            $request->user(),
            ($validated['status'] ?? 'all') === 'all' ? null : $validated['status'],
            $validated['type'] ?? null,
            $validated['period'] ?? null,
            $page,
            $perPage,
        );

        return response()->json([
            'success' => true,
            'data' => $result['rows'],
            'meta' => ['page' => $page, 'per_page' => $perPage, 'has_more' => $result['has_more']],
        ]);
    }
}
