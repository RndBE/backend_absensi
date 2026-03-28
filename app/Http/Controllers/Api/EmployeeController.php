<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::where('company_id', $request->user()->company_id)
            ->where('is_active', true)
            ->with('department:id,name');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            });
        }

        $employees = $query->select('id', 'employee_code', 'full_name', 'phone', 'photo', 'position', 'department_id')
            ->orderBy('full_name')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $employees]);
    }

    public function show(Request $request, $id)
    {
        $employee = Employee::where('company_id', $request->user()->company_id)
            ->with(['department:id,name', 'company:id,name'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'company' => $employee->company?->name,
                'photo' => $employee->photo,
            ],
        ]);
    }
}
