<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackupAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $message,
        public array $details = [],
    ) {
        //
    }

    public function build(): static
    {
        $detailsHtml = collect($this->details)
            ->map(fn (string $value, string $key) => '<li><strong>'.e(ucwords(str_replace('_', ' ', $key))).':</strong> '.e($value).'</li>')
            ->implode('');

        return $this
            ->subject($this->title)
            ->html('<h2>'.e($this->title).'</h2><p>'.e($this->message).'</p>'.($detailsHtml !== '' ? '<ul>'.$detailsHtml.'</ul>' : ''));
    }
}
