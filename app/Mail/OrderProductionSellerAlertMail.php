<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OrderProductionSellerAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $sales;
    public int $days;

    public function __construct(Collection $sales, int $days = 10)
    {
        $this->sales = $sales;
        $this->days = $days;
    }

    public function build()
    {
        $count = $this->sales->count();
        $subject = $count === 1
            ? "Aviso al vendedor: Pedido #{$this->sales->first()->id} lleva {$this->days} días en producción"
            : "Aviso al vendedor: {$count} pedidos llevan {$this->days} días en producción";

        return $this->subject($subject)
                    ->view('emails.orderProductionSellerAlert');
    }
}
