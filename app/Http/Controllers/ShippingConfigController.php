<?php

namespace App\Http\Controllers;

use App\Models\ShippingConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class ShippingConfigController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    /**
     * Mostrar la configuración de envío
     */
    public function index()
    {
        $config = ShippingConfig::get();
        return $this->success($config, 'Configuración de envío obtenida');
    }

    /**
     * Mostrar un registro específico (opcional)
     */
    public function show($id)
    {
        $config = $this->findObject(ShippingConfig::class, $id);
        return $this->success($config, 'Detalles de configuración obtenidos');
    }

    /**
     * Actualizar la configuración
     */
    public function update(Request $request, $id)
    {
        $config = $this->findObject(ShippingConfig::class, $id);

        $validator = Validator::make($request->all(), [
            'data' => 'required|array', // esperamos un JSON/array válido
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Shipping Config', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $config->update([
            'data' => $request->data,
        ]);

        $this->logAudit(Auth::user(), 'Update Shipping Config', $request->all(), $config);
        return $this->success($config, 'Configuración de envío actualizada');
    }
}
