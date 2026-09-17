import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

export interface ProductInventory {
  quantity_on_hand: string | number;
  quantity_reserved: string | number;
  quantity_available: string | number;
  reorder_level: string | number;
  warehouse_id?: string;
  warehouse_name?: string;
}

export interface Product {
  id: string;
  product_name: string;
  sku: string;
  barcode: string | null;
  type: string;
  price: string | number;
  image_url: string | null;
  cost_price?: string | number;
  description?: string | null;
  category?: { id: string; category_name: string } | null;
  unit?: { id: string; symbol: string } | null;
  tax?: { id: string } | null;
  inventory?: ProductInventory | null;
}

export interface ProductListResponse {
  data: Product[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface ProductReferenceData {
  categories: Array<{ id: string; category_name: string; parent_id: string | null; path?: string }>;
  units: Array<{ id: string; name: string; symbol: string; decimal_places: number }>;
  taxes: Array<{ id: string; tax_name: string; tax_percentage: string | number }>;
  warehouses: Array<{ id: string; name: string; code: string }>;
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  constructor(private readonly http: HttpClient) {}

  referenceData(): Observable<{ data: ProductReferenceData }> {
    return this.http.get<{ data: ProductReferenceData }>(`${environment.apiBaseUrl}/products/reference-data`);
  }

  create(payload: Record<string, string | number | null>): Observable<{ data: Product }> {
    return this.http.post<{ data: Product }>(`${environment.apiBaseUrl}/products`, payload);
  }

  show(id: string): Observable<{ data: Product }> {
    return this.http.get<{ data: Product }>(`${environment.apiBaseUrl}/products/${id}`);
  }

  update(id: string, payload: Record<string, string | number | null>): Observable<{ data: Product }> {
    return this.http.patch<{ data: Product }>(`${environment.apiBaseUrl}/products/${id}`, payload);
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiBaseUrl}/products/${id}`);
  }

  list(filters: {
    search?: string;
    low_stock?: boolean;
    category_id?: string | null;
    type?: string | null;
    per_page: number;
    page: number;
  }): Observable<ProductListResponse> {
    let params = new HttpParams()
      .set('per_page', filters.per_page)
      .set('page', filters.page);

    if (filters.search?.trim()) {
      params = params.set('search', filters.search.trim());
    }

    if (filters.low_stock) {
      params = params.set('low_stock', '1');
    }

    if (filters.category_id) {
      params = params.set('category_id', filters.category_id);
    }

    if (filters.type) {
      params = params.set('type', filters.type);
    }

    return this.http.get<ProductListResponse>(`${environment.apiBaseUrl}/products`, { params });
  }

  listAll(filters: { search?: string; low_stock?: boolean; category_id?: string | null; type?: string | null } = {}): Observable<{ data: Product[] }> {
    let params = new HttpParams().set('all', '1');

    if (filters.search?.trim()) {
      params = params.set('search', filters.search.trim());
    }

    if (filters.low_stock) {
      params = params.set('low_stock', '1');
    }

    if (filters.category_id) {
      params = params.set('category_id', filters.category_id);
    }

    if (filters.type) {
      params = params.set('type', filters.type);
    }

    return this.http.get<{ data: Product[] }>(`${environment.apiBaseUrl}/products`, { params });
  }
}
