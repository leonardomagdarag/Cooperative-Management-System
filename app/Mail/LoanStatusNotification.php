<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanStatusNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Loan $loan;
    public string $status;
    public ?string $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(Loan $loan, string $status, ?string $reason = null)
    {
        $this->loan = $loan;
        $this->status = $status;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->status) {
            'approved' => 'Congratulations! Your Loan Application Has Been Approved',
            'rejected' => 'Loan Application Status Update',
            default => 'Loan Application Status Update',
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
            view: 'emails.loan-status',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
