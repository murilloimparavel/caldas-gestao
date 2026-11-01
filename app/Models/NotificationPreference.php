<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'user_id', 'new_appointments', 'changes', 'cancellations', 'reminders', 'reminder_minutes', 'quiet_start', 'quiet_end', 'push_invitation_dismissed_at'])]
class NotificationPreference extends Model
{
    use HasUuids;

    protected $attributes = ['new_appointments' => true, 'changes' => true, 'cancellations' => true, 'reminders' => true, 'reminder_minutes' => 15];

    protected function casts(): array
    {
        return ['new_appointments' => 'boolean', 'changes' => 'boolean', 'cancellations' => 'boolean', 'reminders' => 'boolean', 'reminder_minutes' => 'integer', 'push_invitation_dismissed_at' => 'datetime'];
    }
}
