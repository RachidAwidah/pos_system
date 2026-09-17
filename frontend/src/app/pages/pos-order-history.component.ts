import { Component, ViewChild, TemplateRef, ChangeDetectorRef, OnDestroy, OnInit } from '@angular/core';
import { NbDialogService } from '@nebular/theme';
import { TranslateService } from '@ngx-translate/core';
import { interval, Subscription } from 'rxjs';
import { map } from 'rxjs/operators';
import { Order, OrderService } from '../services/order.service';
import { PaymentMethod, ShiftService } from '../services/shift.service';
import { downloadExcel } from '../shared/export.util';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { DetailsDialogComponent } from '../@theme/components/details-modal/details-dialog.component';
import { CurrencyService } from '../services/currency.service';

@Component({
  selector: 'ngx-pos-order-history',
  styleUrls: ['./pos-order-history.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header">
        <div><span class="eyebrow">{{ t('ORDERS.TITLE') }}</span><h1>{{ t('ORDERS.SUBTITLE') }}</h1></div>
      </div>
      <button nbButton status="basic" (click)="exportOrders()">{{ t('COMMON.EXPORT_EXCEL') }}</button>
      <nb-card>
        <nb-card-body>
          <div *ngIf="loading" class="state">{{ t('ORDERS.LOADING') }}</div>
          <div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
          <table *ngIf="!loading && !errorMessage" class="data-table">
            <thead><tr><th>{{ t('ORDERS.COL_NUMBER') }}</th><th>{{ t('ORDERS.COL_DATE') }}</th><th>{{ t('ORDERS.COL_CUSTOMER') }}</th><th>{{ t('ORDERS.COL_STATUS') }}</th><th>{{ t('ORDERS.COL_TOTAL') }}</th><th>{{ t('ORDERS.COL_ACTION') }}</th></tr></thead>
            <tbody><tr *ngFor="let order of orders">
              <td>{{ order.invoice_number }}</td><td>{{ order.order_date | date:'short' }}</td>
              <td>{{ order.customer?.name || t('ORDERS.CASH_CUSTOMER') }}</td><td>{{ order.status }}</td>
              <td>{{ order.final_amount | appCurrency }}</td>
              <td><button nbButton size="tiny" status="basic" (click)="viewOrder(order.id)">{{ t('ORDERS.DETAILS') }}</button></td>
            </tr></tbody>
          </table>
          <div *ngIf="!loading && !errorMessage && !orders.length" class="state">{{ t('ORDERS.NO_ORDERS') }}</div>
          <div *ngIf="!loading && !errorMessage && totalPages > 1" class="pagination">
            <select nbInput [(ngModel)]="perPage" (change)="loadOrders(1)"><option [ngValue]="10">10</option><option [ngValue]="25">25</option><option [ngValue]="50">50</option></select>
            <button nbButton size="small" status="basic" [disabled]="page === 1" (click)="loadOrders(page - 1)">{{ t('COMMON.PREVIOUS') }}</button>
            <button *ngFor="let pageNumber of pageNumbers" nbButton size="small" [status]="pageNumber === page ? 'primary' : 'basic'" (click)="loadOrders(pageNumber)">{{ pageNumber }}</button>
            <span>{{ t('COMMON.PAGE_INFO', { page: page, totalPages: totalPages }) }} · {{ totalOrders }} {{ t('ORDERS.TITLE') }}</span>
            <button nbButton size="small" status="basic" [disabled]="page === totalPages" (click)="loadOrders(page + 1)">{{ t('COMMON.NEXT') }}</button>
          </div>
        </nb-card-body>
      </nb-card>
    </div>
    <ng-template #orderDetailsTemplate>
      <div class="detail-meta" *ngIf="selectedOrder">
        <span>{{ t('ORDERS.INVOICE_DATE') }} {{ selectedOrder.order_date | date:'medium' }}</span>
        <span>{{ t('ORDERS.INVOICE_CUSTOMER') }} {{ selectedOrder.customer?.name || t('ORDERS.CASH_CUSTOMER') }}</span>
        <span>{{ t('ORDERS.INVOICE_STATUS') }} {{ selectedOrder.status }}</span>
      </div>
      <div class="table-wrap"><table class="data-table" *ngIf="selectedOrder?.items?.length">
        <thead><tr><th>{{ t('ORDERS.COL_PRODUCT') }}</th><th>{{ t('ORDERS.COL_QTY') }}</th><th>{{ t('ORDERS.COL_UNIT_PRICE') }}</th><th>{{ t('ORDERS.COL_TOTAL') }}</th></tr></thead>
        <tbody><tr *ngFor="let item of selectedOrder.items">
          <td>{{ item.product_name || item.product?.product_name || t('ORDERS.PRODUCT_PLACEHOLDER') }}</td>
          <td>{{ item.quantity }}</td><td>{{ item.unit_price | appCurrency }}</td><td>{{ item.total_amount ? (item.total_amount | appCurrency) : '—' }}</td>
        </tr></tbody>
      </table></div>
      <div class="totals">
        <span>{{ t('ORDERS.SUBTOTAL') }} {{ selectedOrder?.subtotal_amount ? (selectedOrder.subtotal_amount | appCurrency) : '—' }}</span>
        <span>{{ t('ORDERS.TAX') }} {{ selectedOrder?.tax_amount ? (selectedOrder.tax_amount | appCurrency) : '—' }}</span>
        <strong>{{ t('ORDERS.COL_TOTAL') }} {{ selectedOrder?.final_amount | appCurrency }}</strong>
        <span>{{ t('ORDERS.PAID') }} {{ selectedOrder?.paid_amount ? (selectedOrder.paid_amount | appCurrency) : '—' }}</span>
        <span>{{ t('ORDERS.REMAINING') }} {{ selectedOrder?.due_amount ? (selectedOrder.due_amount | appCurrency) : '—' }}</span>
      </div>
      <div class="return-box">
        <h3>{{ t('ORDERS.REFUND_TITLE') }}</h3>
        <div *ngIf="!shiftId" class="cash-refund-alert">
          {{ t('ORDERS.OPEN_POS') }}
          <button nbButton size="tiny" status="info" (click)="syncShiftId()" style="margin-inline-start: 0.5rem;">{{ t('ORDERS.RECHECK_SHIFT') }}</button>
        </div>
        <div class="table-wrap"><table class="data-table" *ngIf="selectedOrder?.items?.length">
          <thead><tr><th>{{ t('ORDERS.COL_PRODUCT') }}</th><th>{{ t('ORDERS.REFUND_COL_SOLD') }}</th><th>{{ t('ORDERS.REFUND_COL_RETURNED') }}</th><th>{{ t('ORDERS.REFUND_COL_STOCK') }}</th></tr></thead>
          <tbody><tr *ngFor="let item of selectedOrder.items">
            <td>{{ item.product_name || item.product?.product_name || t('ORDERS.PRODUCT_PLACEHOLDER') }}</td><td>{{ item.quantity }}</td>
            <td><input nbInput type="number" min="0" [max]="item.quantity" [(ngModel)]="returnQuantities[item.id]" (ngModelChange)="onReturnQuantityChange()"></td>
            <td><input type="checkbox" [(ngModel)]="returnRestock[item.id]"></td>
          </tr></tbody>
        </table></div>
        <div class="return-fields">
          <input nbInput [placeholder]="t('ORDERS.REFUND_REASON')" [(ngModel)]="returnReason">
          <select nbInput [(ngModel)]="refundPaymentMethodId"><option value="">{{ t('ORDERS.REFUND_NO_REFUND') }}</option><option *ngFor="let method of paymentMethods" [value]="method.id">{{ method.name }}</option></select>
          <input nbInput type="number" min="0" step="0.01" [max]="maxCashRefund" [placeholder]="t('ORDERS.REFUND_AMOUNT')" [(ngModel)]="refundAmount">
        </div>
        <div *ngIf="totalReturnValue > 0" class="return-summary">
          <span>{{ t('ORDERS.RETURN_VALUE') }} {{ totalReturnValue | appCurrency }}</span>
          <span>{{ t('ORDERS.MAX_CASH_REFUND') }} {{ maxCashRefund | appCurrency }}</span>
          <span *ngIf="creditAmount > 0" class="credit-note">{{ t('ORDERS.CREDIT_NOTE', { amount: creditAmount | appCurrency }) }}</span>
        </div>
        <div *ngIf="returnError" class="state error">{{ returnError }}</div>
        <div *ngIf="hasCashRefund() && !shiftId" class="state error">{{ t('ORDERS.REFUND_CASH_NEEDS_SHIFT') }}</div>
        <button nbButton status="warning" [disabled]="returning || !canSubmitReturn()" (click)="submitReturn()">{{ returning ? t('ORDERS.REFUND_PROCESSING') : t('ORDERS.REFUND_BTN') }}</button>
        <div style="font-size: 11px; color: #999; margin-top: 6px;">DEBUG shiftId: {{ shiftId || '(empty)' }}</div>
      </div>
    </ng-template>
  `,
})
export class PosOrderHistoryComponent implements OnInit, OnDestroy {
  @ViewChild('orderDetailsTemplate') orderDetailsTemplate: TemplateRef<unknown>;

  orders: Order[] = [];
  selectedOrder: Order | null = null;
  loading = false;
  errorMessage = '';
  page = 1;
  perPage = 25;
  totalPages = 1;
  totalOrders = 0;
  paymentMethods: PaymentMethod[] = [];
  shiftId = '';
  returnQuantities: Record<string, number> = {};
  returnRestock: Record<string, boolean> = {};
  returnReason = '';
  refundPaymentMethodId = '';
  refundAmount = 0;
  returning = false;
  returnError = '';
  private shiftPollSub?: Subscription;

  constructor(
    private readonly orderService: OrderService,
    private readonly shiftService: ShiftService,
    private readonly feedback: OperationFeedbackService,
    private readonly dialogService: NbDialogService,
    private readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    this.syncShiftId();
  }

  ngOnInit(): void {
    this.loadOrders();
    this.shiftService.referenceData().pipe(map(r => r.data.payment_methods)).subscribe({
      next: methods => { this.paymentMethods = methods; this.cdr.markForCheck(); },
    });
    this.shiftPollSub = interval(1500).subscribe(() => this.syncShiftId());
  }

  ngOnDestroy(): void {
    this.shiftPollSub?.unsubscribe();
  }

  syncShiftId(): void {
    const stored = localStorage.getItem('pos_current_shift');
    if (!stored) {
      if (this.shiftId) { this.shiftId = ''; this.cdr.markForCheck(); }
      return;
    }
    try {
      const parsed: unknown = JSON.parse(stored);
      const id = typeof parsed === 'string' ? parsed : (parsed && typeof parsed === 'object' && 'id' in parsed && typeof parsed.id === 'string' ? parsed.id : '');
      if (id !== this.shiftId) { this.shiftId = id; this.cdr.markForCheck(); }
    } catch {
      if (stored !== this.shiftId) { this.shiftId = stored; this.cdr.markForCheck(); }
    }
  }

  canSubmitReturn(): boolean {
    if (this.returning) {
      return false;
    }
    if (!this.returnReason.trim()) {
      return false;
    }
    if (this.hasCashRefund() && !this.shiftId) {
      return false;
    }
    return true;
  }

  hasCashRefund(): boolean {
    return Number(this.refundAmount) > 0;
  }

  get totalReturnValue(): number {
    if (!this.selectedOrder?.items) return 0;
    let total = 0;
    for (const item of this.selectedOrder.items) {
      const returnQty = Number(this.returnQuantities[item.id] || 0);
      if (returnQty <= 0) continue;
      const originalQty = Number(item.quantity);
      if (originalQty <= 0) continue;
      const unitPrice = Number(item.unit_price || 0);
      const lineTotal = Number(item.total_amount || 0);
      const itemValue = lineTotal > 0
        ? (lineTotal * returnQty / originalQty)
        : (unitPrice * returnQty);
      total += isNaN(itemValue) ? 0 : itemValue;
    }
    return Math.round(total * 100) / 100;
  }

  get maxCashRefund(): number {
    return this.totalReturnValue;
  }

  get minCashRefund(): number {
    const dueAmount = Number(this.selectedOrder?.due_amount || 0);
    const maxCredit = Math.min(this.totalReturnValue, dueAmount);
    return Math.round((this.totalReturnValue - maxCredit) * 100) / 100;
  }

  get creditAmount(): number {
    const refundNum = Number(this.refundAmount || 0);
    return Math.round(Math.max(0, this.totalReturnValue - refundNum) * 100) / 100;
  }

  onReturnQuantityChange(): void {
    this.refundAmount = this.maxCashRefund;
  }

  t(key: string, params?: Record<string, unknown>): string {
    return params ? this.translate.instant(key, params) : this.translate.instant(key);
  }

  loadOrders(page = this.page): void {
    this.loading = true; this.errorMessage = '';
    this.orderService.list(page, this.perPage).subscribe({
      next: response => { this.orders = response.data; this.page = response.meta.current_page; this.totalPages = response.meta.last_page; this.totalOrders = response.meta.total; this.loading = false; },
      error: () => { this.loading = false; this.errorMessage = this.translate.instant('ORDERS.LOAD_FAILED'); },
    });
  }

  exportOrders(): void {
    this.orderService.listAll().subscribe({
      next: response => {
        downloadExcel('invoices.csv', [this.translate.instant('ORDERS.COL_NUMBER'), this.translate.instant('ORDERS.COL_DATE'), this.translate.instant('ORDERS.COL_STATUS'), this.translate.instant('ORDERS.COL_TOTAL'), this.translate.instant('ORDERS.PAID')], response.data.map(order => [
          order.invoice_number, order.order_date, order.status, order.final_amount, order.paid_amount,
        ]));
        this.feedback.success(this.translate.instant('ORDERS.EXPORT_SUCCESS'));
      },
      error: (err) => this.feedback.error(err, this.translate.instant('ORDERS.LOAD_FAILED')),
    });
  }

  viewOrder(id: string): void {
    this.orderService.show(id).subscribe({
      next: response => {
        this.selectedOrder = response.data;
        this.returnQuantities = {};
        this.returnRestock = {};
        this.returnReason = '';
        this.refundAmount = 0;
        this.returnError = '';
        this.dialogService.open(DetailsDialogComponent, {
          context: {
            title: this.translate.instant('ORDERS.INVOICE_TITLE', { number: this.selectedOrder.invoice_number }),
            contentTemplate: this.orderDetailsTemplate,
            showFooter: false,
          },
        });
      },
      error: () => this.errorMessage = this.translate.instant('ORDERS.INVOICE_LOAD_FAILED'),
    });
  }

  submitReturn(): void {
    if (this.returning) return;
    if (!this.selectedOrder || !this.returnReason.trim()) { this.returnError = this.translate.instant('ORDERS.REFUND_REASON_REQUIRED'); return; }
    const items = (this.selectedOrder.items || []).filter(item => Number(this.returnQuantities[item.id] || 0) > 0).map(item => ({ order_item_id: item.id, quantity: Number(this.returnQuantities[item.id]), restock: this.returnRestock[item.id] !== false }));
    if (!items.length) { this.returnError = this.translate.instant('ORDERS.REFUND_SELECT_ITEMS'); return; }
    if ((this.selectedOrder.items || []).some(item => Number(this.returnQuantities[item.id] || 0) > Number(item.quantity))) { this.returnError = this.translate.instant('ORDERS.REFUND_QTY_EXCEEDED'); return; }
    const refunds = Number(this.refundAmount) > 0 && this.refundPaymentMethodId ? [{ payment_method_id: this.refundPaymentMethodId, amount: Number(this.refundAmount) }] : [];
    if (refunds.length && !this.shiftId) { this.returnError = this.translate.instant('ORDERS.REFUND_CASH_NEEDS_SHIFT'); return; }
    this.returning = true; this.returnError = '';
    const payload: { shift_id?: string; reason: string; items: typeof items; refunds: typeof refunds } = { reason: this.returnReason.trim(), items, refunds };
    if (this.shiftId) {
      payload.shift_id = this.shiftId;
    }
    this.orderService.returnOrder(this.selectedOrder.id, payload).subscribe({
      next: () => { this.returning = false; this.selectedOrder = null; this.feedback.success(refunds.length ? this.translate.instant('ORDERS.REFUND_SUCCESS') : this.translate.instant('ORDERS.REFUND_SUCCESS_NO_REFUND')); this.loadOrders(this.page); },
      error: error => { this.returning = false; this.returnError = this.feedback.error(error, this.translate.instant('ORDERS.REFUND_FAILED')); },
    });
  }

  get pageNumbers(): number[] {
    return Array.from({ length: this.totalPages }, (_, index) => index + 1);
  }
}
