<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ClaimStatusUpdated extends Mailable
{
    use Queueable;

    public function __construct(
        public string $claimNumber,
        public string $status,
        public string $dtcCardNo,
        public ?string $customerMessage,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Warranty claim status update: '.$this->claimNumber);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.claims.status-updated');
    }
}
