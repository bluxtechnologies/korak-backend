<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public SupportTicket $ticket,
        public string $replySnippet,
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
        $ticketUrl = rtrim($frontendUrl, '/').'/support/'.$this->ticket->ulid;

        return (new MailMessage)
            ->subject("New Reply on Ticket {$this->ticket->reference} — Korak Digitals")
            ->line("A support agent has replied to your ticket: \"{$this->ticket->subject}\"")
            ->line("\"{$this->replySnippet}\"")
            ->action('View Ticket Thread', $ticketUrl)
            ->line('Thank you for choosing Korak Digitals.');
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => "New Reply on Ticket {$this->ticket->reference}",
            'message' => "Support replied: \"{$this->replySnippet}\"",
            'category' => 'support',
            'ticket_id' => $this->ticket->ulid,
            'ticket_reference' => $this->ticket->reference,
        ];
    }
}
