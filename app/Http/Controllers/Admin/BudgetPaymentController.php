<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetPayment;
use App\Models\BudgetRequest;
use App\Models\Employee;
use Illuminate\Http\Request;

class BudgetPaymentController extends Controller
{
    /**
     * Store a payment for the given budget request.
     */
    public function store(Request $request, $budgetRequestId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:transfer,cash,check',
            'reference_no' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $admin = Employee::find(session('admin_id'));
        $budgetRequest = BudgetRequest::findOrFail($budgetRequestId);

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')->store('payment-proofs', 'public');
        }

        $payment = BudgetPayment::create([
            'budget_request_id' => $budgetRequest->id,
            'processed_by' => $admin->id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'reference_no' => $request->reference_no,
            'payment_proof' => $proofPath,
            'notes' => $request->notes,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Update budget request status to 'paid'
        $budgetRequest->update(['status' => 'paid']);

        return redirect()->route('admin.budget-requests.show', $budgetRequestId)
            ->with('success', 'Pembayaran berhasil diproses.');
    }

    /**
     * Delete a payment.
     */
    public function destroy($budgetRequestId, $paymentId)
    {
        $admin = Employee::find(session('admin_id'));
        if ($admin->role !== 'superadmin') {
            return back()->with('error', 'Hanya superadmin yang dapat menghapus pembayaran.');
        }

        $payment = BudgetPayment::where('budget_request_id', $budgetRequestId)->findOrFail($paymentId);

        // Revert status if this was the only payment
        $remaining = BudgetPayment::where('budget_request_id', $budgetRequestId)
            ->where('id', '!=', $paymentId)->count();

        if ($remaining === 0) {
            BudgetRequest::where('id', $budgetRequestId)->update(['status' => 'approved']);
        }

        if ($payment->payment_proof) {
            \Storage::disk('public')->delete($payment->payment_proof);
        }
        $payment->delete();

        return redirect()->route('admin.budget-requests.show', $budgetRequestId)
            ->with('success', 'Pembayaran berhasil dihapus.');
    }
}
