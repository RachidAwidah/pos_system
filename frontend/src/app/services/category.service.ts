import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

export interface Category {
  id: string;
  category_name: string;
  parent_id: string | null;
  depth?: number;
  path?: string;
  tree_products_count?: number;
  children?: Category[];
}

@Injectable({ providedIn: 'root' })
export class CategoryService {
  constructor(private readonly http: HttpClient) {}

  list(): Observable<{ data: Category[] }> {
    return this.http.get<{ data: Category[] }>(`${environment.apiBaseUrl}/categories`);
  }

  create(payload: { category_name: string; parent_id: string | null }): Observable<{ data: Category }> {
    return this.http.post<{ data: Category }>(`${environment.apiBaseUrl}/categories`, payload);
  }

  update(id: string, payload: { category_name: string; parent_id: string | null }): Observable<{ data: Category }> {
    return this.http.put<{ data: Category }>(`${environment.apiBaseUrl}/categories/${id}`, payload);
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiBaseUrl}/categories/${id}`);
  }
}
