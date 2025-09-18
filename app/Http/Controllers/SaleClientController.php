<?php

namespace App\Http\Controllers;

use App\Mail\OrderAddressChangeMail;
use App\Mail\OrderModificationRequestMail;
use App\Mail\ShippingClaimMail;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\Sale;

class SaleClientController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    /**
     * 1. Historial de pedidos del cliente
     */
    public function orderHistory(Request $request)
    {
        $client = Auth::guard('client')->user(); // guard client

        if (!$client || !$client->id) {
            $this->logAudit(null, 'Order History Validation Fail', $request->all(), 'El usuario no es un cliente válido');
            return $this->error('El usuario no es un cliente válido', 401);
        }

        $perPage = $request->query('quantity'); // cantidad por página
        $page = $request->query('page', 1);

        $query = Sale::query()
            ->with(['channel', 'products.product.images', 'products.product.categories', 'products.variant', 'status', 'statusHistory', 'shippingMethod', 'coupon'])
            ->where('client_id', $client->id)
            ->orderBy('created_at', 'desc');

        // Si no hay perPage, traer todo
        if (!$perPage) {
            $orders = $query->get();
            $this->logAudit(null, 'Get Order History', $request->all(), $orders->take(10));
            return $this->success($orders, 'Historial de pedidos obtenido correctamente');
        }

        // Paginación
        $orders = $query->paginate($perPage, ['*'], 'page', $page);

        $this->logAudit(null, 'Get Order History', $request->all(), collect($orders->items())->take(10));

        $metaData = [
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'from' => $orders->firstItem(),
            'to' => $orders->lastItem(),
        ];

        return $this->success($orders->items(), 'Historial de pedidos obtenido correctamente', $metaData);
    }


    /**
     * 2. Modificar pedido (enviar notificación por email)
     */
    public function requestOrderModification(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            $this->logAudit(null, 'Order Modification Fail', $request->all(), 'Token inválido o no enviado');
            return $this->error('No autorizado', 401);
        }

        $rules = [
            'order_id' => 'required|integer|exists:sales,id',
            'note' => 'required|string|max:1000',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(null, 'Order Modification Fail', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $mailData = [
            'client_name' => $client->name . ' ' . $client->lastname,
            'order_id' => $request->order_id,
            'message' => $request->note,
        ];

        $notifyEmail = env('MAIL_NOTIFICATION_TO');

        Mail::to($notifyEmail)->send(new OrderModificationRequestMail($mailData));

        $this->logAudit(null, 'Order Modification Sent', $request->all(), $mailData);

        return $this->success($mailData, 'Solicitud de modificación enviada correctamente');
    }

    /**
     * 3. Cambiar dirección de un envío (enviar notificación por email)
     */
    /**
     * 3. Solicitud de cambio de dirección
     */
    public function requestAddressChange(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client->id) {
            $this->logAudit(null, 'Address Change Validation Fail', $request->all(), 'El usuario no es un cliente válido');
            return $this->error('El usuario no es un cliente válido', 401);
        }

        $rules = [
            'order_id' => 'required|integer|exists:sales,id',
            'new_address' => 'required|string|max:255',
            'message' => 'nullable|string|max:2000',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(null, 'Address Change Validation Fail', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $mailData = [
            'client_name' => $client->name . ' ' . $client->lastname,
            'order_id' => $request->order_id,
            'new_address' => $request->new_address,
            'message' => $request->message,
        ];

        $notifyEmail = env('MAIL_NOTIFICATION_TO');

        \Mail::to($notifyEmail)->send(new OrderAddressChangeMail($mailData));

        $this->logAudit(null, 'Request Address Change', ['orderId' => $request->order_id], $mailData);

        return $this->success($mailData, 'Solicitud de cambio de dirección enviada correctamente');
    }

    /**
     * 4. Reclamo de un envío (enviar notificación por email)
     */
    public function requestShippingClaim(Request $request)
    {
        $client = Auth::guard('client')->user();

        if (!$client->id) {
            $this->logAudit(null, 'Shipping Claim Validation Fail', $request->all(), 'El usuario no es un cliente válido');
            return $this->error('El usuario no es un cliente válido', 401);
        }

        $rules = [
            'message' => 'required|string|max:2000',
            'order_id' => 'required|integer|exists:sales,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(null, 'Shipping Claim Validation Fail', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $mailData = [
            'client_name' => $client->name . ' ' . $client->lastname,
            'order_id' => $request->order_id,
            'message' => $request->message,
        ];

        $notifyEmail = env('MAIL_NOTIFICATION_TO');

        Mail::to($notifyEmail)->send(new ShippingClaimMail($mailData));

        $this->logAudit(null, 'Request Shipping Claim', ['orderId' => $request->order_id], $mailData);

        return $this->success($mailData, 'Reclamo de envío enviado correctamente');
    }

}
