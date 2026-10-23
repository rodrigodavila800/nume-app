<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $tenantSlug,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
            'tenant_slug' => $this->tenantSlug,
        ]);

        return (new MailMessage)
            ->subject('RedefiniÃ§Ã£o de senha')
            ->line('VocÃª estÃ¡ recebendo este e-mail porque solicitou a redefiniÃ§Ã£o de senha.')
            ->action('Redefinir senha', $url)
            ->line('Se vocÃª nÃ£o solicitou esta redefiniÃ§Ã£o, nenhuma aÃ§Ã£o Ã© necessÃ¡ria.');
    }
}
