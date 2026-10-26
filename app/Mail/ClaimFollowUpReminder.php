<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ClaimFollowUpReminder extends Mailable
{
    use Queueable;

    public function __construct(
        public string $claimNumber,
        public string $subjectLine,
        public string $detailsUrl,
        public string $lastUpdated,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Follow-up needed for claim '.$this->claimNumber);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.claims.follow-up-reminder');
    }
}
