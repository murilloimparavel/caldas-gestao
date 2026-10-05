<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantRun extends Model
{
    use HasUuids;

    protected $fillable = ['conversation_id', 'message_id', 'user_id', 'tenant_id', 'unit_id', 'status', 'provider', 'model', 'tool_calls', 'result', 'error', 'started_at', 'completed_at', 'expires_at'];

    protected function casts(): array
    {
        return ['result' => 'array', 'tool_calls' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<AssistantConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class);
    }

    /** @return BelongsTo<AssistantMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class);
    }
}
