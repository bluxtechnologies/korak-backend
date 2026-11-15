<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeviceVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
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
        return ['mail', 'database'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $frontendUrl = (string) config('app.frontend_url', 'http://localhost:3000');
        $devicesUrl = rtrim($frontendUrl, '/').'/settings/security';

        $details = [
            'Device' => $this->deviceName,
            'Timestamp (UTC)' => Carbon::now('UTC')->toDateTimeString().' UTC',
        ];

        if ($this->ipAddress) {
            $details['IP Address'] = $this->ipAddress;
        }

        return (new MailMessage)
            ->subject('Security Alert: New Device Verified — Korak Digitals')
            ->action('Manage Devices', $devicesUrl)
            ->markdown('mail.security.security-alert', [
                'title' => 'New Device Verified',
                'intro' => "A sign-in from an unrecognized device ({$this->deviceName}) was successfully verified via OTP.",
                'details' => $details,
                'actionText' => 'Manage Trusted Devices',
                'actionUrl' => $devicesUrl,
                'warning' => 'If you did not authorize this login, someone else may have accessed your account. Revoke this device immediately in your Settings and change your password.',
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
            'title' => 'New Device Verified',
            'message' => "A new device ({$this->deviceName}) was verified on your account.",
            'category' => 'security',
            'device_name' => $this->deviceName,
            'ip_address' => $this->ipAddress,
        ];
    }
}
