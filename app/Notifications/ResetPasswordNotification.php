<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a notification instance.
     */
    public function __construct(
        #[\SensitiveParameter] public string $token
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $frontendUrl = (string) config('app.frontend_url', 'http://localhost:3000');
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? (string) $notifiable->getEmailForPasswordReset()
            : (string) ($notifiable->email ?? '');

        $url = rtrim($frontendUrl, '/').'/auth/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);

        $expire = (int) config('auth.passwords.customers.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your Password — Korak Digitals')
            ->action('Reset Password', $url)
            ->markdown('mail.auth.reset-password', [
                'url' => $url,
                'email' => $email,
                'expireMinutes' => $expire,
            ]);
    }
}
