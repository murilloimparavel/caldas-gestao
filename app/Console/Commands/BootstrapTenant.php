<?php

namespace App\Console\Commands;

use App\Actions\Identity\OnboardTenant;
use App\Actions\Identity\ResumeTenantOnboarding;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CanonicalEmail;
use App\Support\OwnerPermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

final class BootstrapTenant extends Command implements PromptsForMissingInput
{
    protected $signature = 'app:bootstrap-tenant
        {--tenant= : Unique tenant slug}
        {--unit= : Initial unit slug}
        {--name= : Tenant display name}
        {--timezone=America/Sao_Paulo : IANA timezone}
        {--currency=BRL : ISO-4217 currency code}
        {--admin-name= : First administrator display name}
        {--admin-email= : First administrator email}
        {--resume : Explicitly reconcile an existing tenant through ResumeTenantOnboarding}
        {--force : Allow execution in production}';

    protected $description = 'Create the first tenant, unit, owner and administrator safely';

    public function handle(OnboardTenant $onboard, ResumeTenantOnboarding $resume): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Production bootstrap requires --force.');

            return self::FAILURE;
        }

        $input = null;

        try {
            $input = $this->validatedInput();
            $existingTenant = Tenant::query()->where('slug', $input['tenant_slug'])->first();
            $admin = $this->resolveExistingAdmin($input['admin_email']);

            if ($admin !== null && $admin->email_verified_at === null) {
                throw new \LogicException('The existing administrator identity is not verified; verify it before bootstrap.');
            }

            if ($existingTenant !== null) {
                return $this->handleExistingTenant($existingTenant, $admin, $input, $resume);
            }

            $password = $admin === null ? $this->resolvePassword() : null;

            try {
                $tenant = DB::transaction(function () use ($admin, $input, $onboard, $password): Tenant {
                    $admin = $this->lockOrCreateAdmin($admin, $input, $password);

                    return $onboard->handle(
                        $admin,
                        [
                            'name' => $input['tenant_name'],
                            'slug' => $input['tenant_slug'],
                            'timezone' => $input['timezone'],
                            'default_currency' => $input['currency'],
                        ],
                        [
                            'name' => $input['tenant_name'],
                            'slug' => $input['unit_slug'],
                            'timezone' => $input['timezone'],
                        ],
                    );
                }, 5);
            } catch (\LogicException|QueryException $exception) {
                if ($this->wasCompletedConcurrently($input)) {
                    $this->info("Tenant already bootstrapped: {$input['tenant_slug']}");

                    return self::SUCCESS;
                }

                throw $exception;
            }

            $this->info("Tenant bootstrapped: {$tenant->slug}");

            return self::SUCCESS;
        } catch (QueryException|\LogicException $exception) {
            if ($this->convergedAfterConcurrentCreate($input)) {
                return self::SUCCESS;
            }

            $this->error($this->safeMessage($exception));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($this->safeMessage($exception));

            return self::FAILURE;
        }
    }

    /** @param array{tenant_slug:string,unit_slug:string,tenant_name:string,timezone:string,currency:string,admin_name:string,admin_email:string}|null $input */
    private function convergedAfterConcurrentCreate(?array $input): bool
    {
        if ($input === null) {
            return false;
        }

        $tenant = Tenant::query()->where('slug', $input['tenant_slug'])->first();
        $admin = User::query()->where('email_normalized', $input['admin_email'])->first();

        if ($tenant === null || $admin === null || $admin->email_verified_at === null) {
            return false;
        }

        if (! $this->isComplete($tenant, $admin, $input['unit_slug'])) {
            return false;
        }

        $this->info("Tenant already bootstrapped: {$tenant->slug}");

        return true;
    }

    /** @return array{tenant_slug:string,unit_slug:string,tenant_name:string,timezone:string,currency:string,admin_name:string,admin_email:string} */
    private function validatedInput(): array
    {
        $tenantSlug = $this->requiredOption('tenant', 'Tenant slug');
        $unitSlug = $this->requiredOption('unit', 'Initial unit slug');
        $tenantName = $this->requiredOption('name', 'Tenant name');
        $adminName = $this->requiredOption('admin-name', 'Administrator name');
        $adminEmail = CanonicalEmail::normalize($this->requiredOption('admin-email', 'Administrator email'));
        $timezone = trim((string) ($this->option('timezone') ?: 'America/Sao_Paulo'));
        $currency = Str::upper(trim((string) ($this->option('currency') ?: 'BRL')));

        $tenantSlug = Str::slug($tenantSlug);
        $unitSlug = Str::slug($unitSlug);
        $tenantName = trim($tenantName);
        $adminName = trim($adminName);

        if ($tenantSlug === '' || Str::length($tenantSlug) > 100) {
            throw new \InvalidArgumentException('Tenant slug must be a valid value up to 100 characters.');
        }

        if ($unitSlug === '' || Str::length($unitSlug) > 100) {
            throw new \InvalidArgumentException('Unit slug must be a valid value up to 100 characters.');
        }

        if ($tenantName === '' || Str::length($tenantName) > 255 || $adminName === '' || Str::length($adminName) > 255) {
            throw new \InvalidArgumentException('Tenant and administrator names are required and limited to 255 characters.');
        }

        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false || Str::length($adminEmail) > 320) {
            throw new \InvalidArgumentException('Administrator email is invalid.');
        }

        if (! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException('Timezone must be a valid IANA identifier.');
        }

        $supportedCurrencies = array_map(
            static fn (mixed $supportedCurrency): string => Str::upper(trim((string) $supportedCurrency)),
            (array) config('bootstrap.supported_currencies', ['BRL']),
        );

        if (! in_array($currency, $supportedCurrencies, true)) {
            throw new \InvalidArgumentException('Currency is not supported in this release. Supported currencies: '.implode(', ', $supportedCurrencies).'.');
        }

        return [
            'tenant_slug' => $tenantSlug,
            'unit_slug' => $unitSlug,
            'tenant_name' => $tenantName,
            'timezone' => $timezone,
            'currency' => $currency,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
        ];
    }

    private function requiredOption(string $name, string $label): string
    {
        $option = $this->option($name);
        $value = is_string($option) ? trim($option) : '';

        if ($value === '' && $this->input->isInteractive()) {
            $value = trim((string) $this->ask($label));
        }

        if ($value === '') {
            throw new \InvalidArgumentException("Option --{$name} is required.");
        }

        return $value;
    }

    private function resolveExistingAdmin(string $email): ?User
    {
        return User::query()->where('email_normalized', $email)->first();
    }

    /** @param array{tenant_slug:string,unit_slug:string,tenant_name:string,timezone:string,currency:string,admin_name:string,admin_email:string} $input */
    private function handleExistingTenant(Tenant $tenant, ?User $admin, array $input, ResumeTenantOnboarding $resume): int
    {
        if ($admin === null) {
            throw new \LogicException('The tenant already exists; its existing owner identity was not found. No user was created.');
        }

        if ($this->isComplete($tenant, $admin, $input['unit_slug'])) {
            $this->info("Tenant already bootstrapped: {$tenant->slug}");

            return self::SUCCESS;
        }

        if (! $this->option('resume')) {
            throw new \LogicException('The tenant already exists; use --resume for an explicit authorized reconciliation.');
        }

        $context = TenantContext::forUser($admin, $tenant->getKey());
        $resume->handle($admin, $context, $tenant, [
            'name' => $input['tenant_name'],
            'slug' => $input['unit_slug'],
            'timezone' => $input['timezone'],
        ]);
        $this->info("Tenant resumed: {$tenant->slug}");

        return self::SUCCESS;
    }

    /** @param array{tenant_slug:string,unit_slug:string,tenant_name:string,timezone:string,currency:string,admin_name:string,admin_email:string} $input */
    private function lockOrCreateAdmin(?User $admin, array $input, ?string $password): User
    {
        $admin = User::query()
            ->where('email_normalized', $input['admin_email'])
            ->lockForUpdate()
            ->first();

        if ($admin !== null) {
            return $admin;
        }

        if ($password === null) {
            throw new \LogicException('A password is required to create the administrator.');
        }

        $admin = User::query()->create([
            'name' => $input['admin_name'],
            'email' => $input['admin_email'],
            'password' => $password,
        ]);

        $admin->forceFill(['email_verified_at' => now()])->save();

        return $admin;
    }

    private function resolvePassword(): string
    {
        $configuredPassword = config('bootstrap.admin_password');

        if (is_string($configuredPassword) && $configuredPassword !== '') {
            $password = $configuredPassword;
        } elseif ($this->input->isInteractive()) {
            $password = (string) $this->secret('Administrator password');
            $confirmation = (string) $this->secret('Confirm administrator password');

            if ($password !== $confirmation) {
                throw new \InvalidArgumentException('Administrator passwords do not match.');
            }
        } else {
            throw new \LogicException('No administrator password was provided. Use the secret config value or an interactive prompt.');
        }

        Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        return $password;
    }

    private function isComplete(Tenant $tenant, User $admin, string $unitSlug): bool
    {
        $membership = Membership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $admin->getKey())
            ->where('status', 'active')
            ->first();
        $ownerRole = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('key', 'owner')
            ->where('is_system', true)
            ->first();

        if ($membership === null || $ownerRole === null) {
            return false;
        }

        $unit = $tenant->units()->where('slug', $unitSlug)->first();

        if ($unit === null) {
            return false;
        }

        $hasOwnerAssignment = MembershipRole::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('membership_id', $membership->getKey())
            ->where('role_id', $ownerRole->getKey())
            ->where('scope_kind', 'tenant')
            ->whereNull('unit_id')
            ->whereNull('revoked_at')
            ->exists();
        $hasUnitAssignment = MembershipUnit::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('membership_id', $membership->getKey())
            ->where('unit_id', $unit->getKey())
            ->exists();
        $catalog = app(OwnerPermissionCatalog::class);
        $catalogPermissionIds = Permission::query()
            ->whereIn('key', $catalog->keys())
            ->pluck('id');

        return $hasOwnerAssignment
            && $hasUnitAssignment
            && $catalogPermissionIds->count() === count($catalog->keys())
            && RolePermission::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('role_id', $ownerRole->getKey())
                ->whereIn('permission_id', $catalogPermissionIds)
                ->count() === $catalogPermissionIds->count();
    }

    /** @param array{tenant_slug:string,unit_slug:string,tenant_name:string,timezone:string,currency:string,admin_name:string,admin_email:string} $input */
    private function wasCompletedConcurrently(array $input): bool
    {
        $tenant = Tenant::query()->where('slug', $input['tenant_slug'])->first();
        $admin = $this->resolveExistingAdmin($input['admin_email']);

        return $tenant !== null && $admin !== null && $this->isComplete($tenant, $admin, $input['unit_slug']);
    }

    private function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            return 'Bootstrap could not be completed because the database rejected the operation.';
        }

        return $exception->getMessage() !== '' ? $exception->getMessage() : 'Bootstrap could not be completed.';
    }
}
