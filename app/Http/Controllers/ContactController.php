<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    use ApiResponse;

    public function sendContactForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'required|string|max:50',
            'motivo' => 'required|string|max:500',
            'comentarios' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $mailData = [
            'nombre' => $request->nombre,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'motivo' => $request->motivo,
            'comentarios' => $request->comentarios,
        ];

        Mail::to('info@etiquecosas.com.ar')->send(new ContactFormMail($mailData));

        return $this->success(null, 'Formulario de contacto enviado correctamente');
    }
}
