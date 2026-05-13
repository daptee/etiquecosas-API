<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public Product $product;
    public array $alerts;

    public function __construct(Product $product, array $alerts)
    {
        $this->product = $product;
        $this->alerts  = $alerts;
    }

    public function build()
    {
        $count = count($this->alerts);
        $subject = $count === 1
            ? "Alerta de stock bajo: {$this->product->name}"
            : "Alerta de stock bajo ({$count} casos): {$this->product->name}";

        return $this->subject($subject)
                    ->view('emails.stockAlert');
    }
}
