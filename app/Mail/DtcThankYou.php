<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DtcThankYou extends Mailable
{
    use Queueable;

    public function __construct(
        public array $data,
        public string $emailSubject,
        public ?string $unsubscribeUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registrations.thank-you');
    }
}
