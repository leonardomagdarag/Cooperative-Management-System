<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanDueNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Loan $loan;
    public int $daysRemaining;
    public bool $isOverdue;

    /**
     * Create a new message instance.
     */
    public function __construct(Loan $loan, int $daysRemaining = 0, bool $isOverdue = false)
    {
        $this->loan = $loan;
        $this->daysRemaining = $daysRemaining;
        $this->isOverdue = $isOverdue;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isOverdue
            ? 'URGENT: Loan Payment Overdue'
            : "Loan Payment Due in {$this->daysRemaining} Day(s)";

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
            view: 'emails.loan-due',
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
