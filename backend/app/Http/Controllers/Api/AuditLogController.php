<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = AuditLog::query()->with('user:id,full_name');

        foreach (['user_id', 'action', 'entity_type', 'entity_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['from'])) {
            $query->where('logged_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (isset($filters['to'])) {
            $query->where('logged_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return AuditLogResource::collection(
            $query->orderByDesc('logged_at')->orderByDesc('id')
                ->paginate($filters['per_page'] ?? 25)->appends($filters),
        );
    }

    public function show(AuditLog $auditLog): AuditLogResource
    {
        $auditLog->load('user:id,full_name');
        AuditLogService::viewed(AuditLog::class, $auditLog->id);

        return new AuditLogResource($auditLog);
    }
}
