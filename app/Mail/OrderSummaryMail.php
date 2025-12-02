<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sale;

    public function __construct($sale)
    {
        $this->sale = $sale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Resumen de tu compra - Pedido #{$this->sale->id}",
            replyTo: ['info@etiquecosas.com.ar'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orderSummary',
            with: [
                'sale' => $this->sale,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
