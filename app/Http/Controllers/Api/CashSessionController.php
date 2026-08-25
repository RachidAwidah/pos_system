<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashMovementRequest;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Register;
use App\Models\Shift;
use App\Services\CashSessionService;
use Illuminate\Http\JsonResponse;

class CashSessionController extends Controller
{
    public function __construct(public CashSessionService $cashSessionService) {}

    public function open(OpenShiftRequest $request): ShiftResource
    {
        $shift = $this->cashSessionService->open(Register::query()->findOrFail((string) $request->string('register_id')), $request->user(), (string) $request->string('opening_cash'), $request->input('notes'));

        return new ShiftResource($shift);
    }

    public function cashIn(CashMovementRequest $request, Shift $shift): JsonResponse
    {
        return response()->json(['data' => $this->cashSessionService->cashIn($shift, $request->user(), (string) $request->string('amount'), (string) $request->string('reason'))], 201);
    }

    public function cashOut(CashMovementRequest $request, Shift $shift): JsonResponse
    {
        return response()->json(['data' => $this->cashSessionService->cashOut($shift, $request->user(), (string) $request->string('amount'), (string) $request->string('reason'))], 201);
    }

    public function close(CloseShiftRequest $request, Shift $shift): ShiftResource
    {
        return new ShiftResource($this->cashSessionService->close($shift, $request->user(), (string) $request->string('closing_cash')));
    }
}
