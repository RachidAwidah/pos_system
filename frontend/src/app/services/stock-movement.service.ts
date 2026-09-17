import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

export interface StockMovement {
  id: string;
  product_id: string;
  warehouse_id: string;
  user_id: string | null;
  movement_type: string;
  quantity_delta: string;
  balance_before: string;
  balance_after: string;
  unit_cost: string | null;
  order_id: string | null;
  purchase_order_id: string | null;
  goods_receipt_id: string | null;
  inventory_count_id: string | null;
  transfer_batch_id: string | null;
  notes: string | null;
  occurred_at: string;
  product?: { id: string; product_name: string; sku: string };
  warehouse?: { id: string; name: string };
  user?: { id: string; full_name: string } | null;
}

export interface StockMovementListResponse {
  data: StockMovement[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

@Injectable({ providedIn: 'root' })
export class StockMovementService {
  constructor(private readonly http: HttpClient) {}

  list(params: Record<string, string | number | boolean | null | undefined>): Observable<StockMovementListResponse> {
    let httpParams = new HttpParams();

    for (const [key, value] of Object.entries(params)) {
      if (value !== null && value !== undefined && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }

    return this.http.get<StockMovementListResponse>(`${environment.apiBaseUrl}/stock-movements`, { params: httpParams });
  }

  listAll(params: Record<string, string | number | boolean | null | undefined> = {}): Observable<{ data: StockMovement[] }> {
    let httpParams = new HttpParams().set('all', '1');

    for (const [key, value] of Object.entries(params)) {
      if (value !== null && value !== undefined && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    }

    return this.http.get<{ data: StockMovement[] }>(`${environment.apiBaseUrl}/stock-movements`, { params: httpParams });
  }
}
