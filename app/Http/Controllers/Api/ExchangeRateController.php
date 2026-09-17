<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    /**
     * Get current exchange rates.
     */
    public function index(): JsonResponse
    {
        $rates = $this->exchangeRateService->getRates();
        $supportedCurrencies = $this->exchangeRateService->getSupportedCurrencies();

        return response()->json([
            'base_currency' => 'USD',
            'rates' => $rates,
            'supported_currencies' => $supportedCurrencies,
            'cached_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Force refresh exchange rates (admin only).
     */
    public function refresh(Request $request): JsonResponse
    {
        $rates = $this->exchangeRateService->refreshRates();

        return response()->json([
            'base_currency' => 'USD',
            'rates' => $rates,
            'supported_currencies' => $this->exchangeRateService->getSupportedCurrencies(),
            'refreshed_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get rate for a specific currency.
     */
    public function show(string $currency): JsonResponse
    {
        $rate = $this->exchangeRateService->getRate($currency);

        if ($rate === null) {
            return response()->json([
                'message' => "العملة {$currency} غير مدعومة",
            ], 422);
        }

        return response()->json([
            'currency' => $currency,
            'base_currency' => 'USD',
            'rate' => $rate,
        ]);
    }
}
