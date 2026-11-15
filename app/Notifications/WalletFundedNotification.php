<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletFundedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $amountInKobo,
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

        $mail = (new MailMessage)
            ->subject('Wallet Funded Successfully — Korak Digitals')
            ->line("Your Korak Digitals wallet has been credited with {$this->currency} {$formatted}.");

        if ($this->reference) {
            $mail->line("Transaction Reference: {$this->reference}");
        }

        return $mail->line('You can now use your wallet balance to purchase services on Korak Digitals.');
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        $formatted = number_format($this->amountInKobo / 100, 2);

        return [
            'title' => 'Wallet Credited',
            'message' => "Your wallet has been credited with {$this->currency} {$formatted}.",
            'category' => 'wallet',
            'amount' => $this->amountInKobo,
            'currency' => $this->currency,
            'reference' => $this->reference,
        ];
    }
}
