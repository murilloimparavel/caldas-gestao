<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MembershipInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $tenantName, private readonly string $loginUrl)
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->theme('caldas')
            ->subject('Você recebeu um convite para '.$this->tenantName)
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Você foi convidado para acessar o espaço '.$this->tenantName.' no '.config('branding.name').'.')
            ->line('Entre com sua conta para continuar e ver os acessos liberados para você.')
            ->action('Acessar o sistema', $this->loginUrl)
            ->line('Se você não esperava este convite, fale com o responsável pelo espaço.')
            ->salutation('Até logo,<br>'.config('branding.name'));
    }
}
