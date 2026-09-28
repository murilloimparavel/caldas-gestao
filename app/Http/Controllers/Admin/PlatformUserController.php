<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ManagePlatformUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlatformUserRequest;
use App\Models\Membership;
use App\Models\Tenant;
use App\Support\AuditEventWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

final class PlatformUserController extends Controller
{
    public function store(PlatformUserRequest $request, Tenant $tenant, ManagePlatformUser $manage): RedirectResponse
    {
        $membership = $manage->create($request->user(), $tenant, $request->validated());
        $status = Password::broker()->sendResetLink(['email' => $membership->user->email]);
        $sent = $status === Password::RESET_LINK_SENT;
        app(AuditEventWriter::class)->record(['actor_user_id' => $request->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.user.invite_sent', 'resource_type' => 'user', 'resource_id' => $membership->user->getKey(), 'metadata' => ['status' => $status]]);

        return back()->with($sent ? 'success' : 'warning', $sent ? 'Usuário criado e convite enviado.' : 'Usuário criado, mas o convite não pôde ser enviado.');
    }

    public function revoke(Request $request, Tenant $tenant, Membership $membership, ManagePlatformUser $manage): RedirectResponse
    {
        $manage->revoke($request->user(), $tenant, $membership);

        return back()->with('success', 'Usuário revogado.');
    }

    public function sendAccess(Request $request, Tenant $tenant, Membership $membership, AuditEventWriter $audit): RedirectResponse
    {
        abort_unless($membership->tenant_id === $tenant->getKey(), 404);
        $user = $membership->user;
        $status = Password::broker()->sendResetLink(['email' => $user->email]);
        $sent = $status === Password::RESET_LINK_SENT;
        if ($sent) {
            $audit->record(['actor_user_id' => $request->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.user.access_sent', 'resource_type' => 'user', 'resource_id' => $user->getKey(), 'metadata' => ['status' => $status]]);
        }

        return back()->with($sent ? 'success' : 'error', $sent ? 'Link de acesso enviado.' : 'Não foi possível enviar o link de acesso.');
    }
}
