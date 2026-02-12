<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderAddressChangeMail extends Mailable
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
            subject: "Solicitud de cambio de dirección del pedido #{$this->mailData['order_id']}",
            replyTo: [
                new Address($this->mailData['client_email'], $this->mailData['client_name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orderAddressChange',
            with: [
                'clientName' => $this->mailData['client_name'],
                'orderId' => $this->mailData['order_id'],
                'newAddress' => $this->mailData['new_address'],
                'clientMessage' => $this->mailData['message'] ?? null,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
