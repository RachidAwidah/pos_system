import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

export interface Customer {
  id: string;
  name: string;
  company_name?: string | null;
  email?: string | null;
  phone?: string | null;
  balance: string | number;
  loyalty_points: number;
  address?: string | null;
  tax_number?: string | null;
  credit_limit?: string | number;
}

export interface CustomerListResponse {
  data: Customer[];
  meta: { current_page: number; last_page: number; total: number };
}

export interface CustomerStatement {
  data: Array<{ id: string; entry_type: string; occurred_at: string; reference: string | null; debit: string; credit: string; balance_after: string }>;
  summary: { invoices_total: string; paid_total: string; due_total: string; balance: string };
  meta: { current_page: number; last_page: number };
}

@Injectable({ providedIn: 'root' })
export class CustomerService {
  constructor(private readonly http: HttpClient) {}

  statement(id: string, page = 1): Observable<CustomerStatement> {
    return this.http.get<CustomerStatement>(`${environment.apiBaseUrl}/customers/${id}/statement`, { params: { page, per_page: 25 } });
  }

  list(page = 1, perPage = 20, search = ''): Observable<CustomerListResponse> {
    return this.http.get<CustomerListResponse>(`${environment.apiBaseUrl}/customers`, {
      params: { page, per_page: perPage, search: search.trim() },
    });
  }

  listAll(search = ''): Observable<{ data: Customer[] }> {
    return this.http.get<{ data: Customer[] }>(`${environment.apiBaseUrl}/customers`, {
      params: { all: '1', search: search.trim() },
    });
  }

  create(payload: {
    name: string;
    company_name?: string;
    email?: string;
    phone?: string;
    address?: string;
    tax_number?: string;
    credit_limit?: number;
  }): Observable<{ data: Customer }> {
    return this.http.post<{ data: Customer }>(`${environment.apiBaseUrl}/customers`, payload);
  }

  update(id: string, payload: {
    name: string;
    company_name?: string;
    email?: string;
    phone?: string;
    address?: string;
    tax_number?: string;
    credit_limit?: number;
  }): Observable<{ data: Customer }> {
    return this.http.put<{ data: Customer }>(`${environment.apiBaseUrl}/customers/${id}`, payload);
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiBaseUrl}/customers/${id}`);
  }

  collectPayment(id: string, payload: { order_id: string; payment_method_id: string; amount: number; reference_number?: string; notes?: string }): Observable<{ data: unknown }> {
    return this.http.post<{ data: unknown }>(`${environment.apiBaseUrl}/customers/${id}/payments`, payload);
  }
}
