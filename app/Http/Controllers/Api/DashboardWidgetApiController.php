<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductStockResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\SupplierPerformanceResource;
use App\Models\PurchaseOrder;
use App\Services\ReorderService;
use App\Services\SupplierPerformanceService;
use Illuminate\Http\JsonResponse;

/** JSON endpoints used by the dashboard widgets for AJAX refreshes. */
class DashboardWidgetApiController extends Controller
{
    public function pendingOrders(): JsonResponse
    {
        $orders = PurchaseOrder::pending()->with('supplier')->latest()->take(5)->get();

        return response()->json([
            'data' => PurchaseOrderResource::collection($orders)->resolve(),
            'count' => PurchaseOrder::pending()->count(),
            'generated_at' => now(),
        ]);
    }

    public function recentOrders(): JsonResponse
    {
        $orders = PurchaseOrder::with(['supplier', 'createdBy'])->latest()->take(5)->get();

        return response()->json([
            'data' => PurchaseOrderResource::collection($orders)->resolve(),
            'generated_at' => now(),
        ]);
    }

    public function supplierPerformance(SupplierPerformanceService $service): JsonResponse
    {
        return response()->json([
            'data' => SupplierPerformanceResource::collection($service->ranking(5))->resolve(),
            'generated_at' => now(),
        ]);
    }

    public function reorderAlerts(ReorderService $service): JsonResponse
    {
        $candidates = $service->getProductsNeedingReorder();

        return response()->json([
            'data' => ProductStockResource::collection($candidates->take(5))->resolve(),
            'count' => $candidates->count(),
            'generated_at' => now(),
        ]);
    }
}
