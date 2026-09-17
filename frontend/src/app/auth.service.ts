import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';

import { environment } from '../environments/environment';
import { TokenService } from './token.service';

export interface AuthPermission {
  id?: string;
  key?: string;
  name?: string;
}

export interface AuthRole {
  id: string;
  name: string;
}

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  must_change_password: boolean;
  roles: AuthRole[];
  permissions: Array<AuthPermission | string>;
}

interface LoginResponse {
  token: string;
  token_type: string;
  user: AuthUser;
}

interface MeResponse {
  user: AuthUser;
}

interface MessageResponse {
  message: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly userKey = 'pos_user';
  private readonly userSubject = new BehaviorSubject<AuthUser | null>(this.getStoredUser());

  readonly user$ = this.userSubject.asObservable();

  constructor(private readonly http: HttpClient, private readonly tokenService: TokenService) {}

  login(payload: { email: string; password: string; device_name?: string }): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${environment.apiBaseUrl}/login`, {
      ...payload,
      device_name: payload.device_name ?? 'angular-pos',
    }).pipe(tap(response => {
      this.tokenService.setToken(response.token);
      this.storeUser(response.user);
    }));
  }

  me(): Observable<MeResponse> {
    return this.http.get<MeResponse>(`${environment.apiBaseUrl}/me`).pipe(
      tap(response => this.storeUser(response.user)),
    );
  }

  logout(): Observable<MessageResponse> {
    return this.http.post<MessageResponse>(`${environment.apiBaseUrl}/logout`, {}).pipe(
      tap(() => this.clearSession()),
    );
  }

  logoutAll(): Observable<MessageResponse> {
    return this.http.post<MessageResponse>(`${environment.apiBaseUrl}/logout-all`, {}).pipe(
      tap(() => this.clearSession()),
    );
  }

  changePassword(payload: { old_password: string; new_password: string; new_password_confirmation: string }): Observable<MessageResponse> {
    return this.http.post<MessageResponse>(`${environment.apiBaseUrl}/password/change`, payload).pipe(
      tap(() => this.clearSession()),
    );
  }

  clearSession(): void {
    this.tokenService.clearToken();
    localStorage.removeItem(this.userKey);
    localStorage.removeItem('pos_current_shift');
    this.userSubject.next(null);
  }

  isAuthenticated(): boolean {
    return !!this.tokenService.getToken();
  }

  getCurrentUser(): AuthUser | null {
    return this.userSubject.value;
  }

  hasPermission(permission: string): boolean {
    return this.isAdmin()
      || this.normalizePermissions(this.userSubject.value?.permissions ?? []).includes(permission);
  }

  isAdmin(): boolean {
    return (this.userSubject.value?.roles ?? []).some(role => role.name.toLowerCase() === 'admin');
  }

  hasAnyPermission(permissions: string[]): boolean {
    return permissions.some(permission => this.hasPermission(permission));
  }

  defaultRoute(): string {
    const destinations = [
      { permission: 'reports.view_financial', route: '/pages/dashboard' },
      { permission: 'sales.create', route: '/pages/sales' },
      { permission: 'sales.view', route: '/pages/orders' },
      { permission: 'products.view', route: '/pages/products' },
      { permission: 'inventory.view', route: '/pages/inventory' },
      { permission: 'customers.view', route: '/pages/customers' },
      { permission: 'suppliers.view', route: '/pages/suppliers' },
      { permission: 'purchases.view', route: '/pages/purchases' },
    ];

    return destinations.find(destination => this.hasPermission(destination.permission))?.route ?? '/pages/403';
  }

  private normalizePermissions(permissions: Array<AuthPermission | string>): string[] {
    return permissions.map(permission => typeof permission === 'string' ? permission : permission.key ?? permission.name ?? '').filter(Boolean);
  }

  private getStoredUser(): AuthUser | null {
    const raw = localStorage.getItem(this.userKey);
    if (!raw) return null;
    try {
      const parsed: unknown = JSON.parse(raw);
      return this.isAuthUser(parsed) ? parsed : null;
    } catch (error) {
      if (error instanceof SyntaxError) localStorage.removeItem(this.userKey);
      return null;
    }
  }

  private storeUser(user: AuthUser): void {
    localStorage.setItem(this.userKey, JSON.stringify(user));
    this.userSubject.next(user);
  }

  private isAuthUser(value: unknown): value is AuthUser {
    if (typeof value !== 'object' || value === null) return false;
    return 'id' in value && typeof value.id === 'string'
      && 'email' in value && typeof value.email === 'string'
      && 'roles' in value && Array.isArray(value.roles)
      && 'permissions' in value && Array.isArray(value.permissions);
  }
}
