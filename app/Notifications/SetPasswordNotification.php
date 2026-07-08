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
     * Monta o e-mail de credenciais/definição de senha usando o template HTML
     * customizado da marca Plannerate (resources/views/emails/credentials.blade.php).
     */
    public function toMail(object $notifiable): MailMessage
    {
        // URL base do sistema (home do tenant), derivada da própria URL de setup,
        // para exibir no cartão "Link do sistema" e como fallback do logo.
        $parts = parse_url($this->setupUrl);
        $systemUrl = isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : (string) config('app.url');

        return (new MailMessage)
            ->subject($this->isResend
                ? __('app.password_setup.mail.resend_subject')
                : __('app.password_setup.mail.subject'))
            ->view(['emails.credentials', 'emails.credentials-text'], [
                'name' => $notifiable->name,
                'username' => $notifiable->email,
                'systemUrl' => $systemUrl,
                'actionUrl' => $this->setupUrl,
                'expiryDays' => (int) config('password_setup.code_ttl_days', 7),
                'isResend' => $this->isResend,
            ]);
    }
}
