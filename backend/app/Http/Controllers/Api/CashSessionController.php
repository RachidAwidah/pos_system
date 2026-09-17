<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashMovementRequest;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\ForceCloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Register;
use App\Models\Shift;
use App\Services\CashSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    public function __construct(public CashSessionService $cashSessionService) {}

    public function lastClosed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'register_id' => ['required', 'string', 'exists:registers,id'],
        ]);

        $shift = Shift::query()
            ->where('register_id', $validated['register_id'])
            ->where('status', ShiftStatus::Closed->value)
            ->orderByDesc('closed_at')
            ->with('closedBy:id,full_name')
            ->first();

        if (! $shift) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'closing_cash' => $shift->closing_cash,
                'closed_at' => $shift->closed_at,
                'closed_by_user_name' => $shift->closedBy?->full_name,
            ],
        ]);
    }

    public function open(OpenShiftRequest $request): ShiftResource
    {
        $shift = $this->cashSessionService->open(Register::query()->findOrFail((string) $request->string('register_id')), $request->user(), (string) $request->string('opening_cash'), $request->input('notes'));

        return new ShiftResource($shift);
    }

    public function summary(Request $request, Shift $shift): JsonResponse
    {
        abort_unless($shift->opened_by_user_id === $request->user()->id || $request->user()->hasRole('Admin'), 403);

        return response()->json(['data' => $this->cashSessionService->cashSummary($shift)]);
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
        return new ShiftResource($this->cashSessionService->close(
            $shift,
            $request->user(),
            (string) $request->string('closing_cash'),
            $request->input('closing_notes'),
            $request->input('admin_override_reason'),
        ));
    }

    public function forceClose(ForceCloseShiftRequest $request, Shift $shift): ShiftResource
    {
        return new ShiftResource($this->cashSessionService->forceClose(
            $shift,
            $request->user(),
            (string) $request->string('closing_cash'),
            (string) $request->string('reason'),
            $request->input('closing_notes'),
        ));
    }
}
