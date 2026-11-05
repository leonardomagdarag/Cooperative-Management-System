<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanLifecycleNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan,
        public string $title,
        public string $bodyMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loan-lifecycle-update');
    }
}
