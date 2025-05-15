<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
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
            subject: 'Etiquecosas - Recuperación de contraseña',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.forgotPassword',
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