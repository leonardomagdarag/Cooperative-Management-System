<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WithdrawalStatusNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Transaction $withdrawal;

    public string $status;

    public ?string $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(Transaction $withdrawal, string $status, ?string $reason = null)
    {
        $this->withdrawal = $withdrawal;
        $this->status = $status;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->status) {
            'approved' => 'Withdrawal Request Approved',
            'rejected' => 'Withdrawal Request Status Update',
            default => 'Withdrawal Request Status Update',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.withdrawal-status',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
