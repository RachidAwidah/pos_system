<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockMovementIndexRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function index(StockMovementIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = StockMovement::query()
            ->with(['product:id,product_name,sku', 'warehouse:id,name', 'user:id,full_name'])
            ->when($validated['product_id'] ?? null, fn (Builder $q, string $v) => $q->where('product_id', $v))
            ->when($validated['warehouse_id'] ?? null, fn (Builder $q, string $v) => $q->where('warehouse_id', $v))
            ->when($validated['user_id'] ?? null, fn (Builder $q, string $v) => $q->where('user_id', $v))
            ->when($validated['movement_type'] ?? null, fn (Builder $q, string $v) => $q->where('movement_type', $v))
            ->when($validated['from'] ?? null, fn (Builder $q, string $v) => $q->whereDate('occurred_at', '>=', $v))
            ->when($validated['to'] ?? null, fn (Builder $q, string $v) => $q->whereDate('occurred_at', '<=', $v))
            ->when($validated['search'] ?? null, fn (Builder $q, string $v) => $q->whereHas('product', fn (Builder $pq) => $pq
                ->where('product_name', 'like', "%{$v}%")
                ->orWhere('sku', 'like', "%{$v}%")))
            ->orderByDesc('occurred_at');

        if ($validated['all'] ?? false) {
            return response()->json([
                'data' => StockMovementResource::collection($query->get()),
            ]);
        }

        $paginated = $query->paginate($request->integer('per_page', 25))->withQueryString();

        return response()->json([
            'data' => StockMovementResource::collection($paginated->getCollection()),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }
}
