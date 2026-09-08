<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberApplicationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?User $user;

    public string $status;

    public string $reason;

    public ?string $activationUrl;

    public string $applicantName;

    /**
     * Create a new message instance.
     */
    public function __construct(
        ?User $user,
        string $status,
        string $reason = '',
        ?string $activationUrl = null,
        ?string $applicantName = null
    ) {
        $this->user = $user;
        $this->status = $status;
        $this->reason = $reason;
        $this->activationUrl = $activationUrl;
        $this->applicantName = $applicantName ?: ($user?->name ?? '');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->status === 'approved'
                ? 'Your Membership Application Has Been Approved'
                : 'Update on Your Membership Application',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.member-status',
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
