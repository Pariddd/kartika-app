<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Support\AppMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Di luar production: tolak lazy loading, atribut yang tidak ada,
        // dan mass assignment ke kolom yang tidak fillable.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Pengumpulan data nyata: hanya admin dan analis, dan hanya di mode lokal.
        Gate::define('collect-data', fn (User $user): bool => AppMode::isLocal()
            && $user->hasRole(Role::Admin, Role::Analis));

        Gate::define('view-audit-log', fn (User $user): bool => $user->hasRole(Role::Admin));
    }
}
