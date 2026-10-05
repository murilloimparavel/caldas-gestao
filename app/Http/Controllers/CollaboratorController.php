<?php

namespace App\Http\Controllers;

use App\Actions\Identity\ActivateMembership;
use App\Actions\Identity\AssignRole;
use App\Actions\Identity\InviteMembership;
use App\Actions\Identity\RevokeMembership;
use App\Enums\MembershipRoleScope;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Notifications\CollaboratorAccessNotification;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CollaboratorController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly IdentityEventRecorder $events,
        private readonly AuditEventWriter $audit,
    ) {}

    public function index(TenantContext $context, Request $request): Response
    {
        $this->authorizeAccess($context, $request->user());
        $tenantId = $context->tenant->getKey();

        return Inertia::render('settings/collaborators', [
            'memberships' => Membership::query()
                ->with(['user:id,name,email', 'professional:id,name,unit_id', 'roles:id,name'])
                ->where('tenant_id', $tenantId)
                ->where('user_id', '!=', $request->user()->getKey())
                ->orderByDesc('created_at')->get()
                ->map(fn (Membership $membership): array => [
                    'id' => $membership->getKey(),
                    'status' => $membership->status->value,
                    'user' => $membership->user?->only(['id', 'name', 'email']),
                    'professional' => $membership->professional?->only(['id', 'name', 'unit_id']),
                    'roles' => $membership->roles->map(fn (Role $role): array => [
                        'id' => $role->getKey(), 'name' => $role->name,
                    ])->values()->all(),
                ])->values()->all(),
            'roles' => Role::query()->with('permissions:id,key')->where('tenant_id', $tenantId)->where('is_system', false)->orderBy('name')->get()->map(fn (Role $role): array => [
                'id' => $role->getKey(), 'name' => $role->name, 'description' => $role->description,
                'is_system' => $role->is_system, 'permission_ids' => $role->permissions->pluck('id')->values()->all(),
            ])->values()->all(),
            'permissions' => Permission::query()->whereIn('key', $this->delegablePermissionKeys($request->user(), $context))->orderBy('key')->get(['id', 'key', 'description'])->map(fn (Permission $permission): array => [
                'id' => $permission->getKey(), 'key' => $permission->key, 'description' => $permission->description,
            ])->values()->all(),
            'professionals' => Professional::query()->where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'unit_id']),
            'canManage' => $this->authorization->can($request->user(), $context, 'role.manage'),
        ]);
    }

    public function storeRole(Request $request, TenantContext $context): RedirectResponse
    {
        $this->authorizeAccess($context, $request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['uuid', Rule::exists('permissions', 'id')],
        ]);
        $allowedKeys = $this->delegablePermissionKeys($request->user(), $context);
        $requestedPermissionIds = array_values(array_unique(array_map('strval', $data['permission_ids'] ?? [])));
        $permissionIds = Permission::query()->whereIn('key', $allowedKeys)->whereIn('id', $requestedPermissionIds)->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        if (array_diff($requestedPermissionIds, $permissionIds) !== []) {
            abort(422, 'O perfil contém uma permissão que o usuário atual não pode delegar.');
        }
        DB::transaction(function () use ($context, $data, $permissionIds, $request): void {
            $role = Role::query()->create([
                'tenant_id' => $context->tenant->getKey(), 'key' => 'custom_'.Str::substr(Str::slug($data['name']), 0, 65).'_'.Str::lower(Str::random(6)),
                'name' => $data['name'], 'description' => $data['description'] ?? null,
            ]);
            foreach (array_unique($permissionIds) as $permissionId) {
                RolePermission::query()->create(['tenant_id' => $context->tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $permissionId]);
            }
            $this->events->record($request->user(), $context, 'role.created', $role, ['permission_ids' => $permissionIds]);
            $this->events->record($request->user(), $context, 'role.permissions.updated', $role, ['permission_ids' => $permissionIds]);
        });

        return to_route('settings.collaborators')->with('success', 'Perfil criado.');
    }

    public function updateRole(Request $request, Role $role, TenantContext $context): RedirectResponse
    {
        $this->authorizeAccess($context, $request->user());
        abort_unless($role->tenant_id === $context->tenant->getKey() && ! $role->is_system, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['array'], 'permission_ids.*' => ['uuid', Rule::exists('permissions', 'id')],
        ]);
        $allowedKeys = $this->delegablePermissionKeys($request->user(), $context);
        $requestedPermissionIds = array_values(array_unique(array_map('strval', $data['permission_ids'] ?? [])));
        $permissionIds = Permission::query()->whereIn('key', $allowedKeys)->whereIn('id', $requestedPermissionIds)->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        if (array_diff($requestedPermissionIds, $permissionIds) !== []) {
            abort(422, 'O perfil contém uma permissão que o usuário atual não pode delegar.');
        }
        DB::transaction(function () use ($context, $data, $permissionIds, $role, $request): void {
            $role->forceFill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'lock_version' => $role->lock_version + 1])->save();
            RolePermission::query()->where('tenant_id', $context->tenant->getKey())->where('role_id', $role->getKey())->delete();
            foreach (array_unique($permissionIds) as $permissionId) {
                RolePermission::query()->create(['tenant_id' => $context->tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $permissionId]);
            }
            $this->events->record($request->user(), $context, 'role.updated', $role, ['permission_ids' => $permissionIds]);
            $this->events->record($request->user(), $context, 'role.permissions.updated', $role, ['permission_ids' => $permissionIds]);
        });

        return to_route('settings.collaborators')->with('success', 'Perfil atualizado.');
    }

    public function store(Request $request, TenantContext $context, InviteMembership $invite, ActivateMembership $activate): RedirectResponse
    {
        $this->authorizeAccess($context, $request->user());
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:320']]);
        $email = strtolower(trim($data['email']));
        $existingUser = User::query()->where('email_normalized', $email)->first();
        if ($existingUser?->is($request->user())) {
            return back()->withErrors(['email' => 'Você já possui acesso a este tenant e não pode ser convidado como colaborador.']);
        }
        if ($existingUser?->memberships()->where('tenant_id', $context->tenant->getKey())->where('status', 'active')->exists()) {
            return back()->withErrors(['email' => 'Este usuário já possui uma membership ativa neste tenant.']);
        }
        [$user, $membership, $created] = DB::transaction(function () use ($context, $data, $email, $invite, $request, $activate): array {
            $user = User::query()->createOrFirst(
                ['email_normalized' => $email],
                [
                    'name' => trim($data['name']),
                    'email' => $email,
                    'password' => Hash::make(Str::random(48)),
                    'must_change_password' => true,
                    'temporary_password_expires_at' => now()->addDays(3),
                ],
            );
            $created = $user->wasRecentlyCreated;
            $membership = $invite->handle($request->user(), $context, $context->tenant, $user);
            if ($membership->status->value === 'invited' && $context->unit !== null) {
                MembershipUnit::query()->firstOrCreate([
                    'tenant_id' => $context->tenant->getKey(), 'membership_id' => $membership->getKey(), 'unit_id' => $context->unit->getKey(),
                ], ['is_primary' => true]);
                if ($user->email_verified_at !== null) {
                    $activate->handle($request->user(), $context, $membership);
                }
            }

            return [$user, $membership, $created];
        }, 5);
        if ($membership->status->value !== 'invited') {
            return back()->withErrors(['email' => 'Este usuário já possui uma membership ativa neste tenant.']);
        }
        $delivery = $this->sendAccess($request, $context, $membership, $created ? 'membership.invite.sent' : 'membership.access.sent');

        return to_route('settings.collaborators')->with($delivery['sent'] ? 'success' : 'warning', $delivery['sent'] ? 'Convite criado para o colaborador.' : ($delivery['partial'] ? 'Convite criado. O e-mail de verificação foi enviado, mas o link para definir a senha atingiu o limite de tentativas.' : 'Convite criado, mas o e-mail não foi enviado. Tente reenviar o acesso.'));
    }

    public function resendAccess(Request $request, Membership $membership, TenantContext $context, ActivateMembership $activate): RedirectResponse
    {
        $this->authorizeAccess($context, $request->user());
        abort_unless($membership->tenant_id === $context->tenant->getKey(), 404);
        abort_unless($membership->status->value === 'invited', 422);
        $membership->loadMissing('user');
        if ($membership->user?->email_verified_at !== null && $context->unit !== null) {
            MembershipUnit::query()->firstOrCreate([
                'tenant_id' => $context->tenant->getKey(), 'membership_id' => $membership->getKey(), 'unit_id' => $context->unit->getKey(),
            ], ['is_primary' => true]);
            $activate->handle($request->user(), $context, $membership);
        }
        $delivery = $this->sendAccess($request, $context, $membership, 'membership.access.resent');

        return back()->with($delivery['sent'] ? 'success' : 'warning', $delivery['sent'] ? 'Link de acesso reenviado.' : ($delivery['partial'] ? 'E-mail de verificação reenviado, mas o link para definir a senha atingiu o limite de tentativas.' : 'Não foi possível enviar o link de acesso. Tente novamente mais tarde.'));
    }

    public function assignRole(Request $request, Membership $membership, TenantContext $context, AssignRole $assign): RedirectResponse
    {
        $this->authorizeAccess($context, $request->user());
        $data = $request->validate(['role_id' => ['required', 'uuid', Rule::exists('roles', 'id')], 'scope_kind' => ['required', Rule::in(['tenant', 'unit'])], 'unit_id' => ['nullable', 'uuid']]);
        $roleId = (string) $data['role_id'];
        $role = Role::query()->where('tenant_id', $context->tenant->getKey())->whereKey($roleId)->firstOrFail();
        $rolePermissionKeys = $role->permissions()->pluck('key')->all();
        abort_unless(count(array_diff($rolePermissionKeys, $this->delegablePermissionKeys($request->user(), $context))) === 0, 403);
        $unit = null;
        if ($data['scope_kind'] === 'unit') {
            $unit = $context->tenant->units()->whereKey((string) $data['unit_id'])->firstOrFail();
        }
        $assign->handle($request->user(), $context, $membership, $role, MembershipRoleScope::from($data['scope_kind']), $unit);

        return back()->with('success', 'Perfil atribuído.');
    }

    public function linkProfessional(Request $request, Membership $membership, TenantContext $context): RedirectResponse
    {
        $this->authorizeAccess($context, $request->user());
        abort_unless($membership->tenant_id === $context->tenant->getKey(), 404);
        $data = $request->validate(['professional_id' => ['nullable', 'uuid']]);
        $professional = null;
        if ($data['professional_id'] !== null) {
            $professional = Professional::query()->where('tenant_id', $context->tenant->getKey())->whereKey((string) $data['professional_id'])->firstOrFail();
        }
        if ($professional !== null) {
            abort_unless($membership->membershipUnits()->where('unit_id', $professional->unit_id)->exists(), 422, 'O profissional precisa pertencer a uma unidade liberada para o colaborador.');
        }
        DB::transaction(function () use ($context, $membership, $professional, $request): void {
            $lockedMembership = Membership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();
            $previousProfessionalId = $lockedMembership->professional_id;
            $professionalId = $professional?->getKey();

            $lockedMembership->forceFill([
                'professional_id' => $professionalId,
                'lock_version' => $lockedMembership->lock_version + 1,
            ])->save();

            $this->events->record(
                $request->user(),
                $context,
                $professionalId === null ? 'membership.professional_unlinked' : 'membership.professional_linked',
                $lockedMembership,
                [
                    'membership_id' => $lockedMembership->getKey(),
                    'professional_id' => $professionalId,
                    'previous_professional_id' => $previousProfessionalId,
                    'lock_version' => $lockedMembership->lock_version,
                ],
            );
        });

        return back()->with('success', 'Profissional vinculado.');
    }

    public function revoke(Request $request, Membership $membership, TenantContext $context, RevokeMembership $revoke): RedirectResponse
    {
        $this->authorizeAccess($context, $request->user());
        abort_unless($membership->tenant_id === $context->tenant->getKey(), 404);
        $revoke->handle($request->user(), $context, $membership);

        return to_route('settings.collaborators')->with('success', 'Acesso do colaborador revogado.');
    }

    private function authorizeAccess(TenantContext $context, User $user): void
    {
        abort_unless($this->authorization->can($user, $context, 'role.manage') && $this->authorization->can($user, $context, 'membership.manage'), 403);
    }

    /** @return array{sent: bool, partial: bool} */
    private function sendAccess(Request $request, TenantContext $context, Membership $membership, string $action): array
    {
        $membership->loadMissing('user');
        $user = $membership->user;
        $status = $user->email_verified_at === null
            ? Password::broker()->sendResetLink(['email' => $user->email])
            : 'access_notification_sent';
        $verificationSent = false;
        if ($user->email_verified_at === null) {
            $user->sendEmailVerificationNotification();
            $verificationSent = true;
        } else {
            $user->notify(new CollaboratorAccessNotification($context->tenant->name));
        }
        $this->audit->record([
            'actor_user_id' => $request->user()->getKey(),
            'tenant_id' => $context->tenant->getKey(),
            'action' => $action,
            'resource_type' => 'membership',
            'resource_id' => $membership->getKey(),
            'metadata' => ['status' => $status, 'verification_sent' => $verificationSent],
        ]);

        $sent = $user->email_verified_at !== null || $status === Password::RESET_LINK_SENT;

        return ['sent' => $sent, 'partial' => ! $sent && $verificationSent];
    }

    /** @return list<string> */
    private function delegablePermissionKeys(User $user, TenantContext $context): array
    {
        $administrative = ['tenant.manage', 'membership.manage', 'membership.invite', 'membership.activate', 'membership.revoke', 'membership.assign_role', 'role.manage', 'role.update', 'role.delete'];

        return array_values(array_diff($this->authorization->permissions($user, $context), $administrative));
    }
}
