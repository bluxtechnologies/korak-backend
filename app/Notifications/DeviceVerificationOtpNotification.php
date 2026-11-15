<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeviceVerificationOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        #[\SensitiveParameter] public string $code,
        public string $deviceName = 'Unknown Device',
        public ?string $ipAddress = null,
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
        return (new MailMessage)
            ->subject('Your Verification Code — Korak Digitals')
            ->markdown('mail.auth.verify-device', [
                'code' => $this->code,
                'deviceName' => $this->deviceName,
                'ipAddress' => $this->ipAddress,
            ]);
    }
}
