<?php

namespace App\Http\Controllers;

use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
class ProvinceController extends Controller
{
    use FindObject, ApiResponse, Auditable;

   public function index(Request $request)
    {
        $perPage = $request->query('quantity');
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

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $provinces = $query->get();
            $this->logAudit(Auth::user(), 'Get Provinces List', '/provinces', $provinces);
            return $this->success([
                'message' => 'Provincias obtenidas',
                'data' => $provinces,
                'meta_data' => null,
            ], 200);
        }

        $provinces = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Provinces List', '/provinces', $provinces);
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
