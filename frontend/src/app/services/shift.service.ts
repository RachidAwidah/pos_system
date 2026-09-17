import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

export interface Register {
  id: string;
  name: string;
  open_shift?: {
    id: string;
    opened_by_user_id: string;
    opened_by_name: string | null;
    opening_cash: string | number;
    opened_at: string;
    owned_by_current_user: boolean;
  } | null;
}

export interface PosProduct {
  id: string;
  name: string;
  sku: string;
  barcode: string | null;
  price: string | number;
  type: string;
  tax_rate: string | number;
  quantity_available: string | number;
  unit?: { symbol: string } | null;
}

export interface PaymentMethod {
  id: string;
  name: string;
  code: string;
  category: string;
  requires_reference: boolean;
}

export interface PosCustomer {
  id: string;
  name: string;
  phone?: string | null;
  credit_limit: string | number;
  balance: string | number;
  loyalty_points: number;
}

export interface Shift {
  id: string;
  register_id: string;
  status: string;
  opening_cash: string | number;
  opened_at: string;
  notes?: string | null;
}

interface ReferenceDataResponse {
  data: {
    registers: Register[];
    products: PosProduct[];
    payment_methods: PaymentMethod[];
    customers: PosCustomer[];
    current_shift: Shift | null;
    can_force_close_shifts: boolean;
  };
}

export interface PosProductPageResponse {
  data: PosProduct[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface LastClosedShift {
  closing_cash: string;
  closed_at: string;
  closed_by_user_name: string | null;
}

@Injectable({ providedIn: 'root' })
export class ShiftService {
  constructor(private readonly http: HttpClient) {}

  open(registerId: string, openingCash: number, notes: string): Observable<{ data: Shift }> {
    return this.http.post<{ data: Shift }>(`${environment.apiBaseUrl}/shifts/open`, {
      register_id: registerId,
      opening_cash: openingCash,
      notes: notes.trim() || null,
    });
  }

  close(shiftId: string, closingCash: number, closingNotes?: string, adminOverrideReason?: string): Observable<{ data: Shift }> {
    return this.http.post<{ data: Shift }>(`${environment.apiBaseUrl}/shifts/${shiftId}/close`, {
      closing_cash: closingCash,
      ...(closingNotes ? { closing_notes: closingNotes } : {}),
      ...(adminOverrideReason ? { admin_override_reason: adminOverrideReason } : {}),
    });
  }

  referenceData(): Observable<ReferenceDataResponse> {
    return this.http.get<ReferenceDataResponse>(`${environment.apiBaseUrl}/pos/reference-data`);
  }

  summary(shiftId: string): Observable<{ data: Record<string, string> }> {
    return this.http.get<{ data: Record<string, string> }>(`${environment.apiBaseUrl}/shifts/${shiftId}/summary`);
  }

  products(page = 1, perPage = 25): Observable<PosProductPageResponse> {
    return this.http.get<PosProductPageResponse>(`${environment.apiBaseUrl}/pos/products`, {
      params: { page, per_page: perPage },
    });
  }

  checkout(
    shiftId: string,
    items: Array<{ id: string; quantity: number }>,
    paymentMethodId: string,
    paymentAmount: number,
    customerId?: string,
    referenceNumber?: string,
    displayCurrency?: string,
    exchangeRate?: number,
    rateProvider?: string,
  ): Observable<unknown> {
    return this.http.post(`${environment.apiBaseUrl}/orders`, {
      shift_id: shiftId,
      items: items.map(item => ({ product_id: item.id, quantity: item.quantity })),
      payments: paymentAmount > 0
        ? [{ payment_method_id: paymentMethodId, amount: paymentAmount, ...(referenceNumber ? { reference_number: referenceNumber } : {}) }]
        : [],
      ...(customerId ? { customer_id: customerId } : {}),
      ...(displayCurrency ? { display_currency: displayCurrency } : {}),
      ...(exchangeRate ? { exchange_rate: exchangeRate } : {}),
      ...(rateProvider ? { rate_provider: rateProvider } : {}),
    });
  }

  forceClose(shiftId: string, closingCash: number, reason: string): Observable<{ data: Shift }> {
    return this.http.post<{ data: Shift }>(`${environment.apiBaseUrl}/shifts/${shiftId}/force-close`, {
      closing_cash: closingCash,
      reason: reason.trim(),
    });
  }

  lastClosed(registerId: string): Observable<{ data: LastClosedShift | null }> {
    return this.http.get<{ data: LastClosedShift | null }>(
      `${environment.apiBaseUrl}/shifts/last-closed`,
      { params: { register_id: registerId } },
    );
  }
}
