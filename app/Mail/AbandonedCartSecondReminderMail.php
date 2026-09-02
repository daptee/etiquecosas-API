<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartSecondReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sale;
    public $coupon;
    public $cartUid;

    public function __construct($sale, $coupon, $cartUid)
    {
        $this->sale = $sale;
        $this->coupon = $coupon;
        $this->cartUid = $cartUid;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Último aviso: 15% OFF + envío gratis en tu compra 🎁',
            replyTo: ['info@etiquecosas.com.ar'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abandonedCartSecondReminder',
            with: [
                'sale' => $this->sale,
                'coupon' => $this->coupon,
                'cartUid' => $this->cartUid,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
