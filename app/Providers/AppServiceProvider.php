<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Riwayat skor kinerja: satu pintu penulis, menangkap semua jalur perubahan.
        \App\Models\RekapKinerjaBulanan::observe(\App\Observers\RekapKinerjaBulananObserver::class);

        //
    }
}
