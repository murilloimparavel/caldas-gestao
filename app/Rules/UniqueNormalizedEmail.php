<?php

namespace App\Rules;

use App\Models\User;
use App\Support\CanonicalEmail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueNormalizedEmail implements ValidationRule
{
    public function __construct(private readonly ?string $ignoredUserId = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $query = User::query()->where('email_normalized', CanonicalEmail::normalize($value));

        if ($this->ignoredUserId !== null) {
            $query->whereKeyNot($this->ignoredUserId);
        }

        if ($query->exists()) {
            $fail('validation.unique')->translate();
        }
    }
}
