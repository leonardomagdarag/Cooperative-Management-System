<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public string $purpose;

    public string $otpCode;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $purpose, string $otpCode)
    {
        $this->user = $user;
        $this->purpose = $purpose;
        $this->otpCode = $otpCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->purpose) {
            'new_device_login' => 'Verify your new device login',
            'withdrawal_approval' => 'OTP required for withdrawal approval',
            default => 'Your security verification code',
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
            view: 'emails.otp-code',
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
