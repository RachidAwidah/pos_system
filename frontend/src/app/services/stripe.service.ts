import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { take } from 'rxjs/operators';
import { loadStripe, Stripe, StripeCardNumberElement } from '@stripe/stripe-js';

import { environment } from '../../environments/environment';
import { SettingsService } from './settings.service';

interface PaymentIntentResponse {
  client_secret: string;
  payment_intent_id: string;
}

@Injectable({ providedIn: 'root' })
export class StripeService {
  private stripePromise: Promise<Stripe | null> | null = null;

  constructor(
    private readonly http: HttpClient,
    private readonly settingsService: SettingsService,
  ) {}

  createPaymentIntent(amount: number): Observable<PaymentIntentResponse> {
    return this.http.post<PaymentIntentResponse>(
      `${environment.apiBaseUrl}/payment/create-intent`, { amount },
    );
  }

  async getStripe(): Promise<Stripe | null> {
    if (!this.stripePromise) {
      const key = await this.settingsService.getValue('stripe_publishable_key')
        .pipe(take(1))
        .toPromise();
      const publishableKey = [key, environment.stripeKey]
        .filter((candidate): candidate is string => typeof candidate === 'string')
        .map(candidate => candidate.trim())
        .find(candidate => /^pk_(test|live)_[A-Za-z0-9]+$/.test(candidate)
          && !candidate.toUpperCase().includes('REPLACE'));
      if (!publishableKey) {
        throw new Error('مفتاح Stripe العام غير مُعد بصيغة صحيحة. تحقق من إعدادات الدفع.');
      }
      this.stripePromise = loadStripe(publishableKey);
    }
    return this.stripePromise;
  }

  async confirmCardPayment(
    clientSecret: string,
    cardElement: StripeCardNumberElement,
    billingDetails?: { name?: string; email?: string },
  ): Promise<{ paymentIntent?: unknown; error?: { message?: string } }> {
    const stripe = await this.getStripe();
    if (!stripe) {
      return { error: { message: 'فشل تحميل نظام الدفع.' } };
    }

    return stripe.confirmCardPayment(clientSecret, {
      payment_method: {
        card: cardElement,
        billing_details: billingDetails ?? {},
      },
    });
  }
}
