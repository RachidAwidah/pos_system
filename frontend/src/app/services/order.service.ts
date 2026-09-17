import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

export interface Order {
  id: string;
  invoice_number: string;
  customer_id?: string | null;
  status: string;
  payment_status: string;
  final_amount: string | number;
  order_date: string;
  customer?: { name: string } | null;
  items?: Array<{ id: string; product_name?: string; product?: { product_name: string }; quantity: string | number; unit_price: string | number; total_amount?: string | number }>;
  payments?: Array<{
    id: string;
    amount: string | number;
    status?: string;
    reference_number?: string | null;
    notes?: string | null;
    paid_at?: string;
    payment_method?: { id?: string; name: string; code?: string } | null;
  }>;
  subtotal_amount?: string | number;
  tax_amount?: string | number;
  discount_amount?: string | number;
  paid_amount?: string | number;
  due_amount?: string | number;
  notes?: string | null;
}

export interface OrderListResponse {
  data: Order[];
  meta: { current_page: number; last_page: number; total: number };
}

@Injectable({ providedIn: 'root' })
export class OrderService {
  constructor(private readonly http: HttpClient) {}

  list(page = 1, perPage = 20, customerId = '', dueOnly = false): Observable<OrderListResponse> {
    return this.http.get<OrderListResponse>(`${environment.apiBaseUrl}/orders`, {
      params: { page, per_page: perPage, customer_id: customerId, due_only: dueOnly ? '1' : '0' },
    });
  }

  listAll(customerId = '', dueOnly = false): Observable<{ data: Order[] }> {
    return this.http.get<{ data: Order[] }>(`${environment.apiBaseUrl}/orders`, {
      params: { all: '1', customer_id: customerId, due_only: dueOnly ? '1' : '0' },
    });
  }

  show(id: string): Observable<{ data: Order }> {
    return this.http.get<{ data: Order }>(`${environment.apiBaseUrl}/orders/${id}`);
  }

  returnOrder(id: string, payload: {
    shift_id?: string;
    reason: string;
    items: Array<{ order_item_id: string; quantity: number; restock: boolean }>;
    refunds: Array<{ payment_method_id: string; amount: number }>;
  }): Observable<{ data: unknown }> {
    return this.http.post<{ data: unknown }>(`${environment.apiBaseUrl}/orders/${id}/returns`, payload);
  }
}
