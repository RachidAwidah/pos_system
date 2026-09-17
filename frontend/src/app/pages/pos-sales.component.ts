import { Component, NgZone, ChangeDetectorRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { StripeCardNumberElement } from '@stripe/stripe-js';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { PaymentMethod, PosCustomer, PosProduct, Register, Shift, ShiftService, LastClosedShift } from '../services/shift.service';
import { StripeService } from '../services/stripe.service';
import { CurrencyService } from '../services/currency.service';
import { ExchangeRateService } from '../services/exchange-rate.service';

interface CartItem {
  id: string;
  name: string;
  price: number;
  quantity: number;
  taxRate: number;
  availableQuantity: number;
}

@Component({
  selector: 'ngx-pos-sales',
  templateUrl: './pos-sales.component.html',
  styleUrls: ['./pos-sales.component.scss'],
})
export class PosSalesComponent {
  Number = Number;
  registers: Register[] = [];
  paymentMethods: PaymentMethod[] = [];
  customers: PosCustomer[] = [];
  catalogProducts: PosProduct[] = [];
  selectedRegisterId = '';
  openingCash = 0;
  shiftNotes = '';
  currentShift: Shift | null = null;
  showShiftForm = false;
  shiftLoading = false;
  registersLoading = false;
  shiftError = '';
  showCloseShiftForm = false;
  closingShiftId: string | null = null;
  administrativeClose = false;
  adminOverrideReason = '';
  cashSummary: Record<string, string> | null = null;
  summaryLoading = false;
  private openingRequest = 0;
  closingCash: number | null = null;
  closingNotes = '';
  canForceCloseShifts = false;
  forceCloseReason = '';
  lastClosingCash: string | null = null;
  categories: string[] = [];
  selectedCategory = '';
  productSearch = '';

  products: PosProduct[] = [];
  cart: CartItem[] = [];
  paymentMethodId = '';
  customerId = '';
  paymentAmount: number | null = null;
  productsLoading = false;
  productsError = '';
  checkoutError = '';
  checkoutLoading = false;
  productPage = 1;
  productLastPage = 1;

  showStripeModal = false;
  stripeLoading = false;
  stripeError = '';
  cardNumberComplete = false;
  cardExpiryComplete = false;
  cardCvcComplete = false;
  private cardNumberElement: StripeCardNumberElement | null = null;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private cardExpiryElement: any = null;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private cardCvcElement: any = null;

  constructor(
    private readonly shiftService: ShiftService,
    private readonly feedback: OperationFeedbackService,
    private readonly stripeService: StripeService,
    private readonly ngZone: NgZone,
    private readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly exchangeRateService: ExchangeRateService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    this.currencyService.currentCurrency$.subscribe(() => this.cdr.detectChanges());
    this.categories = [this.translate.instant('SALES.LOAD_ALL_CATEGORIES')];
    this.selectedCategory = this.categories[0];
    this.currentShift = this.restoreCurrentShift();
    this.loadReferenceData();
  }

  loadReferenceData(): void {
    this.productsLoading = true;
    this.shiftService.referenceData().subscribe({
      next: response => {
        this.registers = response.data.registers;
        this.paymentMethods = response.data.payment_methods;
        this.customers = response.data.customers;
        this.canForceCloseShifts = response.data.can_force_close_shifts;
        this.currentShift = response.data.current_shift;
        if (this.currentShift) {
          localStorage.setItem('pos_current_shift', JSON.stringify(this.currentShift));
          this.showShiftForm = false;
        } else {
          localStorage.removeItem('pos_current_shift');
        }
        this.loadProducts();
      },
      error: () => {
        this.productsLoading = false;
        this.productsError = this.translate.instant('SALES.PRODUCTS_LOAD_FAILED');
      },
    });
  }

  loadProducts(page = 1, append = false): void {
    this.productsLoading = true;
    this.shiftService.products(page).subscribe({
      next: response => {
        this.catalogProducts = append ? [...this.catalogProducts, ...response.data] : response.data;
        this.products = this.catalogProducts;
        this.productPage = response.meta.current_page;
        this.productLastPage = response.meta.last_page;
        this.productsLoading = false;
      },
      error: () => {
        this.productsLoading = false;
        this.productsError = this.translate.instant('SALES.PRODUCTS_LOAD_FAILED');
      },
    });
  }

  openShiftForm(): void {
    this.showShiftForm = true;
    this.shiftError = '';
    this.lastClosingCash = null;
    this.selectedRegisterId = this.selectedRegisterId || this.registers[0]?.id || '';
    this.onRegisterChange(this.selectedRegisterId);
  }

  onRegisterChange(registerId: string): void {
    this.openingRequest++;
    this.selectedRegisterId = registerId;
    this.lastClosingCash = null;
    this.openingCash = 0;
    if (registerId && !this.selectedRegister?.open_shift) {
      this.fetchLastClosingCash(registerId);
    }
  }

  continueShift(): void {
    const occupied = this.selectedRegister?.open_shift;
    if (!occupied?.owned_by_current_user) return;
    this.currentShift = {
      id: occupied.id,
      register_id: this.selectedRegisterId,
      status: 'open',
      opening_cash: occupied.opening_cash,
      opened_at: occupied.opened_at,
    };
    localStorage.setItem('pos_current_shift', JSON.stringify(this.currentShift));
    this.showShiftForm = false;
    this.feedback.success(this.translate.instant('SALES.SHIFT_CONTINUED'));
  }

  openShift(): void {
    if (this.shiftLoading) return;
    if (!this.selectedRegisterId) {
      this.shiftError = this.translate.instant('SALES.REGISTER_REQUIRED');
      return;
    }

    if (this.selectedRegister?.open_shift) {
      this.shiftError = this.translate.instant('SALES.REGISTER_HAS_SHIFT');
      return;
    }

    this.shiftLoading = true;
    this.shiftError = '';
    this.shiftService.open(this.selectedRegisterId, Number(this.openingCash), this.shiftNotes).subscribe({
      next: response => {
        this.currentShift = response.data;
        localStorage.setItem('pos_current_shift', JSON.stringify(this.currentShift));
        this.showShiftForm = false;
        this.shiftLoading = false;
        this.feedback.success(this.translate.instant('SALES.OPEN_SUCCESS'));
      },
      error: error => {
        this.shiftLoading = false;
        this.shiftError = this.feedback.error(error, this.translate.instant('SALES.OPEN_FAILED'));
      },
    });
  }

  forceCloseSelectedShift(): void {
    if (this.shiftLoading) return;
    const shiftId = this.closingShiftId;
    if (!shiftId || !this.administrativeClose || !this.canForceCloseShifts || !this.validClosingCash) return;
    if (!this.forceCloseReason.trim()) {
      this.shiftError = this.translate.instant('SALES.ADMIN_CLOSE_REQUIRED');
      return;
    }

    this.shiftLoading = true;
    this.shiftError = '';
    this.shiftService.forceClose(shiftId, Number(this.closingCash), this.forceCloseReason).subscribe({
      next: () => {
        this.shiftLoading = false;
        this.closingCash = null;
        this.forceCloseReason = '';
        this.showCloseShiftForm = false;
        this.feedback.success(this.translate.instant('SALES.ADMIN_CLOSE_SUCCESS'));
        this.loadReferenceData();
      },
      error: error => {
        this.shiftLoading = false;
        this.shiftError = this.feedback.error(error, this.translate.instant('SALES.ADMIN_CLOSE_FAILED'));
      },
    });
  }

  closeShift(): void {
    if (this.shiftLoading || this.summaryLoading || !this.cashSummary || !this.validClosingCash) return;
    if (this.largeClosingDifference && !this.canForceCloseShifts) return;
    if (this.administrativeClose) {
      this.forceCloseSelectedShift();
      return;
    }
    if (!this.currentShift || this.currentShift.id !== this.closingShiftId) {
      return;
    }

    this.shiftLoading = true;
    this.shiftError = '';
    this.shiftService.close(this.currentShift.id, Number(this.closingCash), this.closingNotes || undefined, this.adminOverrideReason.trim() || undefined).subscribe({
      next: () => {
        this.currentShift = null;
        localStorage.removeItem('pos_current_shift');
        this.showCloseShiftForm = false;
        this.shiftLoading = false;
        this.closingCash = 0;
        this.closingNotes = '';
        this.cart = [];
        this.feedback.success(this.translate.instant('SALES.CLOSE_SUCCESS'));
        this.loadReferenceData();
      },
      error: error => {
        this.shiftLoading = false;
        this.shiftError = this.feedback.error(error, this.translate.instant('SALES.CLOSE_FAILED'));
      },
    });
  }

  openCloseShiftForm(administrative = false): void {
    if (this.shiftLoading) return;
    const shiftId = administrative ? this.selectedRegister?.open_shift?.id : this.currentShift?.id;
    if (!shiftId || (administrative && !this.canForceCloseShifts)) return;
    this.closingShiftId = shiftId;
    this.administrativeClose = administrative;
    this.closingCash = null;
    this.closingNotes = '';
    this.adminOverrideReason = '';
    this.forceCloseReason = '';
    this.cashSummary = null;
    this.shiftError = '';
    this.showShiftForm = false;
    this.showCloseShiftForm = true;
    this.summaryLoading = true;
    this.shiftService.summary(shiftId).subscribe({
      next: response => {
        if (this.closingShiftId !== shiftId) return;
        this.cashSummary = response.data;
        this.summaryLoading = false;
      },
      error: error => {
        if (this.closingShiftId !== shiftId) return;
        this.summaryLoading = false;
        this.shiftError = this.feedback.error(error, this.translate.instant('SALES.SUMMARY_FAILED'));
      },
    });
  }

  get validClosingCash(): boolean {
    return this.closingCash !== null && this.closingCash !== undefined && Number.isFinite(Number(this.closingCash))
      && Number(this.closingCash) >= 0 && /^\d+(\.\d{1,2})?$/.test(String(this.closingCash));
  }

  get closingDifference(): number {
    return this.cashSummary && this.validClosingCash
      ? this.roundMoney(Number(this.closingCash) - Number(this.cashSummary.expected_cash)) : 0;
  }

  get largeClosingDifference(): boolean {
    return Math.abs(this.closingDifference) > 50;
  }

  get filteredProducts() {
    let result = this.products;

    if (this.selectedCategory !== this.translate.instant('SALES.LOAD_ALL_CATEGORIES')) {
      result = result;
    }

    const term = this.productSearch.trim().toLowerCase();
    if (term) {
      result = result.filter(p =>
        p.name.toLowerCase().includes(term) ||
        p.sku.toLowerCase().includes(term)
      );
    }

    return result;
  }

  addToCart(product: PosProduct): void {
    const existing = this.cart.find(item => item.id === product.id);

    if (existing) {
      if (existing.quantity >= Number(product.quantity_available)) {
        return;
      }
      existing.quantity += 1;
      return;
    }

    if (Number(product.quantity_available) <= (existing?.quantity || 0)) {
      return;
    }
    this.cart.push({ id: product.id, name: product.name, price: Number(product.price), quantity: 1, taxRate: Number(product.tax_rate), availableQuantity: Number(product.quantity_available) });
  }

  updateQuantity(item: CartItem, amount: number): void {
    const next = item.quantity + amount;
    if (next <= 0) {
      this.cart = this.cart.filter(cartItem => cartItem.id !== item.id);
      return;
    }

    if (next > item.availableQuantity) {
      this.checkoutError = this.translate.instant('SALES.STOCK_EXCEEDED', { name: item.name, available: item.availableQuantity });
      return;
    }

    item.quantity = next;
  }

  get subtotal(): number {
    return this.cart.reduce((sum, item) => sum + this.roundMoney(item.price * item.quantity), 0);
  }

  get tax(): number {
    return this.cart.reduce((sum, item) => sum + this.roundMoney(item.price * item.quantity * item.taxRate / 100), 0);
  }

  get total(): number {
    return this.roundMoney(this.subtotal + this.tax);
  }

  checkout(): void {
    if (this.checkoutLoading) {
      return;
    }
    this.checkoutError = '';
    const amount = this.paymentAmount === null ? this.total : Number(this.paymentAmount);
    if (!this.currentShift || !this.cart.length || (amount > 0 && !this.paymentMethodId)) {
      this.checkoutError = this.translate.instant('SALES.OPEN_SHIFT_FIRST');
      return;
    }
    if (amount < 0 || amount > this.total) {
      this.checkoutError = this.translate.instant('SALES.INVALID_AMOUNT');
      return;
    }
    if (amount < this.total && !this.customerId) {
      this.checkoutError = this.translate.instant('SALES.CUSTOMER_REQUIRED');
      return;
    }

    const selectedMethod = this.paymentMethods.find(m => m.id === this.paymentMethodId);
    if (selectedMethod && (selectedMethod.category === 'card' || selectedMethod.code === 'CARD')) {
      this.openStripeModal();
      return;
    }

    this.executeCashCheckout(amount);
  }

  private executeCashCheckout(amount: number, referenceNumber?: string): void {
    this.checkoutLoading = true;
    const displayCurrency = this.currencyService.getCurrentCurrency();
    const exchangeRate = this.currencyService.getExchangeRate(displayCurrency);
    const rateProvider = displayCurrency !== 'USD' ? 'fxfeed' : null;
    
    this.shiftService.checkout(
      this.currentShift!.id,
      this.cart,
      this.paymentMethodId,
      amount,
      this.customerId || undefined,
      referenceNumber,
      displayCurrency,
      exchangeRate,
      rateProvider,
    ).subscribe({
      next: () => {
        this.cart = [];
        this.paymentAmount = null;
        this.customerId = '';
        this.checkoutLoading = false;
        this.feedback.success(this.translate.instant('SALES.SALE_SUCCESS'));
        this.loadProducts(1);
      },
      error: error => {
        this.checkoutLoading = false;
        this.checkoutError = this.feedback.error(error, this.translate.instant('SALES.SALE_FAILED'));
      },
    });
  }

  private async openStripeModal(): Promise<void> {
    this.stripeError = '';
    this.stripeLoading = false;
    this.cardNumberComplete = false;
    this.cardExpiryComplete = false;
    this.cardCvcComplete = false;
    this.showStripeModal = true;

    const stripe = await this.stripeService.getStripe();
    if (!stripe) {
      this.stripeError = this.translate.instant('SALES.STRIPE_LOAD_FAILED');
      return;
    }

    const elementStyle = {
      base: {
        fontSize: '16px',
        color: '#023E8A',
        fontFamily: '"IBM Plex Sans Arabic", sans-serif',
        '::placeholder': { color: '#8898aa' },
      },
      invalid: { color: '#e74c3c' },
    };

    const elements = stripe.elements();

    this.cardNumberElement = elements.create('cardNumber', { style: elementStyle });
    this.cardExpiryElement = elements.create('cardExpiry', { style: elementStyle });
    this.cardCvcElement = elements.create('cardCvc', { style: elementStyle });

    setTimeout(() => {
      const numEl = document.getElementById('stripe-card-number');
      const expEl = document.getElementById('stripe-card-expiry');
      const cvcEl = document.getElementById('stripe-card-cvc');

      if (numEl && this.cardNumberElement) {
        this.cardNumberElement.mount('#stripe-card-number');
        this.cardNumberElement.on('change', (event) => {
          this.ngZone.run(() => { this.cardNumberComplete = event.complete; });
        });
      }
      if (expEl && this.cardExpiryElement) {
        this.cardExpiryElement.mount('#stripe-card-expiry');
        this.cardExpiryElement.on('change', (event) => {
          this.ngZone.run(() => { this.cardExpiryComplete = event.complete; });
        });
      }
      if (cvcEl && this.cardCvcElement) {
        this.cardCvcElement.mount('#stripe-card-cvc');
        this.cardCvcElement.on('change', (event) => {
          this.ngZone.run(() => { this.cardCvcComplete = event.complete; });
        });
      }
    });
  }

  async confirmStripePayment(): Promise<void> {
    if (!this.cardNumberElement || this.stripeLoading) {
      return;
    }

    this.stripeLoading = true;
    this.stripeError = '';

    const amount = this.paymentAmount === null ? this.total : Number(this.paymentAmount);

    this.stripeService.createPaymentIntent(amount).subscribe({
      next: async (response) => {
        const result = await this.stripeService.confirmCardPayment(response.client_secret, this.cardNumberElement!);

        this.ngZone.run(() => {
          if (result.error) {
            this.stripeLoading = false;
            this.stripeError = result.error.message;
          } else {
            this.showStripeModal = false;
            this.stripeLoading = false;
            this.executeCashCheckout(amount, response.payment_intent_id);
          }
        });
      },
      error: (error) => {
        this.ngZone.run(() => {
          this.stripeLoading = false;
          this.stripeError = error?.error?.message ?? this.translate.instant('SALES.STRIPE_FAILED');
        });
      },
    });
  }

  closeStripeModal(): void {
    if (this.cardNumberElement) {
      this.cardNumberElement.destroy();
      this.cardNumberElement = null;
    }
    if (this.cardExpiryElement) {
      this.cardExpiryElement.destroy();
      this.cardExpiryElement = null;
    }
    if (this.cardCvcElement) {
      this.cardCvcElement.destroy();
      this.cardCvcElement = null;
    }
    this.showStripeModal = false;
    this.stripeError = '';
    this.stripeLoading = false;
  }

  get cardReady(): boolean {
    return this.cardNumberComplete && this.cardExpiryComplete && this.cardCvcComplete;
  }

  get selectedRegister(): Register | undefined {
    return this.registers.find(register => register.id === this.selectedRegisterId);
  }

  private fetchLastClosingCash(registerId: string): void {
    const request = this.openingRequest;
    this.shiftService.lastClosed(registerId).subscribe({
      next: response => {
        if (request !== this.openingRequest || registerId !== this.selectedRegisterId) return;
        if (response.data) {
          this.lastClosingCash = response.data.closing_cash;
          if (this.openingCash === 0) this.openingCash = Number(response.data.closing_cash);
        } else {
          this.lastClosingCash = null;
        }
      },
      error: () => {
        if (request !== this.openingRequest || registerId !== this.selectedRegisterId) return;
        this.lastClosingCash = null;
      },
    });
  }

  private roundMoney(value: number): number {
    return Math.round((value + Number.EPSILON) * 100) / 100;
  }

  private restoreCurrentShift(): Shift | null {
    const storedShift = localStorage.getItem('pos_current_shift');
    if (!storedShift) return null;
    try {
      const parsed: unknown = JSON.parse(storedShift);
      return typeof parsed === 'object' && parsed !== null && 'id' in parsed && 'status' in parsed ? parsed as Shift : null;
    } catch (error) {
      if (error instanceof SyntaxError) localStorage.removeItem('pos_current_shift');
      return null;
    }
  }
}
