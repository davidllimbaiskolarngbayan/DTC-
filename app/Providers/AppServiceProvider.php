<?php

namespace App\Providers;

use App\Contracts\DtcMarket;
use App\Implementations\Markets\Standard;
use App\Models\Claim;
use App\Services\ClaimStaffNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DtcMarket::class, Standard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapThree();

        view()->composer('layouts.app', function (View $view) {
            $isStaff = Auth::check() && Auth::user()->role === 'staff';
            $notifications = app(ClaimStaffNotifications::class);
            $claimAlertCount = $isStaff
                ? $notifications->unreadCountFor(Auth::user())
                : 0;
            $claimAlerts = $isStaff
                ? $notifications->unreadFor(Auth::user())
                : collect();
            $submittedClaimsCount = Auth::check()
                ? Claim::query()->where('status', 'submitted')->count()
                : 0;

            $view->with(compact('claimAlertCount', 'claimAlerts', 'submittedClaimsCount'));
        });
    }
}
