<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderSummaryMailTo extends Mailable
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
            subject: "Nueva venta - Pedido #{$this->sale->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifyOrderSummary',
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
