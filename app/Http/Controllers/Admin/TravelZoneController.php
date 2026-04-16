<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelZone;
use Illuminate\Http\Request;

class TravelZoneController extends Controller
{
    public function index()
    {
        $zones = TravelZone::orderBy('zone')->get();
        return view('admin.travel-zones.index', compact('zones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'zone' => 'required|integer|min:1|unique:travel_zones,zone',
            'name' => 'required|string|max:255',
            'meal_allowance' => 'required|numeric|min:0',
        ]);

        TravelZone::create($request->only('zone', 'name', 'meal_allowance'));

        return redirect()->route('admin.travel-zones.index')
            ->with('success', "Zona {$request->zone} berhasil ditambahkan.");
    }

    public function update(Request $request, $id)
    {
        $zone = TravelZone::findOrFail($id);

        $request->validate([
            'zone' => 'required|integer|min:1|unique:travel_zones,zone,' . $id,
            'name' => 'required|string|max:255',
            'meal_allowance' => 'required|numeric|min:0',
        ]);

        $zone->update($request->only('zone', 'name', 'meal_allowance'));

        return redirect()->route('admin.travel-zones.index')
            ->with('success', "Zona {$zone->zone} berhasil diperbarui.");
    }

    public function destroy($id)
    {
        $zone = TravelZone::findOrFail($id);
        $zoneNo = $zone->zone;
        $zone->delete();

        return redirect()->route('admin.travel-zones.index')
            ->with('success', "Zona {$zoneNo} berhasil dihapus.");
    }
}
