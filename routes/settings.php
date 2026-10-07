<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\Integrations\IntegrationCredentialController;
use App\Http\Controllers\Integrations\IntegrationOAuthGrantController;
use App\Http\Controllers\Integrations\ProposedOperationController;
use App\Http\Controllers\Integrations\StepUpPasskeyController;
use App\Http\Controllers\Settings\ApiController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\EnsureAssistantEnabled;
use App\Http\Middleware\RequireIntegrationManagement;
use App\Http\Middleware\RequirePasskeyStepUp;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', 'tenant.context', 'saas.access', 'first.login.complete', RequireIntegrationManagement::class])
    ->get('settings/api', [ApiController::class, 'edit'])
    ->name('settings.api');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::middleware('tenant.context')->group(function (): void {
        Route::get('settings/collaborators', [CollaboratorController::class, 'index'])->name('settings.collaborators');
        Route::post('settings/collaborators', [CollaboratorController::class, 'store'])->middleware('throttle:collaborator-access')->name('settings.collaborators.store');
        Route::post('settings/collaborators/roles', [CollaboratorController::class, 'storeRole'])->name('settings.collaborators.roles.store');
        Route::patch('settings/collaborators/roles/{role}', [CollaboratorController::class, 'updateRole'])->name('settings.collaborators.roles.update');
        Route::post('settings/collaborators/{membership}/role', [CollaboratorController::class, 'assignRole'])->name('settings.collaborators.role.assign');
        Route::patch('settings/collaborators/{membership}/professional', [CollaboratorController::class, 'linkProfessional'])->name('settings.collaborators.professional.update');
        Route::delete('settings/collaborators/{membership}', [CollaboratorController::class, 'revoke'])->name('settings.collaborators.revoke');
        Route::post('settings/collaborators/{membership}/resend-access', [CollaboratorController::class, 'resendAccess'])->middleware('throttle:collaborator-access')->name('settings.collaborators.access.resend');
    });
});

Route::middleware(['auth', 'verified', 'tenant.context', 'saas.access', 'first.login.complete', RequireIntegrationManagement::class])
    ->prefix('settings/integrations')
    ->name('integration-credentials.')
    ->group(function (): void {
        Route::get('api-credentials', [IntegrationCredentialController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('index');
        Route::post('{purpose}/api-credentials', [IntegrationCredentialController::class, 'store'])
            ->where('purpose', 'credentials\.issue')
            ->middleware(['throttle:5,1', RequirePasskeyStepUp::class])
            ->name('store');
        Route::delete('{purpose}/api-credentials/{credential}', [IntegrationCredentialController::class, 'destroy'])
            ->where('purpose', 'credentials\.revoke')
            ->middleware(['throttle:5,1', RequirePasskeyStepUp::class])
            ->name('destroy');
        Route::delete('{purpose}/oauth-grants/{grant}', [IntegrationOAuthGrantController::class, 'destroy'])
            ->where('purpose', 'oauth\.revoke')
            ->whereUuid('grant')
            ->middleware(['throttle:5,1', RequirePasskeyStepUp::class])
            ->name('oauth-grants.destroy');
    });

Route::middleware(['auth', 'verified', 'tenant.context', 'saas.access', 'first.login.complete', RequireIntegrationManagement::class])
    ->prefix('settings/integrations/proposals')
    ->name('integration-proposals.')
    ->group(function (): void {
        Route::get('{proposal}', [ProposedOperationController::class, 'show'])
            ->whereUuid('proposal')
            ->middleware('throttle:30,1')
            ->name('show');
        Route::post('{proposal}/confirm', [ProposedOperationController::class, 'confirm'])
            ->whereUuid('proposal')
            ->middleware(['throttle:5,1', RequirePasskeyStepUp::class.':operations.confirm'])
            ->name('confirm');
        Route::post('{proposal}/reject', [ProposedOperationController::class, 'reject'])
            ->whereUuid('proposal')
            ->middleware('throttle:5,1')
            ->name('reject');
    });

Route::middleware(['auth', 'verified', 'tenant.context', 'saas.access', 'first.login.complete', RequireIntegrationManagement::class])
    ->prefix('integrations/step-up')
    ->name('integrations.step-up.')
    ->group(function (): void {
        Route::get('{purpose}/options', [StepUpPasskeyController::class, 'options'])
            ->where('purpose', '(credentials\\.issue|credentials\\.revoke|oauth\\.consent|oauth\\.revoke|operations\\.confirm)')
            ->middleware('throttle:6,1')
            ->name('options');
        Route::post('{purpose}', [StepUpPasskeyController::class, 'verify'])
            ->where('purpose', '(credentials\\.issue|credentials\\.revoke|oauth\\.consent|oauth\\.revoke|operations\\.confirm)')
            ->middleware('throttle:6,1')
            ->name('verify');
    });

Route::middleware(['auth', 'verified', 'tenant.context', 'saas.access', 'first.login.complete', RequireIntegrationManagement::class, EnsureAssistantEnabled::class])
    ->prefix('assistant')
    ->name('assistant.')
    ->group(function (): void {
        Route::get('/', [AssistantController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('index');
        Route::post('conversations', [AssistantController::class, 'storeConversation'])
            ->middleware('throttle:10,1')
            ->name('conversations.store');
        Route::get('conversations/{conversation}', [AssistantController::class, 'show'])
            ->whereUuid('conversation')
            ->middleware('throttle:30,1')
            ->name('show');
        Route::post('conversations/{conversation}/messages', [AssistantController::class, 'send'])
            ->whereUuid('conversation')
            ->middleware('throttle:20,1')
            ->name('messages.store');
        Route::delete('conversations/{conversation}', [AssistantController::class, 'destroy'])
            ->whereUuid('conversation')
            ->middleware('throttle:10,1')
            ->name('destroy');
    });

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
