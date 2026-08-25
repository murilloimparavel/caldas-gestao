<?php

namespace App\Providers;

use App\Auth\NormalizedEmailUserProvider;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Unit;
use App\Policies\MembershipPolicy;
use App\Policies\RolePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UnitPolicy;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, function (): TenantContext {
            throw new \LogicException('TenantContext must be resolved by tenant.context middleware.');
        });

        Auth::provider('normalized_eloquent', function (Application $app, array $config): NormalizedEmailUserProvider {
            /** @var class-string<Authenticatable&Model> $model */
            $model = $config['model'];

            return new NormalizedEmailUserProvider($app->make('hash'), $model);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
