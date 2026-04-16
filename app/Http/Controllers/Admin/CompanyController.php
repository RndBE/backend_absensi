<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index()
    {
        $company = Company::first();
        return view('admin.company.index', compact('company'));
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

        $company = Company::first();

        if (!$company) {
            $company = new Company();
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
}
