<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteNotification extends Notification
{
    public function __construct(public string $url, public string $portfolioName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = intdiv((int) config('auth.passwords.invites.expire'), 60);

        return (new MailMessage)
            ->subject('Convite para a equipe do portfólio')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Você foi convidado(a) para colaborar no portfólio "'.$this->portfolioName.'".')
            ->line('Clique no botão abaixo para definir sua própria senha. Ninguém além de você terá acesso a ela.')
            ->action('Definir minha senha', $this->url)
            ->line("O link vale por {$hours} horas e só pode ser usado uma vez.")
            ->line('Se você não esperava este convite, ignore este e-mail.')
            ->salutation('Equipe do portfólio');
    }
}
