import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

export interface ReportOverview {
  period: { from: string; to: string };
  summary: {
    orders_count: number;
    returns_count: number;
    net_total: string | number;
    net_sales: string | number;
    gross_profit: string | number;
    average_order_value: string | number;
    gross_margin_percentage: string | number;
  };
  trend: Array<{ date: string; net_sales: string | number; gross_profit: string | number }>;
  top_products: Array<{ product_name: string; net_quantity: string | number; net_sales: string | number }>;
}

@Injectable({ providedIn: 'root' })
export class ReportService {
  constructor(private readonly http: HttpClient) {}

  overview(from: string, to: string): Observable<{ data: ReportOverview }> {
    const params = new HttpParams().set('from', from).set('to', to);
    return this.http.get<{ data: ReportOverview }>(`${environment.apiBaseUrl}/reports/overview`, { params });
  }
}
