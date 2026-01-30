<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class StalledProductionAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $sales;
    public int $businessDays;

    public function __construct(Collection $sales, int $businessDays = 11)
    {
        $this->sales = $sales;
        $this->businessDays = $businessDays;
    }

    public function build()
    {
        $count = $this->sales->count();
        $subject = $count === 1
            ? "Alerta: Venta #{$this->sales->first()->id} lleva {$this->businessDays} días hábiles en producción"
            : "Alerta: {$count} ventas llevan {$this->businessDays} días hábiles en producción";

        return $this->subject($subject)
                    ->view('emails.stalledProductionAlert');
    }
}
