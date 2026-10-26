<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ClaimAssignedToStaff extends Mailable
{
    use Queueable;

    public function __construct(
        public string $staffName,
        public string $claimNumber,
        public string $claimSubject,
        public string $assignedBy,
        public string $detailsUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Claim assigned to you: '.$this->claimNumber);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.claims.assigned-to-staff');
    }
}
