<?php

namespace App\Http\Controllers;

use App\Mail\OrderRetiredMail;
use App\Models\Sale;
use App\Models\SaleStatusHistory;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class CadeteController extends Controller
{
    use ApiResponse, Auditable;

    public function markAsDelivered(Request $request, $saleId)
    {
        $user = Auth::user();

        // Validar que el usuario sea cadete (profile_id = 4) o admin (profile_id = 1)
        $isAdmin = $user->profile_id === 1;
        $isCadete = $user->profile_id === 4;

        if (!$isAdmin && !$isCadete) {
            return $this->error('Solo los administradores o cadetes pueden marcar pedidos como entregados', 403);
        }

        // Validar datos del receptor
        $validator = Validator::make($request->all(), [
            'receiver_name' => 'required|string|max:255',
            'receiver_dni' => [
                'required',
                'string',
                'regex:/^\d{7,8}$|^\d{2}\.\d{3}\.\d{3}$/'
            ],
        ], [
            'receiver_name.required' => 'El nombre del receptor es obligatorio',
            'receiver_dni.required' => 'El DNI del receptor es obligatorio',
            'receiver_dni.regex' => 'El DNI debe tener formato argentino (7-8 dígitos o XX.XXX.XXX)',
        ]);

        if ($validator->fails()) {
            $this->logAudit($user, 'Cadete Mark Delivered Validation Fail', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Buscar la venta
        $sale = Sale::with(['client', 'shippingMethod', 'locality'])->find($saleId);

        if (!$sale) {
            return $this->notFound('Venta no encontrada');
        }

        // Si es cadete, validar que la venta esté asignada a él. Admin puede marcar cualquier venta.
        if ($isCadete && $sale->cadete_id !== $user->id) {
            $this->logAudit($user, 'Cadete Mark Delivered Unauthorized', ['sale_id' => $saleId], 'Venta no asignada a este cadete');
            return $this->error('Esta venta no está asignada a tu cuenta', 403);
        }

        // Validar que la venta no esté cancelada (estado 5)
        if ($sale->sale_status_id == 5) {
            return $this->error('No se puede marcar como entregada una venta cancelada', 400);
        }

        // Validar que no esté ya entregada
        if ($sale->sale_status_id == 4) {
            return $this->error('Esta venta ya fue marcada como entregada', 400);
        }

        // Actualizar la venta
        $sale->sale_status_id = 4; // Entregado
        $sale->receiver_name = $request->receiver_name;
        $sale->receiver_dni = $request->receiver_dni;
        $sale->delivered_at = Carbon::now();
        $sale->save();

        // Registrar en historial de estados
        SaleStatusHistory::create([
            'sale_id' => $sale->id,
            'sale_status_id' => 4,
            'date' => Carbon::now(),
        ]);

        // Enviar email al cliente (si tiene email y método de envío no es local)
        if ($sale->client && $sale->client->email && $sale->shipping_method_id != 1) {
            try {
                Mail::to($sale->client->email)->send(new OrderRetiredMail($sale));
            } catch (\Exception $e) {
                // Log del error pero no interrumpir el proceso
                \Log::error('Error enviando email de entrega: ' . $e->getMessage());
            }
        }

        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'cadete']);

        return $this->success($sale, 'Pedido marcado como entregado correctamente');
    }
}
