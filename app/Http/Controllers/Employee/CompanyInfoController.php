<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRegulation;
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
                && $regulation->file_path
                && Storage::disk('local')->exists($regulation->file_path),
            404
        );

        return Storage::disk('local')->download($regulation->file_path, $regulation->file_name);
    }
}
