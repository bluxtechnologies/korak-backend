<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Security Alert: Your Password Was Changed — Korak Digitals')
            ->action('Reset Password', $forgotPasswordUrl)
            ->markdown('mail.security.security-alert', [
                'title' => 'Password Changed Successfully',
                'intro' => 'The password for your Korak Digitals account was recently changed. If you made this change, no further action is required.',
                'details' => [
                    'Event' => 'Password update',
                    'Timestamp (UTC)' => Carbon::now('UTC')->toDateTimeString().' UTC',
                    'Status' => 'Active',
                ],
                'actionText' => 'Secure Your Account',
                'actionUrl' => $forgotPasswordUrl,
                'warning' => 'If you did not initiate this change, someone else may have gained access to your account. Please reset your password immediately and contact support.',
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
            'title' => 'Password Changed',
            'message' => 'Your account password was successfully updated.',
            'category' => 'security',
        ];
    }
}
