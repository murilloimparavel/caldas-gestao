<?php

namespace App\Auth;

use App\Support\CanonicalEmail;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class NormalizedEmailUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user while keeping the public Fortify username as `email`.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        if (is_string($credentials['email'] ?? null)) {
            $credentials['email_normalized'] = CanonicalEmail::normalize($credentials['email']);
            unset($credentials['email']);
        }

        return parent::retrieveByCredentials($credentials);
    }
}
