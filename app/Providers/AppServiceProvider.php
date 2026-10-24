<?php

namespace App\Providers;

use App\Models\ShopeeSetting;
use Illuminate\Support\Facades\URL;
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

    public function boot(): void
    {
        if (request()->header('x-forwarded-proto') === 'https' || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            URL::forceScheme('https');
        }

        view()->composer('*', function ($view) {
            $view->with('currentShopeeSetting', ShopeeSetting::current());
        });
    }
}
