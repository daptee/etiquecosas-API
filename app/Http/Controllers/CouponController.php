<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class CouponController extends Controller
{
    use FindObject, ApiResponse, Auditable;

     public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $statusId = $request->query('status');        
        $query = Coupon::query()->with('status');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($statusId) {
            $query->where('coupon_status_id', $statusId);
        }
        
        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $coupons = $query->get();
            $this->logAudit(Auth::user(), 'Get Coupons List', $request->all(), $coupons);
            return $this->success([
                'data' => $coupons,
                'meta_data' => null,
            ], 'Cupones obtenidos');
        }

        $coupons = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Coupons List', $request->all(), $coupons);        
        $metaData = [
            'current_page' => $coupons->currentPage(),
            'last_page' => $coupons->lastPage(),
            'per_page' => $coupons->perPage(),
            'total' => $coupons->total(),
            'from' => $coupons->firstItem(),
            'to' => $coupons->lastItem(),
        ];        
        return $this->success([
            'data' => $coupons->items(),
            'meta_data' => $metaData,
        ], 'Cupones obtenidos');
    }
}
