<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityAlert extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public string $alertType;

    public array $data;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $alertType, array $data = [])
    {
        $this->user = $user;
        $this->alertType = $alertType;
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->alertType) {
            'failed_login' => 'Security Alert: Multiple Failed Login Attempts',
            'password_changed' => 'Security Alert: Password Changed',
            'email_changed' => 'Security Alert: Email Address Changed',
            default => 'Security Alert',
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
            view: 'emails.security-alert',
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
