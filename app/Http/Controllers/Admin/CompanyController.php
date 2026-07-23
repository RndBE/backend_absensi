<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRegulation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index()
    {
        $companyId = $this->companyId();
        $company = Company::whereKey($companyId)->first() ?: Company::first();
        $regulations = CompanyRegulation::query()
            ->with('attachments')
            ->where('company_id', $company?->id ?: $companyId)
            ->orderByDesc('effective_date')
            ->orderBy('title')
            ->get();

        return view('admin.company.index', compact('company', 'regulations'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:30',
            'logo' => 'nullable|image|max:2048',
        ]);

        $company = Company::whereKey($this->companyId())->first() ?: Company::first();

        if (!$company) {
            $company = new Company();
            $company->id = $this->companyId();
        }

        $company->name = $request->name;
        $company->address = $request->address;
        $company->phone = $request->phone;
        $company->email = $request->email ?? null;
        $company->npwp = $request->npwp ?? null;

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $company->logo = $request->file('logo')->store('company', 'public');
        }

        $company->save();

        return redirect()->route('admin.company.index')->with('success', 'Info perusahaan berhasil diperbarui');
    }

    private function companyId(): int
    {
        $admin = Employee::find(session('admin_id'));

        return (int) ($admin?->company_id ?: Company::query()->value('id') ?: 1);
    }
}
