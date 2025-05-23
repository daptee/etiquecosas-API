<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class ProvinceController extends Controller
{
    use FindObject, ApiResponse, Auditable;

   public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $provinceId = $request->query('province_id');
        $query = Province::with('localities');
        if ($provinceId) {
            $query->where('id', $provinceId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('localities', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                });
            });
        }

        $provinces = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Provinces List', $request->all(), $provinces);
        $metaData = [
            'current_page' => $provinces->currentPage(),
            'last_page' => $provinces->lastPage(),
            'per_page' => $provinces->perPage(),
            'total' => $provinces->total(),
            'from' => $provinces->firstItem(),
            'to' => $provinces->lastItem(),
        ];
        return $this->success([
            'message' => 'Provincias obtenidas',
            'data' => $provinces->items(),
            'meta_data' => $metaData,
        ], 200);
    }
}
