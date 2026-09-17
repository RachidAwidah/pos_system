<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected int $cacheTtl;

    protected array $supportedCurrencies;

    public function __construct()
    {
        $this->apiKey = config('services.fxfeed.api_key', '');
        $this->baseUrl = config('services.fxfeed.base_url', 'https://api.fxfeed.io/v2/latest');
        $this->cacheTtl = config('services.fxfeed.cache_ttl', 14400);
        $this->supportedCurrencies = config('services.fxfeed.supported_currencies', ['USD', 'SYP', 'TRY']);
    }

    /**
     * Get current exchange rates from cache or API.
     */
    public function getRates(): array
    {
        $cacheKey = 'exchange_rates_'.date('Y-m-d');

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            return $this->fetchRatesFromApi();
        });
    }

    /**
     * Force refresh exchange rates (clear cache and fetch new).
     */
    public function refreshRates(): array
    {
        $cacheKey = 'exchange_rates_'.date('Y-m-d');
        Cache::forget($cacheKey);

        return $this->getRates();
    }

    /**
     * Get rate for a specific currency.
     */
    public function getRate(string $currencyCode): ?float
    {
        $rates = $this->getRates();

        return $rates[$currencyCode] ?? null;
    }

    /**
     * Convert amount from USD to target currency.
     */
    public function convert(float $amount, string $targetCurrency): float
    {
        if ($targetCurrency === 'USD') {
            return $amount;
        }

        $rate = $this->getRate($targetCurrency);
        if ($rate === null) {
            throw new \InvalidArgumentException("Unsupported currency: {$targetCurrency}");
        }

        return round($amount * $rate, 2);
    }

    /**
     * Fetch rates from FxFeed.io API.
     */
    protected function fetchRatesFromApi(): array
    {
        if (empty($this->apiKey)) {
            Log::warning('FXFEED_API_KEY not configured, using fallback rates');

            return $this->getFallbackRates();
        }

        try {
            $currencies = implode(',', array_filter($this->supportedCurrencies, fn ($c) => $c !== 'USD'));
            $response = Http::timeout(10)
                ->get($this->baseUrl, [
                    'base' => 'USD',
                    'currencies' => $currencies,
                    'apikey' => $this->apiKey,
                ]);

            if ($response->failed()) {
                Log::error('FxFeed API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->getFallbackRates();
            }

            $data = $response->json();
            $rates = ['USD' => 1.0];

            if (isset($data['rates'])) {
                foreach ($data['rates'] as $currency => $rate) {
                    $rates[$currency] = (float) $rate;
                }
            }

            return $rates;
        } catch (\Exception $e) {
            Log::error('FxFeed API error', ['message' => $e->getMessage()]);

            return $this->getFallbackRates();
        }
    }

    /**
     * Fallback rates when API is unavailable.
     */
    protected function getFallbackRates(): array
    {
        return [
            'USD' => 1.0,
            'SYP' => 15000.0,  // Approximate fallback rate
            'TRY' => 38.0,     // Approximate fallback rate
        ];
    }

    /**
     * Get supported currencies list.
     */
    public function getSupportedCurrencies(): array
    {
        return $this->supportedCurrencies;
    }

    /**
     * Check if a currency is supported.
     */
    public function isSupported(string $currencyCode): bool
    {
        return in_array($currencyCode, $this->supportedCurrencies);
    }
}
