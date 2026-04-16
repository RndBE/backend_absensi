<?php

use App\Console\Commands\GenerateAutoOvertime;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Setiap hari pukul 00:05: generate lembur otomatis untuk jadwal hari itu
Schedule::command('overtime:auto-generate')->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/auto-overtime.log'));
