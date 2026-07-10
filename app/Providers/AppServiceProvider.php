<?php

namespace App\Providers;

use App\Support\AdminPermission;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu instance per request: AdminPermission menyimpan cache role/permission di dalam
        // dirinya. Tanpa singleton, tiap app(AdminPermission::class) — dan ada belasan
        // pemanggil, termasuk sidebar — membangun instance baru dengan cache kosong.
        $this->app->singleton(AdminPermission::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
