import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface Permission {
  id: string;
  key: string;
  name_ar: string;
  description_ar: string;
}
export interface Role {
  id: string;
  name: string;
  name_ar: string;
  description_ar: string;
  is_system: boolean;
  permissions?: Permission[];
}

@Injectable({ providedIn: 'root' })
export class RoleService {
  constructor(private readonly http: HttpClient) {}
  list(): Observable<{ data: Role[] }> { return this.http.get<{ data: Role[] }>(`${environment.apiBaseUrl}/roles`); }
  permissions(): Observable<{ data: Record<string, Permission[]> }> { return this.http.get<{ data: Record<string, Permission[]> }>(`${environment.apiBaseUrl}/permissions`); }
  create(payload: { name: string; permission_ids: string[] }): Observable<{ data: Role }> { return this.http.post<{ data: Role }>(`${environment.apiBaseUrl}/roles`, payload); }
  update(id: string, payload: { name: string; permission_ids: string[] }): Observable<{ data: Role }> { return this.http.put<{ data: Role }>(`${environment.apiBaseUrl}/roles/${id}`, payload); }
  delete(id: string): Observable<void> { return this.http.delete<void>(`${environment.apiBaseUrl}/roles/${id}`); }
}
