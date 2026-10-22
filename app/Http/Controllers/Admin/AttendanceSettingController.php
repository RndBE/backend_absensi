<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WorkAnniversaryService;
use App\Support\HrContact;
use Illuminate\Http\Request;

class AttendanceSettingController extends Controller
{
    private array $defaults = [
        'office_latitude' => '1.0456',
        'office_longitude' => '104.0305',
        'office_radius_meters' => '100',
        'max_gps_accuracy_meters' => '100',
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
        'anniversary_greeting_enabled' => '1',
        'anniversary_milestones' => WorkAnniversaryService::DEFAULT_MILESTONES,
        'anniversary_greeting_time' => '08:00',
        // Kontak bantuan di halaman login mobile; kosong = memakai cadangan (lihat HrContact).
        HrContact::KEY_WHATSAPP => '',
        HrContact::KEY_EMAIL => '',
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
        // Dipangkas dulu supaya spasi di tepi (mis. hasil tempel) tidak membuat validasi email gagal.
        if ($request->has(HrContact::KEY_EMAIL)) {
            $request->merge([HrContact::KEY_EMAIL => trim((string) $request->input(HrContact::KEY_EMAIL))]);
        }

        $request->validate([
            'office_latitude' => 'required|numeric',
            'office_longitude' => 'required|numeric',
            'office_radius_meters' => 'required|integer|min:50|max:1000',
            'max_gps_accuracy_meters' => 'nullable|integer|min:10|max:500',
            'office_address' => 'nullable|string|max:255',
            'clockin_reminder_before' => 'nullable|integer|min:0|max:120',
            'auto_clockout_time' => 'nullable|date_format:H:i',
            'lpj_reminder_days' => 'nullable|integer|min:1|max:30',
            'lpj_reminder_time' => 'nullable|date_format:H:i',
            'lhp_reminder_after_days' => 'nullable|integer|min:1|max:30',
            'lhp_reminder_before_days' => 'nullable|integer|min:1|max:30',
            'lhp_reminder_time' => 'nullable|date_format:H:i',
            // "every" = setiap tahun, "milestones" = hanya kelipatan di bawah. Daftar kelipatan wajib
            // diisi pada mode kedua, karena daftar kosong akan dibaca sebagai setiap tahun.
            'anniversary_mode' => 'nullable|in:every,milestones',
            'anniversary_milestones' => ['nullable', 'required_if:anniversary_mode,milestones', 'string', 'max:100', 'regex:/^(\*|\d{1,2}(\s*,\s*\d{1,2})*)$/'],
            'anniversary_greeting_time' => 'nullable|date_format:H:i',
            // WhatsApp boleh ditulis 08…, +62…, atau dengan spasi/strip; disimpan sebagai 62….
            HrContact::KEY_WHATSAPP => ['nullable', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (filled($value) && HrContact::normalizeWhatsapp($value) === null) {
                    $fail('Nomor WhatsApp belum benar. Contoh: 0812 3456 7890 atau +62 812 3456 7890.');
                }
            }],
            HrContact::KEY_EMAIL => 'nullable|email|max:255',
        ], [
            'anniversary_milestones.regex' => 'Kelipatan tahun harus berupa angka dipisah koma, contoh: 1,3,5,10.',
            'anniversary_milestones.required_if' => 'Isi tahun yang dirayakan, contoh: 1,3,5,10. Atau pilih "Setiap tahun".',
        ]);

        $booleanKeys = [
            'require_photo', 'require_gps', 'allow_remote_clockin',
            'remote_requires_approval', 'remote_requires_notes',
            'clockin_reminder_enabled', 'auto_clockout_enabled',
            'face_verification_enabled', 'lpj_reminder_enabled',
            'lhp_reminder_enabled', 'anniversary_greeting_enabled',
        ];

        $textKeys = [
            'office_latitude', 'office_longitude', 'office_radius_meters',
            'max_gps_accuracy_meters',
            'office_address', 'clockin_reminder_before', 'auto_clockout_time',
            'lpj_reminder_days', 'lpj_reminder_time',
            'lhp_reminder_after_days', 'lhp_reminder_before_days', 'lhp_reminder_time',
            'anniversary_greeting_time',
        ];

        foreach ($booleanKeys as $key) {
            Setting::setValue($key, $request->boolean($key) ? '1' : '0');
        }

        foreach ($textKeys as $key) {
            if ($request->has($key)) {
                Setting::setValue($key, $request->input($key) ?? '');
            }
        }

        // Mode "setiap tahun" disimpan sebagai "*"; tanpa anniversary_mode (form lama) isian diteruskan apa adanya.
        $mode = $request->input('anniversary_mode');
        if ($mode === 'every') {
            Setting::setValue('anniversary_milestones', WorkAnniversaryService::EVERY_YEAR);
        } elseif ($mode === 'milestones' || $request->has('anniversary_milestones')) {
            Setting::setValue('anniversary_milestones', trim((string) $request->input('anniversary_milestones')));
        }

        // Kontak bantuan login: nomor disimpan dalam format WhatsApp (62…) supaya konsisten.
        if ($request->has(HrContact::KEY_WHATSAPP)) {
            Setting::setValue(
                HrContact::KEY_WHATSAPP,
                HrContact::normalizeWhatsapp($request->input(HrContact::KEY_WHATSAPP)) ?? ''
            );
        }
        if ($request->has(HrContact::KEY_EMAIL)) {
            Setting::setValue(HrContact::KEY_EMAIL, trim((string) $request->input(HrContact::KEY_EMAIL)));
        }

        // Migrate old key
        $oldRadius = Setting::where('key', 'gps_radius_meters')->first();
        if ($oldRadius) {
            $oldRadius->delete();
        }

        return back()->with('success', 'Pengaturan presensi berhasil disimpan.');
    }
}
