<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Loan;
use App\Models\PersonalData;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\LoanPolicy;
use App\Policies\MemberPolicy;
use App\Policies\ReportPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use App\Support\SiteContentCatalog;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Loan::class, LoanPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(PersonalData::class, MemberPolicy::class);

        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        Gate::define('reports.view', [ReportPolicy::class, 'viewAny']);
        Gate::define('reports.export', [ReportPolicy::class, 'export']);

        View::composer(['public.*', 'components.modals.member-form'], function ($view) {
            $view->with('publicContent', SiteContentCatalog::editorContent());
        });

        View::composer('public.*', function ($view) {
            $view->with('publicNewsItems', Announcement::publicNews()->latest()->get());
            $view->with('publicEventItems', Announcement::publicEvents()->orderBy('event_date')->get());
        });
    }
}
