<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CadeteDeliveryNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sale;
    public $cadete;

    public function __construct($sale, $cadete)
    {
        $this->sale = $sale;
        $this->cadete = $cadete;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pedido entregado por cadete - Pedido #{$this->sale->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cadeteDeliveryNotification',
            with: [
                'sale'   => $this->sale,
                'cadete' => $this->cadete,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
