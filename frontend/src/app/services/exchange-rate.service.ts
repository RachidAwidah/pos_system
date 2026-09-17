import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, of } from 'rxjs';
import { map, catchError, tap } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface ExchangeRateResponse {
  base_currency: string;
  rates: { [currency: string]: number };
  supported_currencies: string[];
  cached_at: string;
}

export interface ExchangeRate {
  currency: string;
  base_currency: string;
  rate: number;
}

@Injectable({
  providedIn: 'root',
})
export class ExchangeRateService {
  private ratesSubject = new BehaviorSubject<{ [currency: string]: number }>({});
  public rates$ = this.ratesSubject.asObservable();

  private lastFetchTime: Date | null = null;
  private cacheKey = 'exchange_rates_cache';
  private cacheDuration = 4 * 60 * 60 * 1000; // 4 hours in milliseconds

  constructor(private http: HttpClient) {
    this.loadFromCache();
  }

  /**
   * Get current exchange rates (from cache or API).
   */
  getRates(): Observable<ExchangeRateResponse> {
    // Check if we have cached data that's still valid
    if (this.lastFetchTime && Date.now() - this.lastFetchTime.getTime() < this.cacheDuration) {
      return of({
        base_currency: 'USD',
        rates: this.ratesSubject.value,
        supported_currencies: ['USD', 'SYP', 'TRY'],
        cached_at: this.lastFetchTime.toISOString(),
      });
    }

    return this.http.get<ExchangeRateResponse>(`${environment.apiBaseUrl}/exchange-rate`).pipe(
      tap(response => {
        this.ratesSubject.next(response.rates);
        this.lastFetchTime = new Date();
        this.saveToCache(response);
      }),
      catchError(error => {
        console.error('Failed to fetch exchange rates:', error);
        // Return cached rates if available
        return of({
          base_currency: 'USD',
          rates: this.ratesSubject.value,
          supported_currencies: ['USD', 'SYP', 'TRY'],
          cached_at: this.lastFetchTime?.toISOString() || new Date().toISOString(),
        });
      })
    );
  }

  /**
   * Force refresh exchange rates.
   */
  refreshRates(): Observable<ExchangeRateResponse> {
    return this.http.post<ExchangeRateResponse>(`${environment.apiBaseUrl}/exchange-rate/refresh`, {}).pipe(
      tap(response => {
        this.ratesSubject.next(response.rates);
        this.lastFetchTime = new Date();
        this.saveToCache(response);
      }),
      catchError(error => {
        console.error('Failed to refresh exchange rates:', error);
        throw error;
      })
    );
  }

  /**
   * Get rate for a specific currency.
   */
  getRate(currency: string): Observable<number> {
    return this.http.get<ExchangeRate>(`${environment.apiBaseUrl}/exchange-rate/${currency}`).pipe(
      map(response => response.rate),
      catchError(error => {
        console.error(`Failed to get rate for ${currency}:`, error);
        // Return cached rate if available
        return of(this.ratesSubject.value[currency] || 1.0);
      })
    );
  }

  /**
   * Convert amount from USD to target currency.
   */
  convert(amount: number, targetCurrency: string): number {
    if (targetCurrency === 'USD') {
      return amount;
    }

    const rate = this.ratesSubject.value[targetCurrency];
    if (!rate) {
      console.warn(`No rate found for ${targetCurrency}, using 1.0`);
      return amount;
    }

    return Math.round(amount * rate * 100) / 100;
  }

  /**
   * Get current cached rates without making API call.
   */
  getCurrentRates(): { [currency: string]: number } {
    return this.ratesSubject.value;
  }

  /**
   * Check if rates are cached and valid.
   */
  areRatesCached(): boolean {
    return this.lastFetchTime !== null && Date.now() - this.lastFetchTime.getTime() < this.cacheDuration;
  }

  /**
   * Get last fetch time.
   */
  getLastFetchTime(): Date | null {
    return this.lastFetchTime;
  }

  private loadFromCache(): void {
    try {
      const cached = localStorage.getItem(this.cacheKey);
      if (cached) {
        const data = JSON.parse(cached);
        this.ratesSubject.next(data.rates || {});
        this.lastFetchTime = data.timestamp ? new Date(data.timestamp) : null;
      }
    } catch (error) {
      console.error('Failed to load exchange rates from cache:', error);
    }
  }

  private saveToCache(response: ExchangeRateResponse): void {
    try {
      localStorage.setItem(this.cacheKey, JSON.stringify({
        rates: response.rates,
        timestamp: new Date().toISOString(),
      }));
    } catch (error) {
      console.error('Failed to save exchange rates to cache:', error);
    }
  }
}