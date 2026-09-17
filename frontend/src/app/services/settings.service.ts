import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { map, tap } from 'rxjs/operators';

import { environment } from '../../environments/environment';

export interface StoreSetting {
  id: string;
  group: string;
  key: string;
  value: string | number | boolean | null | unknown[];
  type: string;
  is_public: boolean;
}

@Injectable({ providedIn: 'root' })
export class SettingsService {
  private readonly updatedSubject = new Subject<StoreSetting[]>();
  readonly updated$ = this.updatedSubject.asObservable();
  constructor(private readonly http: HttpClient) {}

  list(): Observable<{ data: StoreSetting[] }> {
    return this.http.get<{ data: StoreSetting[] }>(`${environment.apiBaseUrl}/settings`);
  }

  bulkUpdate(settings: Record<string, unknown>): Observable<{ data: StoreSetting[] }> {
    return this.http.put<{ data: StoreSetting[] }>(`${environment.apiBaseUrl}/settings`, { settings }).pipe(
      tap(response => this.updatedSubject.next(response.data)),
    );
  }

  getValue(key: string): Observable<unknown> {
    return this.list().pipe(
      map(res => res.data.find(s => s.key === key)?.value ?? null),
    );
  }
}
