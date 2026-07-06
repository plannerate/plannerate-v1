<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação por email pedindo para o usuário definir sua senha de acesso.
 *
 * Não implementa NotTenantAware de propósito: esta notificação está sempre atrelada
 * a um usuário de um tenant específico, então o comportamento padrão do pacote
 * (queues_are_tenant_aware_by_default) é o desejado — o job na fila restaura
 * sozinho o tenant/conexão corretos quando executa.
 */
class SetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $setupUrl,
        public readonly bool $isResend = false,
    ) {}

    /**
     * Canal único: email.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Monta o email de definição de senha usando o MailMessage padrão do Laravel
     * (não há template de email customizado neste projeto ainda).
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->isResend
                ? __('app.password_setup.mail.resend_subject')
                : __('app.password_setup.mail.subject'))
            ->greeting(__('app.password_setup.mail.greeting', ['name' => $notifiable->name]))
            ->line($this->isResend
                ? __('app.password_setup.mail.resend_intro')
                : __('app.password_setup.mail.intro'))
            ->action(__('app.password_setup.mail.action'), $this->setupUrl)
            ->line(__('app.password_setup.mail.expiry', ['days' => (string) (int) config('password_setup.code_ttl_days', 7)]));
    }
}
