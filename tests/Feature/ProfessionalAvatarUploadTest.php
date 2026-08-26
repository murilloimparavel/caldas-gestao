<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
});

/** @return array{0: User, 1: Tenant, 2: Unit} */
function professionalAvatarWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    test()->withHeaders([
        'X-Tenant-Id' => $tenant->getKey(),
        'X-Unit-Id' => $unit->getKey(),
    ]);

    return [$owner, $tenant, $unit];
}

it('uploads professional avatar successfully on create', function () {
    [$owner, $tenant] = professionalAvatarWorkspace();

    $avatarFile = UploadedFile::fake()->image('avatar.jpg');
    $response = $this->actingAs($owner)->post(route('professionals.store'), [
        'name' => 'Dr. João Silva',
        'email' => null,
        'phone' => '11999998888',
        'avatar' => $avatarFile,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $professional = Professional::query()->where('name', 'Dr. João Silva')->firstOrFail();
    expect($professional->avatar_path)->not()->toBeNull()
        ->and($professional->avatar_path)->toContain("{$tenant->getKey()}/professionals/{$professional->getKey()}/")
        ->and($professional->avatar_url)->toBe(Storage::disk('public')->url($professional->avatar_path));

    Storage::disk('public')->assertExists($professional->avatar_path);
});

it('replaces old avatar on update and deletes old file from storage', function () {
    [$owner] = professionalAvatarWorkspace();

    $oldAvatarFile = UploadedFile::fake()->image('old_avatar.jpg');
    $this->actingAs($owner)->post(route('professionals.store'), [
        'name' => 'Dra. Maria Santos',
        'avatar' => $oldAvatarFile,
    ]);

    $professional = Professional::query()->where('name', 'Dra. Maria Santos')->firstOrFail();
    $oldAvatarPath = $professional->avatar_path;
    Storage::disk('public')->assertExists($oldAvatarPath);

    $newAvatarFile = UploadedFile::fake()->image('new_avatar.png');
    $this->actingAs($owner)->put(route('professionals.update', $professional), [
        'name' => 'Dra. Maria Santos Silva',
        'lock_version' => $professional->lock_version,
        'avatar' => $newAvatarFile,
    ]);

    $professional->refresh();
    expect($professional->avatar_path)->not()->toBe($oldAvatarPath);
    Storage::disk('public')->assertMissing($oldAvatarPath);
    Storage::disk('public')->assertExists($professional->avatar_path);
});

it('rejects files with invalid types or size exceeding 5MB', function () {
    [$owner] = professionalAvatarWorkspace();

    $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $this->actingAs($owner)
        ->post(route('professionals.store'), [
            'name' => 'Profissional Inválido',
            'avatar' => $pdfFile,
        ])
        ->assertSessionHasErrors(['avatar']);

    $largeFile = UploadedFile::fake()->image('huge_avatar.png')->size(5121);
    $this->actingAs($owner)
        ->post(route('professionals.store'), [
            'name' => 'Profissional Avatar Grande',
            'avatar' => $largeFile,
        ])
        ->assertSessionHasErrors(['avatar']);
});
