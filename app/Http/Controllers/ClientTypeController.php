<?php

namespace App\Http\Controllers;

use App\Models\ClientType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class ClientTypeController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $query = ClientType::query()->orderBy('created_at', 'desc');
        if (!$perPage) {
            $clientTypes = $query->get();
            $this->logAudit(Auth::user(), 'Get Client Type List', $request->all(), $clientTypes);
            return $this->success($clientTypes, 'Tipos de cliente obtenidos');
        }

        $clientTypes = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Client Type List', $request->all(), $clientTypes);
        $metaData = [
            'current_page' => $clientTypes->currentPage(),
            'last_page' => $clientTypes->lastPage(),
            'per_page' => $clientTypes->perPage(),
            'total' => $clientTypes->total(),
            'from' => $clientTypes->firstItem(),
            'to' => $clientTypes->lastItem(),
        ];
        $this->logAudit(Auth::user(), 'Get Client Type List', $request->all(), $clientTypes);
        return $this->success($clientTypes->items(), 'Tipos de cliente obtenidos', $metaData);
    }
}
