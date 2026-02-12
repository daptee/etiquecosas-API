<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderModificationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData;

    public function __construct($mailData)
    {
        $this->mailData = $mailData;
    }

    public function envelope(): Envelope
    {
        Log::info($this->mailData);
        return new Envelope(
            subject: "Solicitud de modificación del pedido #{$this->mailData['order_id']}",
            replyTo: [
                new Address($this->mailData['client_email'], $this->mailData['client_name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orderModificationRequest',
            with: [
                'clientName' => $this->mailData['client_name'],
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
