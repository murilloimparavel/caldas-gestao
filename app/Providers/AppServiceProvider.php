<?php

namespace App\Providers;

use App\Actions\Identity\ActivateVerifiedOwnerMemberships;
use App\Auth\NormalizedEmailUserProvider;
use App\Contracts\CustomDomainProvisioner;
use App\Contracts\DnsResolver;
use App\Contracts\TlsCertificateVerifier;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Customer;
use App\Models\CustomerCommunicationPreference;
use App\Models\Membership;
use App\Models\PackageUsage;
use App\Models\Professional;
use App\Models\RetentionCampaign;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Policies\AppointmentPolicy;
use App\Policies\AvailabilityRulePolicy;
use App\Policies\CustomerCommunicationPreferencePolicy;
use App\Policies\CustomerPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\PackageUsagePolicy;
use App\Policies\ProfessionalPolicy;
use App\Policies\RetentionCampaignPolicy;
use App\Policies\RolePolicy;
use App\Policies\SaleCategoryPolicy;
use App\Policies\SalePolicy;
use App\Policies\ScheduleBlockPolicy;
use App\Policies\ServicePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UnitPolicy;
use App\Support\CoolifyCustomDomainProvisioner;
use App\Support\NativeDnsResolver;
use App\Support\NativeTlsCertificateVerifier;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, NativeDnsResolver::class);
        $this->app->bind(TlsCertificateVerifier::class, NativeTlsCertificateVerifier::class);
        $this->app->bind(CustomDomainProvisioner::class, CoolifyCustomDomainProvisioner::class);

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
        Event::listen(Verified::class, ActivateVerifiedOwnerMemberships::class);

        RateLimiter::for('public-booking', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('public-booking-create', fn (Request $request): Limit => Limit::perMinute(8)->by($request->ip()));
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerCommunicationPreference::class, CustomerCommunicationPreferencePolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(AvailabilityRule::class, AvailabilityRulePolicy::class);
        Gate::policy(Professional::class, ProfessionalPolicy::class);
        Gate::policy(PackageUsage::class, PackageUsagePolicy::class);
        Gate::policy(RetentionCampaign::class, RetentionCampaignPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(ScheduleBlock::class, ScheduleBlockPolicy::class);
        Gate::policy(SaleCategory::class, SaleCategoryPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);

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
