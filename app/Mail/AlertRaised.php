<?php

namespace App\Mail;

use App\Models\Alert;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AlertRaised extends Mailable
{
    public function __construct(public readonly Alert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[El3ktron] {$this->alert->severity->value}: {$this->alert->type}");
    }

    public function content(): Content
    {
        $context = e((string) json_encode($this->alert->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return new Content(htmlString: '<p>'.e($this->alert->message).'</p><pre>'.$context.'</pre>');
    }
}
