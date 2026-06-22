<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class AttendanceSettingController extends Controller
{
    private array $defaults = [
        'office_latitude' => '1.0456',
        'office_longitude' => '104.0305',
        'office_radius_meters' => '100',
        'office_address' => '',
        'require_photo' => '1',
        'require_gps' => '1',
        'allow_remote_clockin' => '0',
        'remote_requires_approval' => '1',
        'remote_requires_notes' => '1',
        'clockin_reminder_enabled' => '0',
        'clockin_reminder_time' => '07:45',
        'auto_clockout_enabled' => '0',
        'auto_clockout_time' => '18:00',
        'face_verification_enabled' => '1',
        'tomtom_api_key' => '',
    ];

    public function index()
    {
        $settings = [];
        foreach ($this->defaults as $key => $default) {
            $settings[$key] = Setting::getValue($key, $default);
        }

        return view('admin.attendance-settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'office_latitude' => 'required|numeric',
            'office_longitude' => 'required|numeric',
            'office_radius_meters' => 'required|integer|min:50|max:1000',
            'office_address' => 'nullable|string|max:255',
            'clockin_reminder_time' => 'nullable|date_format:H:i',
            'auto_clockout_time' => 'nullable|date_format:H:i',
        ]);

        $booleanKeys = [
            'require_photo', 'require_gps', 'allow_remote_clockin',
            'remote_requires_approval', 'remote_requires_notes',
            'clockin_reminder_enabled', 'auto_clockout_enabled',
            'face_verification_enabled',
        ];

        $textKeys = [
            'office_latitude', 'office_longitude', 'office_radius_meters',
            'office_address', 'clockin_reminder_time', 'auto_clockout_time',
            'tomtom_api_key',
        ];

        foreach ($booleanKeys as $key) {
            Setting::setValue($key, $request->boolean($key) ? '1' : '0');
        }

        foreach ($textKeys as $key) {
            if ($request->has($key)) {
                Setting::setValue($key, $request->input($key) ?? '');
            }
        }

        // Migrate old key
        $oldRadius = Setting::where('key', 'gps_radius_meters')->first();
        if ($oldRadius) {
            $oldRadius->delete();
        }

        return back()->with('success', 'Pengaturan presensi berhasil disimpan.');
    }
}
