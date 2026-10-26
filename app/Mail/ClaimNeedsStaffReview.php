<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ClaimNeedsStaffReview extends Mailable
{
    use Queueable;

    public function __construct(
        public string $claimNumber,
        public string $claimSubject,
        public string $detailsUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New warranty claim: '.$this->claimNumber);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.claims.staff-review');
    }
}
