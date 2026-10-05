<?php

use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

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
        Route::post('settings/collaborators', [CollaboratorController::class, 'store'])->name('settings.collaborators.store');
        Route::post('settings/collaborators/roles', [CollaboratorController::class, 'storeRole'])->name('settings.collaborators.roles.store');
        Route::patch('settings/collaborators/roles/{role}', [CollaboratorController::class, 'updateRole'])->name('settings.collaborators.roles.update');
        Route::post('settings/collaborators/{membership}/role', [CollaboratorController::class, 'assignRole'])->name('settings.collaborators.role.assign');
        Route::patch('settings/collaborators/{membership}/professional', [CollaboratorController::class, 'linkProfessional'])->name('settings.collaborators.professional.update');
        Route::delete('settings/collaborators/{membership}', [CollaboratorController::class, 'revoke'])->name('settings.collaborators.revoke');
        Route::post('settings/collaborators/{membership}/resend-access', [CollaboratorController::class, 'resendAccess'])->name('settings.collaborators.access.resend');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
