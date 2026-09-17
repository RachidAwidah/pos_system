import { Pipe, PipeTransform } from '@angular/core';
import { CurrencyService } from '../../services/currency.service';

@Pipe({
  name: 'appCurrency',
  pure: false,
})
export class AppCurrencyPipe implements PipeTransform {
  constructor(private currencyService: CurrencyService) {}

  transform(amount: number | string | null | undefined, currencyCode?: string): string {
    if (amount === null || amount === undefined) {
      return '';
    }

    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;

    if (isNaN(numAmount)) {
      return '';
    }

    return this.currencyService.formatAmount(numAmount, currencyCode);
  }
}