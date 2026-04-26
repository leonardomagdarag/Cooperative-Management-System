<?php

namespace App\Providers;

use App\Models\Loan;
use App\Models\PersonalData;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\LoanPolicy;
use App\Policies\MemberPolicy;
use App\Policies\ReportPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

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
        Gate::policy(Loan::class, LoanPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(PersonalData::class, MemberPolicy::class);

        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        Gate::define('reports.view', [ReportPolicy::class, 'viewAny']);
        Gate::define('reports.export', [ReportPolicy::class, 'export']);

        View::composer('*', function ($view) {
            $view->with('authUser', Auth::user());
        });
    }
}
