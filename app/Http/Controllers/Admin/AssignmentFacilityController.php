<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentLetterFacility;
use App\Models\CommunicationDevice;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Master kendaraan dan perangkat komunikasi (HT) untuk surat tugas, dikelola GA&HSE.
 *
 * Item yang sudah pernah dipakai di surat tidak dihapus, hanya dinonaktifkan: teks di surat
 * lama tetap tersimpan, tapi tautan ke master dipakai untuk mendeteksi kendaraan bentrok.
 */
class AssignmentFacilityController extends Controller
{
    public function index()
    {
        $companyId = $this->admin()->company_id;

        return view('admin.assignment-facilities.index', [
            'vehicles' => Vehicle::where('company_id', $companyId)->orderByDesc('is_active')->orderBy('name')->get(),
            'devices' => CommunicationDevice::where('company_id', $companyId)->orderByDesc('is_active')->orderBy('code')->get(),
            'ownerships' => Vehicle::OWNERSHIPS,
        ]);
    }

    public function storeVehicle(Request $request)
    {
        $admin = $this->admin();
        Vehicle::create($this->validateVehicle($request) + ['company_id' => $admin->company_id]);

        return back()->with('success', 'Kendaraan ditambahkan.');
    }

    public function updateVehicle(Request $request, $id)
    {
        $vehicle = Vehicle::where('company_id', $this->admin()->company_id)->findOrFail($id);
        $vehicle->update($this->validateVehicle($request));

        return back()->with('success', 'Kendaraan diperbarui.');
    }

    public function destroyVehicle($id)
    {
        $vehicle = Vehicle::where('company_id', $this->admin()->company_id)->findOrFail($id);

        if (AssignmentLetterFacility::where('vehicle_id', $vehicle->id)->exists()) {
            $vehicle->update(['is_active' => false]);

            return back()->with('success', 'Kendaraan sudah dipakai di surat tugas, jadi dinonaktifkan (tidak dihapus).');
        }

        $vehicle->delete();

        return back()->with('success', 'Kendaraan dihapus.');
    }

    public function storeDevice(Request $request)
    {
        $admin = $this->admin();
        CommunicationDevice::create($this->validateDevice($request) + ['company_id' => $admin->company_id]);

        return back()->with('success', 'Perangkat komunikasi ditambahkan.');
    }

    public function updateDevice(Request $request, $id)
    {
        $device = CommunicationDevice::where('company_id', $this->admin()->company_id)->findOrFail($id);
        $device->update($this->validateDevice($request));

        return back()->with('success', 'Perangkat komunikasi diperbarui.');
    }

    public function destroyDevice($id)
    {
        $device = CommunicationDevice::where('company_id', $this->admin()->company_id)->findOrFail($id);

        if (AssignmentLetterFacility::where('communication_device_id', $device->id)->exists()) {
            $device->update(['is_active' => false]);

            return back()->with('success', 'Perangkat sudah dipakai di surat tugas, jadi dinonaktifkan (tidak dihapus).');
        }

        $device->delete();

        return back()->with('success', 'Perangkat komunikasi dihapus.');
    }

    private function validateVehicle(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'plate_number' => 'nullable|string|max:20',
            'ownership' => ['required', Rule::in(array_keys(Vehicle::OWNERSHIPS))],
            'notes' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Nama kendaraan wajib diisi.',
        ]);

        return $validated + ['is_active' => $request->boolean('is_active', true)];
    }

    private function validateDevice(Request $request): array
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100',
            'name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ], [
            'code.required' => 'Kode inventaris perangkat wajib diisi.',
        ]);

        return $validated + ['is_active' => $request->boolean('is_active', true)];
    }

    private function admin(): Employee
    {
        return Employee::findOrFail(session('admin_id'));
    }
}
