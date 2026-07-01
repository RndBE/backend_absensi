<?php

use App\Console\Commands\GenerateAutoOvertime;
use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Setiap hari pukul 00:05: generate lembur otomatis untuk jadwal hari itu
Schedule::command('overtime:auto-generate')->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/auto-overtime.log'));

// Ingatkan karyawan membuat LPJ — jam kirim mengikuti Pengaturan Presensi.
// Toggle aktif/nonaktif & jumlah hari dicek di dalam LpjReminderService.
$lpjReminderTime = '08:00';
try {
    if (Schema::hasTable('settings')) {
        $lpjReminderTime = Setting::getValue('lpj_reminder_time', '08:00') ?: '08:00';
    }
} catch (\Throwable $e) {
    // Abaikan saat tabel belum siap (mis. fresh migrate).
}

Schedule::command('lpj:remind')->dailyAt($lpjReminderTime)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/lpj-reminder.log'));

// Ingatkan karyawan membuat LHP — setelah pulang & menjelang batas pengumpulan.
// Toggle aktif/nonaktif & hari dicek di dalam LhpReminderService.
$lhpReminderTime = '08:00';
try {
    if (Schema::hasTable('settings')) {
        $lhpReminderTime = Setting::getValue('lhp_reminder_time', '08:00') ?: '08:00';
    }
} catch (\Throwable $e) {
    // Abaikan saat tabel belum siap (mis. fresh migrate).
}

Schedule::command('lhp:remind')->dailyAt($lhpReminderTime)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/lhp-reminder.log'));
