<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailConfigurationTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function build(): static
    {
        return $this
            ->subject('Email Configuration Test')
            ->html('<p>This test email confirms the cooperative system can send mail successfully.</p>');
    }
}
