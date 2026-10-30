<?php

namespace App\Providers;

use App\Models\ShopeeSetting;
use App\Services\ShopeeService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShopeeService::class, fn () => new ShopeeService(null));
    }

    public function boot(): void
    {
        Paginator::useBootstrapFour();

        if (request()->header('x-forwarded-proto') === 'https' || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            URL::forceScheme('https');
        }

        view()->composer('*', function ($view) {
            $view->with('currentShopeeSetting', ShopeeSetting::current());
        });
    }
}
