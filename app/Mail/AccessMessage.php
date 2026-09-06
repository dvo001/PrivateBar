<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class AccessMessage extends Mailable
{
    public function __construct(public string $kind, public string $accessUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'invite'
            ? 'Deine Einladung zu PrivateBar'
            : 'Bestätige deine E-Mail-Adresse für PrivateBar');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.access', text: 'mail.access-text');
    }
}
