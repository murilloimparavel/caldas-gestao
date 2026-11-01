<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'user_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'origin', 'session_hash', 'device_name'])]
class PushSubscription extends Model
{
    use HasUuids;

    protected $attributes = ['content_encoding' => 'aes128gcm'];

    protected $hidden = ['endpoint', 'public_key', 'auth_token', 'session_hash'];

    protected function casts(): array
    {
        return ['endpoint' => 'encrypted', 'public_key' => 'encrypted', 'auth_token' => 'encrypted'];
    }
}
