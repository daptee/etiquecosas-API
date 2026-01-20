<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
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
            subject: 'Nuevo formulario de contacto desde la web',
            replyTo: [
                new Address($this->mailData['email'], $this->mailData['nombre']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contactForm',
            with: [
                'nombre' => $this->mailData['nombre'],
                'email' => $this->mailData['email'],
                'telefono' => $this->mailData['telefono'],
                'motivo' => $this->mailData['motivo'],
                'comentarios' => $this->mailData['comentarios'],
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
