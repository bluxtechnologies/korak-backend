<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletAdjustmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $action, // 'credit' or 'debit'
        public int $amountInKobo,
        public string $reason,
        public string $currency = 'NGN',
        public ?string $reference = null,
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
        $formatted = number_format($this->amountInKobo / 100, 2);
        $verb = $this->action === 'credit' ? 'credited' : 'debited';
        $title = $this->action === 'credit' ? 'Wallet Credited' : 'Wallet Debited';

        $mail = (new MailMessage)
            ->subject("{$title} Notice — Korak Digitals")
            ->line("Your Korak Digitals wallet has been {$verb} with {$this->currency} {$formatted}.")
            ->line("Reason: {$this->reason}");

        if ($this->reference) {
            $mail->line("Reference: {$this->reference}");
        }

        return $mail->line('Thank you for choosing Korak Digitals.');
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        $formatted = number_format($this->amountInKobo / 100, 2);
        $verb = $this->action === 'credit' ? 'credited' : 'debited';

        return [
            'title' => 'Wallet '.ucfirst($this->action),
            'message' => "Your wallet was {$verb} with {$this->currency} {$formatted}. Reason: {$this->reason}",
            'action' => $this->action,
            'amount_kobo' => $this->amountInKobo,
            'reference' => $this->reference,
            'reason' => $this->reason,
        ];
    }
}
