<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        Gate::define('access-workspace', fn (User $user) => $user->isFreelancer());
        Gate::define('access-portal', fn (User $user) => $user->isClient());
    }
}
