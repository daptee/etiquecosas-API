<?php

namespace App\Http\Controllers;

use App\Models\CustomerService;
use App\Models\CustomerServiceStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class CustomerServiceController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    /**
     * Get all customer services (Admin) - with filters and pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $status = $request->query('status');

        $query = CustomerService::with(['status', 'steps']);

        // Filter by search (name)
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filter by status_id
        if ($status) {
            $query->where('status_id', $status);
        }

        // Order by created_at desc
        $query->orderBy('created_at', 'desc');

        // Without pagination
        if (!$perPage) {
            $customerServices = $query->get();
            $this->logAudit(Auth::user(), 'Get Customer Services List', $request->all(), $customerServices);
            return $this->success($customerServices, 'Customer services obtenidos');
        }

        // With pagination
        $customerServices = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Customer Services List', $request->all(), $customerServices);

        $metaData = [
            'current_page' => $customerServices->currentPage(),
            'last_page' => $customerServices->lastPage(),
            'per_page' => $customerServices->perPage(),
            'total' => $customerServices->total(),
            'from' => $customerServices->firstItem(),
            'to' => $customerServices->lastItem(),
        ];

        return $this->success($customerServices->items(), 'Customer services obtenidos', $metaData);
    }

    /**
     * Get active customer services (Web) - only active with pagination
     */
    public function getActive(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);

        $query = CustomerService::with(['status', 'steps'])->active()->orderBy('created_at', 'desc');

        // Without pagination
        if (!$perPage) {
            $customerServices = $query->get();
            return $this->success($customerServices, 'Customer services activos obtenidos');
        }

        // With pagination
        $customerServices = $query->paginate($perPage, ['*'], 'page', $page);

        $metaData = [
            'current_page' => $customerServices->currentPage(),
            'last_page' => $customerServices->lastPage(),
            'per_page' => $customerServices->perPage(),
            'total' => $customerServices->total(),
            'from' => $customerServices->firstItem(),
            'to' => $customerServices->lastItem(),
        ];

        return $this->success($customerServices->items(), 'Customer services activos obtenidos', $metaData);
    }

    /**
     * Create new customer service with steps
     */
    public function store(Request $request)
    {
        // Validar campos básicos primero
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status_id' => 'required|integer|exists:general_statuses,id',
            'steps' => 'required',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Customer Service', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Procesar steps (puede venir como string JSON o como array)
        $stepsData = $request->steps;
        if (is_string($stepsData)) {
            $stepsData = json_decode($stepsData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->validationError(['steps' => ['El campo steps no es un JSON válido.']]);
            }
        }

        if (!is_array($stepsData) || empty($stepsData)) {
            return $this->validationError(['steps' => ['El campo steps debe contener al menos un paso.']]);
        }

        // Validar estructura de cada step
        foreach ($stepsData as $index => $step) {
            if (!isset($step['step_number']) || !isset($step['title']) || !isset($step['description'])) {
                return $this->validationError(['steps' => ["El paso en la posición {$index} debe contener step_number, title y description."]]);
            }
        }

        // Create customer service
        $customerServiceData = [
            'name' => $request->name,
            'status_id' => $request->status_id,
        ];

        // Process service icon file
        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $fileName = 'customer_services/icons/' . uniqid('service_icon_') . '.' . $file->getClientOriginalExtension();
            if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                $customerServiceData['icon'] = $fileName;
            }
        }

        $customerService = CustomerService::create($customerServiceData);

        foreach ($stepsData as $index => $stepData) {
            $step = [
                'customer_service_id' => $customerService->id,
                'step_number' => $stepData['step_number'],
                'title' => $stepData['title'],
                'description' => $stepData['description'],
            ];

            // Process icon file
            $iconKey = "step_{$index}_icon";
            if ($request->hasFile($iconKey)) {
                $file = $request->file($iconKey);
                $fileName = 'customer_services/icons/' . uniqid('icon_') . '.' . $file->getClientOriginalExtension();
                if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                    $step['icon'] = $fileName;
                }
            }

            // Process image_1 file
            $image1Key = "step_{$index}_image_1";
            if ($request->hasFile($image1Key)) {
                $file = $request->file($image1Key);
                $fileName = 'customer_services/images/' . uniqid('image_') . '.' . $file->getClientOriginalExtension();
                if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                    $step['image_1'] = $fileName;
                }
            }

            // Process image_2 file
            $image2Key = "step_{$index}_image_2";
            if ($request->hasFile($image2Key)) {
                $file = $request->file($image2Key);
                $fileName = 'customer_services/images/' . uniqid('image_') . '.' . $file->getClientOriginalExtension();
                if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                    $step['image_2'] = $fileName;
                }
            }

            CustomerServiceStep::create($step);
        }

        $customerService->load(['status', 'steps']);

        $this->logAudit(Auth::user(), 'Store Customer Service', $request->all(), $customerService);
        return response()->json([
            'success' => true,
            'message' => 'Customer service creado',
            'data' => $customerService
        ]);
    }

    /**
     * Update customer service
     */
    public function update(Request $request, $id)
    {
        $customerService = $this->findObject(CustomerService::class, $id);

        // Validar campos básicos primero
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status_id' => 'required|integer|exists:general_statuses,id',
            'steps' => 'required',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Customer Service', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Procesar steps (puede venir como string JSON o como array)
        $stepsData = $request->steps;
        if (is_string($stepsData)) {
            $stepsData = json_decode($stepsData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->validationError(['steps' => ['El campo steps no es un JSON válido.']]);
            }
        }

        if (!is_array($stepsData) || empty($stepsData)) {
            return $this->validationError(['steps' => ['El campo steps debe contener al menos un paso.']]);
        }

        // Validar estructura de cada step
        foreach ($stepsData as $index => $step) {
            if (!isset($step['step_number']) || !isset($step['title']) || !isset($step['description'])) {
                return $this->validationError(['steps' => ["El paso en la posición {$index} debe contener step_number, title y description."]]);
            }
        }

        // Update customer service
        $customerService->name = $request->name;
        $customerService->status_id = $request->status_id;

        // Process service icon file
        if ($request->hasFile('icon')) {
            // Delete old icon if exists
            if ($customerService->icon && Storage::disk('public_uploads')->exists($customerService->icon)) {
                Storage::disk('public_uploads')->delete($customerService->icon);
            }
            // Upload new icon
            $file = $request->file('icon');
            $fileName = 'customer_services/icons/' . uniqid('service_icon_') . '.' . $file->getClientOriginalExtension();
            if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                $customerService->icon = $fileName;
            }
        } elseif ($request->has('remove_icon') && $request->remove_icon == true) {
            // Remove icon if explicitly requested
            if ($customerService->icon && Storage::disk('public_uploads')->exists($customerService->icon)) {
                Storage::disk('public_uploads')->delete($customerService->icon);
            }
            $customerService->icon = null;
        }

        $customerService->save();

        // Delete all old steps (cascade will handle deletion later)
        CustomerServiceStep::where('customer_service_id', $customerService->id)->delete();

        // Create new steps
        foreach ($stepsData as $index => $stepData) {
            $step = [
                'customer_service_id' => $customerService->id,
                'step_number' => $stepData['step_number'],
                'title' => $stepData['title'],
                'description' => $stepData['description'],
            ];

            // Process icon file (nuevo archivo o mantener el existente)
            $iconKey = "step_{$index}_icon";
            if ($request->hasFile($iconKey)) {
                // Si hay un archivo antiguo, eliminarlo
                if (isset($stepData['icon']) && Storage::disk('public_uploads')->exists($stepData['icon'])) {
                    Storage::disk('public_uploads')->delete($stepData['icon']);
                }
                // Subir nuevo archivo
                $file = $request->file($iconKey);
                $fileName = 'customer_services/icons/' . uniqid('icon_') . '.' . $file->getClientOriginalExtension();
                if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                    $step['icon'] = $fileName;
                }
            } elseif (isset($stepData['icon']) && $stepData['icon']) {
                // Mantener el archivo existente
                $step['icon'] = $stepData['icon'];
            }

            // Process image_1 file (nuevo archivo o mantener el existente)
            $image1Key = "step_{$index}_image_1";
            if ($request->hasFile($image1Key)) {
                // Si hay un archivo antiguo, eliminarlo
                if (isset($stepData['image_1']) && Storage::disk('public_uploads')->exists($stepData['image_1'])) {
                    Storage::disk('public_uploads')->delete($stepData['image_1']);
                }
                // Subir nuevo archivo
                $file = $request->file($image1Key);
                $fileName = 'customer_services/images/' . uniqid('image_') . '.' . $file->getClientOriginalExtension();
                if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                    $step['image_1'] = $fileName;
                }
            } elseif (isset($stepData['image_1']) && $stepData['image_1']) {
                // Mantener el archivo existente
                $step['image_1'] = $stepData['image_1'];
            }

            // Process image_2 file (nuevo archivo o mantener el existente)
            $image2Key = "step_{$index}_image_2";
            if ($request->hasFile($image2Key)) {
                // Si hay un archivo antiguo, eliminarlo
                if (isset($stepData['image_2']) && Storage::disk('public_uploads')->exists($stepData['image_2'])) {
                    Storage::disk('public_uploads')->delete($stepData['image_2']);
                }
                // Subir nuevo archivo
                $file = $request->file($image2Key);
                $fileName = 'customer_services/images/' . uniqid('image_') . '.' . $file->getClientOriginalExtension();
                if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                    $step['image_2'] = $fileName;
                }
            } elseif (isset($stepData['image_2']) && $stepData['image_2']) {
                // Mantener el archivo existente
                $step['image_2'] = $stepData['image_2'];
            }

            CustomerServiceStep::create($step);
        }

        $customerService->load(['status', 'steps']);

        $this->logAudit(Auth::user(), 'Update Customer Service', $request->all(), $customerService);
        return response()->json([
            'success' => true,
            'message' => 'Customer service actualizado',
            'data' => $customerService
        ]);
    }

    /**
     * Change customer service status
     */
    public function changeStatus(Request $request, $id)
    {
        $customerService = $this->findObject(CustomerService::class, $id);

        $validator = Validator::make($request->all(), [
            'status_id' => 'required|integer|exists:general_statuses,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Change Customer Service Status', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $customerService->status_id = $request->status_id;
        $customerService->save();

        $customerService->load(['status', 'steps']);

        $this->logAudit(Auth::user(), 'Change Customer Service Status', $request->all(), $customerService);
        return response()->json([
            'success' => true,
            'message' => 'Estado del customer service actualizado',
            'data' => $customerService
        ]);
    }

    /**
     * Delete customer service (soft delete)
     */
    public function delete($id)
    {
        $customerService = $this->findObject(CustomerService::class, $id);
        $customerService->load(['status', 'steps']);

        // Delete service icon file
        if ($customerService->icon && Storage::disk('public_uploads')->exists($customerService->icon)) {
            Storage::disk('public_uploads')->delete($customerService->icon);
        }

        // Delete step files
        foreach ($customerService->steps as $step) {
            if ($step->icon && Storage::disk('public_uploads')->exists($step->icon)) {
                Storage::disk('public_uploads')->delete($step->icon);
            }
            if ($step->image_1 && Storage::disk('public_uploads')->exists($step->image_1)) {
                Storage::disk('public_uploads')->delete($step->image_1);
            }
            if ($step->image_2 && Storage::disk('public_uploads')->exists($step->image_2)) {
                Storage::disk('public_uploads')->delete($step->image_2);
            }
        }

        $customerService->delete();

        $this->logAudit(Auth::user(), 'Delete Customer Service', $id, $customerService);
        return $this->success($customerService, 'Customer service eliminado');
    }
}
