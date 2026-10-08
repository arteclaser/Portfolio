<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject('Recuperação de acesso ao portfólio')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Definir nova senha', $url)
            ->line('O link vale por '.config('auth.passwords.users.expire').' minutos.')
            ->line('Se você não fez este pedido, nenhuma ação é necessária: sua senha atual continua valendo.')
            ->salutation('Equipe do portfólio');
    }
}
