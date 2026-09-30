{{--
    Served by PreventRequestsDuringMaintenance whenever `php artisan down` is
    active, which is how BackupService::restore() brackets a restore: the
    database is being replaced underneath the request, so this has to render
    with no database, no session and no compiled-asset manifest.

    Registered ahead of the framework's built-in 503 page because
    RegisterErrorViewPaths maps the `errors` namespace to resources/views/errors
    before falling back to Illuminate\Foundation\Exceptions\views.
--}}
@include('maintenance', ['maintenance' => \App\Support\MaintenanceState::describe()])