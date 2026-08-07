<?php

namespace App\Providers;

use App\Services\Authorization\CurrentCompanyPermissionResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class, fn ($app) => new CurrentCompany($app['session.store']));
        $this->app->scoped(CurrentCompanyPermissionResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
