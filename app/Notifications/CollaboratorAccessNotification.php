<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CollaboratorAccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $tenantName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->name : 'colaborador';

        return (new MailMessage)
            ->subject("Acesso ao {$this->tenantName}")
            ->greeting("Olá, {$name}!")
            ->line("Você recebeu acesso ao {$this->tenantName}.")
            ->action('Acessar sistema', route('login'))
            ->line('Use sua senha atual para entrar e acessar as funcionalidades liberadas para você.');
    }
}
