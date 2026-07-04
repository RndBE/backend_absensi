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
        'clockin_reminder_before' => '15',
        'auto_clockout_enabled' => '0',
        'auto_clockout_time' => '18:00',
        'face_verification_enabled' => '1',
        'lpj_reminder_enabled' => '1',
        'lpj_reminder_days' => '3',
        'lpj_reminder_time' => '08:00',
        'lhp_reminder_enabled' => '1',
        'lhp_reminder_after_days' => '1',
        'lhp_reminder_before_days' => '2',
        'lhp_reminder_time' => '08:00',
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
            'clockin_reminder_before' => 'nullable|integer|min:0|max:120',
            'auto_clockout_time' => 'nullable|date_format:H:i',
            'lpj_reminder_days' => 'nullable|integer|min:1|max:30',
            'lpj_reminder_time' => 'nullable|date_format:H:i',
            'lhp_reminder_after_days' => 'nullable|integer|min:1|max:30',
            'lhp_reminder_before_days' => 'nullable|integer|min:1|max:30',
            'lhp_reminder_time' => 'nullable|date_format:H:i',
        ]);

        $booleanKeys = [
            'require_photo', 'require_gps', 'allow_remote_clockin',
            'remote_requires_approval', 'remote_requires_notes',
            'clockin_reminder_enabled', 'auto_clockout_enabled',
            'face_verification_enabled', 'lpj_reminder_enabled',
            'lhp_reminder_enabled',
        ];

        $textKeys = [
            'office_latitude', 'office_longitude', 'office_radius_meters',
            'office_address', 'clockin_reminder_before', 'auto_clockout_time',
            'lpj_reminder_days', 'lpj_reminder_time',
            'lhp_reminder_after_days', 'lhp_reminder_before_days', 'lhp_reminder_time',
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
