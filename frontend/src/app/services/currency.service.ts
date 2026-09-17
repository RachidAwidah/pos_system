import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { ExchangeRateService } from './exchange-rate.service';

export interface CurrencyInfo {
  code: string;
  symbol: string;
  name: string;
  nameAr: string;
}

@Injectable({
  providedIn: 'root',
})
export class CurrencyService {
  private readonly CURRENCIES: { [code: string]: CurrencyInfo } = {
    USD: { code: 'USD', symbol: '$', name: 'US Dollar', nameAr: 'دولار أمريكي' },
    SYP: { code: 'SYP', symbol: 'SYP', name: 'Syrian Pound', nameAr: 'ليرة سورية' },
    TRY: { code: 'TRY', symbol: '₺', name: 'Turkish Lira', nameAr: 'ليرة تركية' },
  };

  private readonly STORAGE_KEY = 'display_currency';
  private readonly DEFAULT_CURRENCY = 'USD';

  private currentCurrencySubject = new BehaviorSubject<string>(this.getStoredCurrency());
  public currentCurrency$ = this.currentCurrencySubject.asObservable();

  constructor(private exchangeRateService: ExchangeRateService) {
    // Load rates on initialization
    this.exchangeRateService.getRates().subscribe();
  }

  /**
   * Get current display currency code.
   */
  getCurrentCurrency(): string {
    return this.currentCurrencySubject.value;
  }

  /**
   * Set display currency.
   */
  setCurrency(currencyCode: string): void {
    if (!this.CURRENCIES[currencyCode]) {
      console.warn(`Unsupported currency: ${currencyCode}`);
      return;
    }

    localStorage.setItem(this.STORAGE_KEY, currencyCode);
    this.currentCurrencySubject.next(currencyCode);
  }

  applyDisplaySettings(defaultCode: string, enabledCodes: string[]): void {
    const code = this.isSupported(defaultCode) && enabledCodes.includes(defaultCode) ? defaultCode : 'USD';
    const previousDefault = localStorage.getItem('default_display_currency');
    if (previousDefault !== code || !enabledCodes.includes(this.getCurrentCurrency())) {
      this.setCurrency(code);
    }
    localStorage.setItem('default_display_currency', code);
  }

  /**
   * Get currency info for a code.
   */
  getCurrencyInfo(code: string): CurrencyInfo | undefined {
    return this.CURRENCIES[code];
  }

  /**
   * Get all supported currencies.
   */
  getSupportedCurrencies(): CurrencyInfo[] {
    return Object.values(this.CURRENCIES);
  }

  /**
   * Format amount with currency symbol.
   */
  formatAmount(amount: number, currencyCode?: string): string {
    const code = currencyCode || this.currentCurrencySubject.value;
    const info = this.CURRENCIES[code];

    if (!info) {
      return `$${amount.toFixed(2)}`;
    }

    // Convert amount if needed
    let displayAmount = amount;
    if (code !== 'USD') {
      displayAmount = this.exchangeRateService.convert(amount, code);
    }

    // Format based on currency
    switch (code) {
      case 'USD':
        return `$${displayAmount.toFixed(2)}`;
      case 'SYP':
        return `${displayAmount.toLocaleString('en-US', { maximumFractionDigits: 0 })} ل.س`;
      case 'TRY':
        return `₺${displayAmount.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
      default:
        return `${info.symbol}${displayAmount.toFixed(2)}`;
    }
  }

  /**
   * Format amount for display in templates.
   */
  formatForDisplay(amount: number, currencyCode?: string): { symbol: string; amount: string; code: string } {
    const code = currencyCode || this.currentCurrencySubject.value;
    const info = this.CURRENCIES[code];

    if (!info) {
      return { symbol: '$', amount: amount.toFixed(2), code: 'USD' };
    }

    // Convert amount if needed
    let displayAmount = amount;
    if (code !== 'USD') {
      displayAmount = this.exchangeRateService.convert(amount, code);
    }

    return {
      symbol: info.symbol,
      amount: displayAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
      code: code,
    };
  }

  /**
   * Get exchange rate for a currency.
   */
  getExchangeRate(currencyCode: string): number {
    if (currencyCode === 'USD') {
      return 1.0;
    }
    return this.exchangeRateService.getCurrentRates()[currencyCode] || 1.0;
  }

  /**
   * Check if a currency is supported.
   */
  isSupported(currencyCode: string): boolean {
    return !!this.CURRENCIES[currencyCode];
  }

  private getStoredCurrency(): string {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored && this.CURRENCIES[stored]) {
        return stored;
      }
    } catch (error) {
      console.error('Failed to get stored currency:', error);
    }
    return this.DEFAULT_CURRENCY;
  }
}
