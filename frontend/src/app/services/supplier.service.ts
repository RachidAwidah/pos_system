import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface Supplier {
  id: string;
  name: string;
  company_name?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  tax_number?: string | null;
  payable_limit?: string | number;
  balance: string | number;
  purchases_total?: string | number;
}

export interface SupplierListResponse {
  data: Supplier[];
  meta: { current_page: number; last_page: number; total: number };
}

export interface SupplierPayment {
  id: string;
  amount: string | number;
  status: string;
  reference_number?: string | null;
  notes?: string | null;
  paid_at: string;
  payment_method?: { id: string; name: string; code: string } | null;
  purchase_order?: { id: string; purchase_order_number: string } | null;
  user?: { id: string; full_name: string } | null;
}

export interface SupplierLedgerEntry {
  id: string;
  entry_type: string;
  amount_delta: string | number;
  balance_before: string | number;
  balance_after: string | number;
  description?: string | null;
  occurred_at: string;
  supplier_payment_id?: string | null;
  purchase_order?: { id: string; purchase_order_number: string } | null;
  goods_receipt?: { id: string; receipt_number: string } | null;
}

interface PaginatedResponse<T> {
  data: T[];
  meta: { current_page: number; last_page: number; total: number };
}

type SupplierPayload = Omit<Supplier, 'id' | 'balance' | 'purchases_total'>;

@Injectable({ providedIn: 'root' })
export class SupplierService {
  constructor(private readonly http: HttpClient) {}

  products(id: string, page = 1): Observable<{ data: Array<{ id: string; supplier_sku: string | null; last_cost?: string; minimum_order_quantity: string; is_preferred: boolean; product: { product_name: string; sku: string } | null }>; meta: { current_page: number; last_page: number } }> {
    return this.http.get<{ data: Array<{ id: string; supplier_sku: string | null; last_cost?: string; minimum_order_quantity: string; is_preferred: boolean; product: { product_name: string; sku: string } | null }>; meta: { current_page: number; last_page: number } }>(`${environment.apiBaseUrl}/suppliers/${id}/products`, { params: { page, per_page: 25 } });
  }
  list(page = 1, perPage = 20, search = ''): Observable<SupplierListResponse> {
    return this.http.get<SupplierListResponse>(`${environment.apiBaseUrl}/suppliers`, { params: { page, per_page: perPage, search: search.trim() } });
  }
  listAll(search = ''): Observable<{ data: Supplier[] }> {
    return this.http.get<{ data: Supplier[] }>(`${environment.apiBaseUrl}/suppliers`, { params: { all: '1', search: search.trim() } });
  }
  create(payload: SupplierPayload): Observable<{ data: Supplier }> {
    return this.http.post<{ data: Supplier }>(`${environment.apiBaseUrl}/suppliers`, payload);
  }
  update(id: string, payload: SupplierPayload): Observable<{ data: Supplier }> {
    return this.http.put<{ data: Supplier }>(`${environment.apiBaseUrl}/suppliers/${id}`, payload);
  }
  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiBaseUrl}/suppliers/${id}`);
  }
  pay(id: string, payload: { payment_method_id: string; amount: number; purchase_order_id?: string; reference_number?: string; notes?: string }): Observable<{ data: unknown }> {
    return this.http.post<{ data: unknown }>(`${environment.apiBaseUrl}/suppliers/${id}/payments`, payload);
  }
  payments(id: string, page = 1, perPage = 20): Observable<PaginatedResponse<SupplierPayment>> {
    return this.http.get<PaginatedResponse<SupplierPayment>>(`${environment.apiBaseUrl}/suppliers/${id}/payments`, {
      params: { page, per_page: perPage },
    });
  }
  ledger(id: string, page = 1, perPage = 20): Observable<PaginatedResponse<SupplierLedgerEntry>> {
    return this.http.get<PaginatedResponse<SupplierLedgerEntry>>(`${environment.apiBaseUrl}/suppliers/${id}/ledger`, {
      params: { page, per_page: perPage },
    });
  }
}
