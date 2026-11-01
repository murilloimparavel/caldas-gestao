<?php

namespace App\Http\Controllers;

use App\Models\AgendaNotification;
use App\Models\Appointment;
use App\Support\NotificationInbox;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(TenantContext $context): Response
    {
        return Inertia::render('notifications/index', [
            'notifications' => app(NotificationInbox::class)->query($context)->latest()->paginate(25)->through(fn (AgendaNotification $notification): array => $notification->only(['id', 'category', 'title', 'body', 'read_at', 'created_at', 'appointment_id'])),
            'unreadCount' => app(NotificationInbox::class)->query($context)->whereNull('read_at')->count(),
        ]);
    }

    public function read(AgendaNotification $notification, TenantContext $context): RedirectResponse
    {
        $this->owned($notification, $context);
        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        return back();
    }

    public function readAll(TenantContext $context): RedirectResponse
    {
        app(NotificationInbox::class)->query($context)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function open(AgendaNotification $notification, TenantContext $context): RedirectResponse
    {
        abort_unless($notification->user_id === $context->user->id, 404);
        $context = TenantContext::forUser($context->user, $notification->tenant_id, $notification->unit_id);
        $this->owned($notification, $context);
        $appointment = null;
        if ($notification->appointment_id !== null) {
            $appointment = Appointment::query()->where('tenant_id', $context->tenant->id)->where('unit_id', $context->unit->id)->whereKey($notification->appointment_id)->firstOrFail();
            Gate::authorize('view', $appointment);
        }
        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();
        request()->session()->put(['tenant_id' => $context->tenant->id, 'unit_id' => $context->unit->id]);
        if ($appointment === null) {
            return to_route('notifications.index');
        }

        return to_route('calendar.index', ['date' => $appointment->starts_at->timezone($appointment->timezone)->toDateString(), 'view' => 'day']);
    }

    private function owned(AgendaNotification $notification, TenantContext $context): void
    {
        abort_unless(app(NotificationInbox::class)->query($context)->whereKey($notification->id)->exists(), 404);
    }
}
