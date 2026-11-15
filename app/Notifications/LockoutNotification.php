<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LockoutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $durationMinutes = 15
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $frontendUrl = (string) config('app.frontend_url', 'http://localhost:3000');
        $forgotPasswordUrl = rtrim($frontendUrl, '/').'/auth/forgot-password';

        return (new MailMessage)
            ->subject('Security Alert: Account Temporarily Locked — Korak Digitals')
            ->action('Reset Password', $forgotPasswordUrl)
            ->markdown('mail.security.security-alert', [
                'title' => 'Security Alert: Account Locked',
                'intro' => 'Your Korak Digitals account has been temporarily locked due to 5 consecutive failed login attempts.',
                'details' => [
                    'Reason' => '5 consecutive failed password attempts',
                    'Lockout Duration' => $this->durationMinutes.' minutes',
                    'Status' => 'Temporarily locked',
                ],
                'actionText' => 'Reset Password',
                'actionUrl' => $forgotPasswordUrl,
                'warning' => 'If you did not attempt these logins, someone may be attempting to guess your password. We recommend resetting your password immediately once your account unlocks.',
            ]);
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => 'Account Temporarily Locked',
            'message' => 'Your account has been temporarily locked for '.$this->durationMinutes.' minutes due to 5 consecutive failed login attempts.',
            'category' => 'security',
            'duration_minutes' => $this->durationMinutes,
        ];
    }
}
