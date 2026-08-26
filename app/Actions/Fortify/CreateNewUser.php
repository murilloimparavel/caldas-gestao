<?php

namespace App\Actions\Fortify;

use App\Actions\Identity\OnboardTenant;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly OnboardTenant $onboardTenant) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $workspaceName = trim($user->name).' Workspace';
            $workspaceSlug = Str::slug($workspaceName).'-'.Str::lower(Str::substr((string) $user->getKey(), 0, 8));

            $this->onboardTenant->handle(
                $user,
                [
                    'name' => $workspaceName,
                    'slug' => $workspaceSlug,
                ],
                [
                    'name' => trim($user->name).' Unit',
                    'slug' => $workspaceSlug.'-unit',
                ],
            );

            return $user;
        }, 5);
    }
}
