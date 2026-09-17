<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripeController extends Controller
{
    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 500;

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        Log::info('Stripe createPaymentIntent request:', [
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'usd',
            'user_id' => $request->user()?->id,
        ]);

        $secretKey = config('stripe.secret');

        if (! $secretKey) {
            Log::warning('Stripe secret key is not configured.');

            return response()->json([
                'message' => 'مفتاح Stripe غير مُعد بصيغة صحيحة. تحقق من إعدادات الدفع أو إعدادات الخادم.',
            ], 500);
        }

        $caBundlePath = config('stripe.ca_bundle_path');
        if ($caBundlePath && file_exists($caBundlePath)) {
            Stripe::setCABundlePath($caBundlePath);
        }

        Stripe::setApiKey($secretKey);
        Stripe::setApiVersion('2026-08-26.dahlia');

        $lastException = null;
        $attempts = 0;

        while ($attempts < self::MAX_RETRIES) {
            $attempts++;
            try {
                $paymentIntent = PaymentIntent::create([
                    'amount' => (int) round($validated['amount'] * 100),
                    'currency' => $validated['currency'] ?? 'usd',
                    'automatic_payment_methods' => [
                        'enabled' => true,
                    ],
                    'metadata' => [
                        'pos' => 'true',
                    ],
                ]);

                Log::info('Stripe PaymentIntent created:', [
                    'id' => $paymentIntent->id,
                    'amount' => $paymentIntent->amount,
                ]);

                return response()->json([
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                ]);
            } catch (ApiConnectionException $e) {
                $lastException = $e;
                Log::warning('Stripe connection attempt '.$attempts.' failed:', [
                    'message' => $e->getMessage(),
                ]);

                if ($attempts < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempts);
                }
            } catch (AuthenticationException $e) {
                Log::error('Stripe authentication error:', [
                    'status' => $e->getHttpStatus(),
                    'code' => $e->getStripeCode(),
                ]);

                return response()->json([
                    'message' => 'رفض Stripe مفتاح المصادقة. تحقق من صلاحية المفتاح في إعدادات الدفع أو إعدادات الخادم.',
                ], 500);
            } catch (InvalidRequestException $e) {
                Log::error('Stripe invalid request:', [
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'طلب غير صالح إلى Stripe: '.$e->getMessage(),
                ], 400);
            } catch (CardException $e) {
                Log::error('Stripe card error:', [
                    'message' => $e->getMessage(),
                    'code' => $e->getDeclineCode(),
                ]);

                return response()->json([
                    'message' => 'خطأ في البطاقة: '.$e->getMessage(),
                ], 402);
            } catch (RateLimitException $e) {
                $lastException = $e;
                Log::warning('Stripe rate limit hit, attempt '.$attempts.':', [
                    'message' => $e->getMessage(),
                ]);

                if ($attempts < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempts * 2);
                }
            } catch (ApiErrorException $e) {
                Log::error('Stripe API error:', [
                    'message' => $e->getMessage(),
                    'code' => $e->getStripeCode(),
                ]);

                return response()->json([
                    'message' => 'فشل إنشاء عملية الدفع: '.$e->getMessage(),
                ], 502);
            }
        }

        Log::error('Stripe connection failed after '.self::MAX_RETRIES.' attempts:', [
            'message' => $lastException?->getMessage(),
        ]);

        return response()->json([
            'message' => 'تعذر الاتصال بـ Stripe. يرجى التحقق من اتصال الإنترنت والمحاولة مرة أخرى.',
        ], 503);
    }
}
