<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $nombre;
    public $apellido;
    public $email;
    public $telefono;
    public $cantidad;
    public $texto;
    public $nombreProducto;

    public function __construct($nombre, $apellido, $email, $telefono, $cantidad, $texto, $nombreProducto)
    {
        $this->nombre = $nombre;
        $this->apellido = $apellido;
        $this->email = $email;
        $this->telefono = $telefono;
        $this->cantidad = $cantidad;
        $this->texto = $texto;
        $this->nombreProducto = $nombreProducto;
    }

    public function build()
    {
        return $this->subject('Consulta de producto - ' . $this->nombreProducto)
                    ->view('emails.productInquiry');
    }
}
