<?php

namespace App\Http\Controllers;

use App\Models\ShippingTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class ShippingTemplateController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = ShippingTemplate::query();
        if ($search) {
            $query->where('description', 'like', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $shippingTemplates = $query->get();
            return $this->success($shippingTemplates, 'Plantilas de envio obtenidas');
        }

        $shippingTemplates = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $shippingTemplates->currentPage(),
            'last_page' => $shippingTemplates->lastPage(),
            'per_page' => $shippingTemplates->perPage(),
            'total' => $shippingTemplates->total(),
            'from' => $shippingTemplates->firstItem(),
            'to' => $shippingTemplates->lastItem(),
        ];        
        return $this->success($shippingTemplates->items(), 'Plantillas de envio obtenidas', $metaData);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_shipping_template_category' => 'nullable|integer',
            'description' => 'required|string',
            'name' => 'required|string',
            'statusId' => 'nullable|in:1,2',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Shipping Template', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $shippingTemplate = ShippingTemplate::create([
            'id_shipping_template_category' => $request->idShippingTemplateCategory,
            'description' => $request->description,
            'name' => $request->name,
            'status_id' => $request->statusId ?? 1,
        ]);
        $this->logAudit(Auth::user(), 'Store Shipping Template', $request->all(), $shippingTemplate);
        return $this->success($shippingTemplate, 'Plantilla de envio creada');
    }

    
    public function update(Request $request, $id)
    {
        $shippingTemplate = $this->findObject(ShippingTemplate::class, $id);
        $validator = Validator::make($request->all(), [
            'id_shipping_template_category' => 'nullable|integer',
            'description' => 'required|string',
            'name' => 'required|string',
            'statusId' => 'nullable|in:1,2',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Shipping Template', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $shippingTemplate->id_shipping_template_category = $request->input('idShippingTemplateCategory', $shippingTemplate->id_shipping_template_category);
        $shippingTemplate->description = $request->input('description', $shippingTemplate->description);
        $shippingTemplate->name = $request->input('name', $shippingTemplate->name);
        $shippingTemplate->status_id = $request->input('statusId', $shippingTemplate->status_id);
        $shippingTemplate->save();
        $this->logAudit(Auth::user(), 'Update Shipping Template', $request->all(), $shippingTemplate);
        return $this->success($shippingTemplate, 'Plantilla de envio actualizada');
    }

    public function delete($id)
    {
        $shippingTemplate = $this->findObject(ShippingTemplate::class, $id);
        $shippingTemplate->delete();
        $this->logAudit(Auth::user(), 'Delete Shipping Template', $id, $shippingTemplate);
        return $this->success($shippingTemplate, 'Plantilla de envio eliminada');
    }
}
