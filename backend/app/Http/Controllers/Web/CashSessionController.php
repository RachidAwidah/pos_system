<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Models\Register;
use App\Models\Shift;
use App\Services\CashSessionService;
use Illuminate\Http\RedirectResponse;

class CashSessionController extends Controller
{
    public function store(OpenShiftRequest $request, CashSessionService $cashSessionService): RedirectResponse
    {
        $cashSessionService->open(
            Register::query()->findOrFail((string) $request->string('register_id')),
            $request->user(),
            (string) $request->string('opening_cash'),
            $request->input('notes'),
        );

        return to_route('pos.index')->with('status', 'تم فتح الوردية بنجاح.');
    }

    public function close(CloseShiftRequest $request, Shift $shift, CashSessionService $cashSessionService): RedirectResponse
    {
        $cashSessionService->close($shift, $request->user(), (string) $request->string('closing_cash'));

        return to_route('pos.index')->with('status', 'تم إغلاق الوردية بنجاح.');
    }
}
