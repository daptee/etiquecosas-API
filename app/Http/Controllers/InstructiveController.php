<?php

namespace App\Http\Controllers;

use App\Models\Instructive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class InstructiveController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    /**
     * Get all instructives (Admin) - with filters and pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Instructive::with('status');

        // Filter by search (name or description)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status_id
        if ($status) {
            $query->where('status_id', $status);
        }

        // Order by position asc, then created_at desc
        $query->orderBy('position', 'asc')->orderBy('created_at', 'desc');

        // Without pagination
        if (!$perPage) {
            $instructives = $query->get();
            $this->logAudit(Auth::user(), 'Get Instructives List', $request->all(), $instructives);
            return $this->success($instructives, 'Instructives obtenidos');
        }

        // With pagination
        $instructives = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Instructives List', $request->all(), $instructives);

        $metaData = [
            'current_page' => $instructives->currentPage(),
            'last_page' => $instructives->lastPage(),
            'per_page' => $instructives->perPage(),
            'total' => $instructives->total(),
            'from' => $instructives->firstItem(),
            'to' => $instructives->lastItem(),
        ];

        return $this->success($instructives->items(), 'Instructives obtenidos', $metaData);
    }

    /**
     * Get active instructives (Web) - only active with pagination
     */
    public function getActive(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);

        $query = Instructive::with('status')->active()->orderBy('position', 'asc')->orderBy('created_at', 'desc');

        // Without pagination
        if (!$perPage) {
            $instructives = $query->get();
            return $this->success($instructives, 'Instructives activos obtenidos');
        }

        // With pagination
        $instructives = $query->paginate($perPage, ['*'], 'page', $page);

        $metaData = [
            'current_page' => $instructives->currentPage(),
            'last_page' => $instructives->lastPage(),
            'per_page' => $instructives->perPage(),
            'total' => $instructives->total(),
            'from' => $instructives->firstItem(),
            'to' => $instructives->lastItem(),
        ];

        return $this->success($instructives->items(), 'Instructives activos obtenidos', $metaData);
    }

    /**
     * Create new instructive
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'link' => 'required|string',
            'status_id' => 'required|integer|exists:general_statuses,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Instructive', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Get next position
        $maxPosition = Instructive::max('position');
        $nextPosition = $maxPosition !== null ? $maxPosition + 1 : 0;

        $instructive = Instructive::create([
            'name' => $request->name,
            'description' => $request->description,
            'link' => $request->link,
            'position' => $nextPosition,
            'status_id' => $request->status_id,
        ]);

        $instructive->load('status');

        $this->logAudit(Auth::user(), 'Store Instructive', $request->all(), $instructive);
        return response()->json([
            'success' => true,
            'message' => 'Instructive creado',
            'data' => $instructive
        ]);
    }

    /**
     * Update instructive
     */
    public function update(Request $request, $id)
    {
        $instructive = $this->findObject(Instructive::class, $id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'link' => 'required|string',
            'status_id' => 'required|integer|exists:general_statuses,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Instructive', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $instructive->name = $request->name;
        $instructive->description = $request->description;
        $instructive->link = $request->link;
        $instructive->status_id = $request->status_id;
        $instructive->save();

        $instructive->load('status');

        $this->logAudit(Auth::user(), 'Update Instructive', $request->all(), $instructive);
        return response()->json([
            'success' => true,
            'message' => 'Instructive actualizado',
            'data' => $instructive
        ]);
    }

    /**
     * Change instructive status
     */
    public function changeStatus(Request $request, $id)
    {
        $instructive = $this->findObject(Instructive::class, $id);

        $validator = Validator::make($request->all(), [
            'status_id' => 'required|integer|exists:general_statuses,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Change Instructive Status', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $instructive->status_id = $request->status_id;
        $instructive->save();

        $instructive->load('status');

        $this->logAudit(Auth::user(), 'Change Instructive Status', $request->all(), $instructive);
        return response()->json([
            'success' => true,
            'message' => 'Estado del instructive actualizado',
            'data' => $instructive
        ]);
    }

    /**
     * Update positions for drag and drop
     */
    public function updatePositions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'positions' => 'required|array|min:1',
            'positions.*.id' => 'required|integer|exists:instructives,id',
            'positions.*.position' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Instructive Positions', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        foreach ($request->positions as $item) {
            Instructive::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        $this->logAudit(Auth::user(), 'Update Instructive Positions', $request->all(), 'Success');
        return response()->json([
            'success' => true,
            'message' => 'Posiciones actualizadas correctamente'
        ]);
    }

    /**
     * Delete instructive (soft delete)
     */
    public function delete($id)
    {
        $instructive = $this->findObject(Instructive::class, $id);
        $instructive->load('status');
        $instructive->delete();

        $this->logAudit(Auth::user(), 'Delete Instructive', $id, $instructive);
        return $this->success($instructive, 'Instructive eliminado');
    }
}
