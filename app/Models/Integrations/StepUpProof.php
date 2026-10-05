<?php

namespace App\Models\Integrations;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'user_id', 'tenant_id', 'unit_id', 'passkey_id', 'factor', 'purpose', 'target_id', 'command_hash', 'verified_at', 'expires_at', 'consumed_at', 'invalidated_at'])]
class StepUpProof extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
        ];
    }
}
