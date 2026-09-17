import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface PurchaseOrder {
  id: string; purchase_order_number: string; status: string; total_amount: string | number;
  supplier_id: string; warehouse_id: string; ordered_at?: string | null; expected_at?: string | null;
  supplier?: { id: string; name: string; balance?: string | number } | null;
  warehouse?: { name: string } | null; items?: Array<{ id: string; product_name: string; ordered_quantity: string | number; received_quantity?: string | number; outstanding_quantity?: string | number; unit_cost: string | number; total_amount: string | number }>;
  goods_receipts?: Array<{ id: string; receipt_number: string; status: string; total_amount: string | number; supplier_reference?: string | null; notes?: string | null; received_at: string }>;
}
export interface PurchaseOrderListResponse { data: PurchaseOrder[]; meta: { current_page: number; last_page: number; total: number }; }
export interface PurchaseOrderPayload { supplier_id: string; warehouse_id: string; expected_at?: string; notes?: string; items: Array<{ product_id: string; quantity: number; unit_cost: number; discount_amount?: number }>; }
export interface PurchaseOrderFilters { supplier_id?: string; status?: string; }

@Injectable({ providedIn: 'root' })
export class PurchaseOrderService {
  constructor(private readonly http: HttpClient) {}
  list(page = 1, perPage = 20, filters: PurchaseOrderFilters = {}): Observable<PurchaseOrderListResponse> {
    return this.http.get<PurchaseOrderListResponse>(`${environment.apiBaseUrl}/purchase-orders`, {
      params: { page, per_page: perPage, ...filters },
    });
  }
  listAll(filters: PurchaseOrderFilters = {}): Observable<{ data: PurchaseOrder[] }> {
    return this.http.get<{ data: PurchaseOrder[] }>(`${environment.apiBaseUrl}/purchase-orders`, {
      params: { all: '1', ...filters },
    });
  }
  create(payload: PurchaseOrderPayload): Observable<{ data: PurchaseOrder }> {
    return this.http.post<{ data: PurchaseOrder }>(`${environment.apiBaseUrl}/purchase-orders`, payload);
  }
  show(id: string): Observable<{ data: PurchaseOrder }> {
    return this.http.get<{ data: PurchaseOrder }>(`${environment.apiBaseUrl}/purchase-orders/${id}`);
  }
  send(id: string): Observable<{ data: PurchaseOrder }> {
    return this.http.post<{ data: PurchaseOrder }>(`${environment.apiBaseUrl}/purchase-orders/${id}/send`, {});
  }
  cancel(id: string, reason: string): Observable<{ data: PurchaseOrder }> {
    return this.http.post<{ data: PurchaseOrder }>(`${environment.apiBaseUrl}/purchase-orders/${id}/cancel`, { reason });
  }
  receive(id: string, payload: { items: Array<{ purchase_order_item_id: string; quantity: number; batch_number?: string; expires_at?: string }>; supplier_reference?: string; notes?: string }): Observable<{ data: unknown }> {
    return this.http.post<{ data: unknown }>(`${environment.apiBaseUrl}/purchase-orders/${id}/receipts`, payload);
  }
}
