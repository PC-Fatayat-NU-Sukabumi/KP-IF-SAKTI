<?php

namespace App\Providers;

use App\Models\PAC;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer(['partials.sidebar', 'partials.header', 'dashboard', 'pengajuanPAC'], function ($view) {
            $pendingPacCount = 0;
            try {
                if (Schema::hasTable('pacs')) {
                    $pendingPacCount = PAC::where('status', 'pending')->count();
                }
            } catch (\Throwable) {
                // Ignore during setup or test migrations
            }
            $view->with('pendingPacCount', $pendingPacCount);
        });
    }
}
