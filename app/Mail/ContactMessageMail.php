<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $message,
    ) {}

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact-message',
        );
    }

    public function build(): self
    {
        return $this->subject('New Contact Message from ' . $this->name);
    }
}
