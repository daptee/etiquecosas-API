<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShippingClaimMail extends Mailable
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
            subject: "Reclamo de envío - Pedido #{$this->mailData['order_id']}",
            replyTo: [
                new Address($this->mailData['client_email'], $this->mailData['client_name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shippingClaim',
            with: [
                'clientName' => $this->mailData['client_name'],
                'clientEmail' => $this->mailData['client_email'],
                'orderId' => $this->mailData['order_id'],
                'clientMessage' => $this->mailData['message'],
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
