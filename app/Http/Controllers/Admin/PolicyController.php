<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use Illuminate\Http\Request;

class PolicyController extends Controller
{
    public function index()
    {
        $policies = Policy::orderBy('key')->get();
        return view('admin.policies.index', compact('policies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:100|unique:policies,key|regex:/^[A-Z0-9_]+$/',
            'name' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        Policy::create($request->only('key', 'name', 'value', 'description'));

        return redirect()->route('admin.policies.index')
            ->with('success', "Kebijakan \"{$request->name}\" berhasil ditambahkan.");
    }

    public function update(Request $request, $id)
    {
        $policy = Policy::findOrFail($id);

        $request->validate([
            'key' => 'required|string|max:100|unique:policies,key,' . $id . '|regex:/^[A-Z0-9_]+$/',
            'name' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        $policy->update($request->only('key', 'name', 'value', 'description'));

        return redirect()->route('admin.policies.index')
            ->with('success', "Kebijakan \"{$policy->name}\" berhasil diperbarui.");
    }

    public function destroy($id)
    {
        $policy = Policy::findOrFail($id);
        $name = $policy->name;
        $policy->delete();

        return redirect()->route('admin.policies.index')
            ->with('success', "Kebijakan \"{$name}\" berhasil dihapus.");
    }
}
