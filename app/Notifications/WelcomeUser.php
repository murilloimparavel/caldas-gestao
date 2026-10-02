<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WelcomeUser extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->theme('caldas')
            ->subject('Bem-vindo ao '.config('branding.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Sua conta no '.config('branding.name').' foi criada com sucesso.')
            ->line('Confirme seu e-mail para liberar seu acesso e começar a usar o sistema.')
            ->action('Acessar o sistema', route('login'))
            ->line('Se precisar de ajuda, fale com o responsável pelo seu espaço.')
            ->salutation('Até logo,<br>'.config('branding.name'));
    }
}
