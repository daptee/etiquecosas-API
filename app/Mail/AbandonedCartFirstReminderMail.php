<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartFirstReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sale;
    public $cartUid;

    public function __construct($sale, $cartUid)
    {
        $this->sale = $sale;
        $this->cartUid = $cartUid;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¿Te olvidaste algo? Tu carrito te espera 🛒',
            replyTo: ['info@etiquecosas.com.ar'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abandonedCartFirstReminder',
            with: [
                'sale' => $this->sale,
                'cartUid' => $this->cartUid,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
