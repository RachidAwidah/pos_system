import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface Role { id: string; name: string; name_ar: string; description_ar: string; is_system: boolean; }
export interface User { id: string; name: string; email: string; phone?: string | null; must_change_password: boolean; roles?: Role[]; }
export interface UserListResponse { data: User[]; meta: { current_page: number; last_page: number; total: number }; }
export interface UserPayload { name: string; email: string; phone?: string; password?: string; password_confirmation?: string; role_ids: string[]; must_change_password?: boolean; }

@Injectable({ providedIn: 'root' })
export class UserService {
  constructor(private readonly http: HttpClient) {}
  list(page = 1, perPage = 15): Observable<UserListResponse> {
    return this.http.get<UserListResponse>(`${environment.apiBaseUrl}/users`, { params: { page, per_page: perPage } });
  }
  listAll(): Observable<{ data: User[] }> {
    return this.http.get<{ data: User[] }>(`${environment.apiBaseUrl}/users`, { params: { all: '1' } });
  }
  roles(): Observable<{ data: Role[] }> {
    return this.http.get<{ data: Role[] }>(`${environment.apiBaseUrl}/roles`);
  }
  create(payload: UserPayload): Observable<{ data: User }> {
    return this.http.post<{ data: User }>(`${environment.apiBaseUrl}/users`, payload);
  }
  update(id: string, payload: UserPayload): Observable<{ data: User }> {
    return this.http.put<{ data: User }>(`${environment.apiBaseUrl}/users/${id}`, payload);
  }
  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiBaseUrl}/users/${id}`);
  }
}
