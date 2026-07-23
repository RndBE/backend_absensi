<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRegulation;
use App\Models\CompanyRegulationAttachment;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyInfoController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $company = $employee->company ?: Company::find($employee->company_id);

        $regulations = CompanyRegulation::query()
            ->with('attachments')
            ->where('company_id', $employee->company_id)
            ->active()
            ->orderByDesc('effective_date')
            ->orderBy('title')
            ->get();

        return view('employee.company-info.index', [
            'company' => $company,
            'employee' => $employee,
            'regulations' => $regulations,
        ]);
    }

    public function download(Request $request, CompanyRegulation $regulation)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        abort_unless(
            $regulation->company_id === $employee->company_id
                && $regulation->is_active
                && (
                    $regulation->attachments()->exists()
                    || ($regulation->file_path && Storage::disk('local')->exists($regulation->file_path))
                ),
            404
        );

        $attachment = $regulation->attachments()->oldest()->first();

        if ($attachment) {
            return $this->downloadAttachmentFile($attachment);
        }

        return Storage::disk('local')->download($regulation->file_path, $regulation->file_name);
    }

    public function downloadAttachment(Request $request, CompanyRegulation $regulation, CompanyRegulationAttachment $attachment)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        abort_unless(
            $regulation->company_id === $employee->company_id
                && $regulation->is_active
                && $attachment->company_regulation_id === $regulation->id,
            404
        );

        return $this->downloadAttachmentFile($attachment);
    }

    private function downloadAttachmentFile(CompanyRegulationAttachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }
}
