<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData;

    public function __construct($mailData)
    {
        $this->mailData = $mailData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Etiquecosas - Tu contraseña fue restablecida',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.passwordResetNotice',
            with: [
                'name' => $this->mailData['name'],
                'password' => $this->mailData['password'],
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
