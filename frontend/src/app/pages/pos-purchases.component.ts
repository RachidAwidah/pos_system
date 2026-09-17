import { Component, ViewChild, TemplateRef, EventEmitter, Output, ChangeDetectorRef } from '@angular/core';
import { NbDialogService } from '@nebular/theme';
import { Product, ProductService } from '../services/product.service';
import { Supplier, SupplierService } from '../services/supplier.service';
import { PurchaseOrder, PurchaseOrderPayload, PurchaseOrderService } from '../services/purchase-order.service';
import { downloadExcel } from '../shared/export.util';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { DetailsDialogComponent } from '../@theme/components/details-modal/details-dialog.component';
import { TranslateService } from '@ngx-translate/core';
import { CurrencyService } from '../services/currency.service';

@Component({
  selector: 'ngx-pos-purchases',
  styleUrls: ['./pos-purchases.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header"><div><span class="eyebrow">{{ translate.instant('PURCHASES.TITLE') }}</span><h1>{{ translate.instant('PURCHASES.SUBTITLE') }}</h1></div><div><button nbButton status="basic" (click)="exportPurchases()">{{ translate.instant('COMMON.EXPORT_EXCEL') }}</button> <button nbButton status="primary" (click)="showForm = !showForm">{{ translate.instant('PURCHASES.CREATE_TITLE') }}</button></div></div>
<nb-card *ngIf="showForm"><nb-card-header>{{ translate.instant('PURCHASES.CREATE_TITLE') }}</nb-card-header><nb-card-body>
        <div class="row">
          <div class="col-md-4">
            <label>{{ translate.instant('PURCHASES.FORM_SUPPLIER') }}</label>
            <nb-select [(ngModel)]="form.supplier_id" status="basic">
              <nb-option *ngFor="let supplier of suppliers" [value]="supplier.id">
                {{ supplier.name }}
              </nb-option>
              <option value="">{{ translate.instant('PURCHASES.FORM_SUPPLIER_SELECT') }}</option>
            </nb-select>
          </div>
          <div class="col-md-4">
            <label>{{ translate.instant('PURCHASES.FORM_WAREHOUSE') }}</label>
            <nb-select [(ngModel)]="form.warehouse_id" status="basic">
              <nb-option *ngFor="let warehouse of warehouses" [value]="warehouse.id">
                {{ warehouse.name }}
              </nb-option>
              <option value="">{{ translate.instant('PURCHASES.FORM_WAREHOUSE_SELECT') }}</option>
            </nb-select>
          </div>
          <div class="col-md-4">
            <label>{{ translate.instant('PURCHASES.FORM_DATE') }}</label>
            <input nbInput type="date" [(ngModel)]="form.expected_at">
          </div>
        </div>
        <div class="row">
          <div class="col-md-4">
            <label>{{ translate.instant('PURCHASES.FORM_PRODUCT') }}</label>
            <nb-select [(ngModel)]="newItemProductId" status="basic" [filter]="true" [placeholder]="translate.instant('PURCHASES.FORM_PRODUCT_SELECT')">
              <nb-option *ngFor="let product of products" [value]="product.id">
                {{ product.product_name }} ({{ product.sku }})
              </nb-option>
            </nb-select>
          </div>
          <div class="col-md-2">
            <label>{{ translate.instant('PURCHASES.FORM_QTY') }}</label>
            <input nbInput type="text" inputmode="decimal" [(ngModel)]="newItemQuantity" (keypress)="allowOnlyNumbers($event)" placeholder="0">
          </div>
          <div class="col-md-2">
            <label>{{ translate.instant('PURCHASES.FORM_PRICE') }}</label>
            <input nbInput type="text" inputmode="decimal" [(ngModel)]="newItemPrice" (keypress)="allowOnlyNumbers($event, true)" placeholder="0.00">
          </div>
          <div class="col-md-2">
            <label>&nbsp;</label>
            <button nbButton status="primary" (click)="addItem()" class="full-width">{{ translate.instant('PURCHASES.ADD_ITEM') }}</button>
          </div>
        </div>
        <table class="items-table" *ngIf="form.items.length">
          <thead>
            <tr>
              <th>{{ translate.instant('PURCHASES.COL_PRODUCT') }}</th>
              <th>{{ translate.instant('PURCHASES.COL_QTY') }}</th>
              <th>{{ translate.instant('PURCHASES.COL_PRICE') }}</th>
              <th>{{ translate.instant('PURCHASES.COL_TOTAL') }}</th>
              <th>{{ translate.instant('PURCHASES.COL_ACTION') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let item of form.items; let i = index">
              <td>{{ getProductName(item.product_id) }}</td>
              <td>{{ item.quantity }}</td>
              <td>{{ item.unit_cost | appCurrency }}</td>
              <td>{{ (item.quantity * item.unit_cost) | appCurrency }}</td>
              <td><button nbButton size="small" status="danger" (click)="removeItem(i)">{{ translate.instant('PURCHASES.DELETE_ITEM') }}</button></td>
            </tr>
          </tbody>
        </table>
        <div *ngIf="!form.items.length" class="state">{{ translate.instant('PURCHASES.NO_ITEMS') }}</div>
        <div *ngIf="formError" class="state error">{{ formError }}</div>
        <div class="form-actions"><button nbButton status="primary" [disabled]="saving" (click)="createOrder()">{{ saving ? translate.instant('PURCHASES.SAVE_SENDING') : translate.instant('PURCHASES.SAVE_DRAFT') }}</button><button nbButton status="basic" (click)="showForm = false">{{ translate.instant('PURCHASES.CANCEL') }}</button></div>
      </nb-card-body></nb-card>
      <nb-card><nb-card-body><div *ngIf="loading" class="state">{{ translate.instant('PURCHASES.LOADING') }}</div><div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
        <table *ngIf="!loading && !errorMessage" class="data-table"><thead><tr><th>{{ translate.instant('PURCHASES.LIST_COL_ORDER') }}</th><th>{{ translate.instant('PURCHASES.LIST_COL_SUPPLIER') }}</th><th>{{ translate.instant('PURCHASES.LIST_COL_WAREHOUSE') }}</th><th>{{ translate.instant('PURCHASES.LIST_COL_STATUS') }}</th><th>{{ translate.instant('PURCHASES.COL_TOTAL') }}</th><th>{{ translate.instant('PURCHASES.COL_ACTION') }}</th></tr></thead><tbody><tr *ngFor="let order of orders"><td>{{ order.purchase_order_number }}</td><td>{{ order.supplier?.name || '—' }}</td><td>{{ order.warehouse?.name || '—' }}</td><td>{{ statusLabel(order.status) }}</td><td>{{ order.total_amount | appCurrency }}</td><td><button nbButton size="tiny" status="basic" [disabled]="detailsLoading" (click)="view(order.id)">{{ translate.instant('PURCHASES.LIST_DETAILS') }}</button><button nbButton size="tiny" status="success" *ngIf="order.status === 'draft'" [disabled]="processingOrderId === order.id" (click)="send(order)">{{ processingOrderId === order.id ? translate.instant('PURCHASES.LIST_SENDING') : translate.instant('PURCHASES.LIST_SEND') }}</button><button nbButton size="tiny" status="danger" *ngIf="order.status === 'draft' || order.status === 'sent'" [disabled]="processingOrderId === order.id" (click)="cancel(order)">{{ translate.instant('PURCHASES.CANCEL') }}</button></td></tr></tbody></table>
        <div *ngIf="!loading && !errorMessage && !orders.length" class="state">{{ translate.instant('PURCHASES.LIST_EMPTY') }}</div>        <div *ngIf="totalPages > 1" class="pagination"><select nbInput [(ngModel)]="perPage" (change)="load(1)"><option [ngValue]="10">10</option><option [ngValue]="20">20</option><option [ngValue]="50">50</option></select><button nbButton size="small" status="basic" [disabled]="page === 1" (click)="load(page - 1)">{{ translate.instant('COMMON.PREVIOUS') }}</button><button *ngFor="let pageNumber of pageNumbers" nbButton size="small" [status]="pageNumber === page ? 'primary' : 'basic'" (click)="load(pageNumber)">{{ pageNumber }}</button><span>{{ translate.instant('COMMON.PAGE_INFO', { page: page, totalPages: totalPages }) }} · {{ total }} طلب</span><button nbButton size="small" status="basic" [disabled]="page === totalPages" (click)="load(page + 1)">{{ translate.instant('COMMON.NEXT') }}</button></div>
      </nb-card-body></nb-card>
    </div>
    <ng-template #purchaseDetailsTemplate>
      <div class="table-wrap" *ngIf="selected">
        <table class="data-table"><thead><tr><th>{{ translate.instant('PURCHASES.COL_PRODUCT') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_COL_ORDERED') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_COL_RECEIVED') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_COL_REMAINING') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_COL_COST') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_COL_RECEIVE') }}</th></tr></thead><tbody><tr *ngFor="let item of selected.items"><td>{{ item.product_name }}</td><td>{{ item.ordered_quantity }}</td><td>{{ item.received_quantity || 0 }}</td><td>{{ outstanding(item) }}</td><td>{{ item.unit_cost | appCurrency }}</td><td><input nbInput type="number" min="0" step="0.001" [max]="outstanding(item)" [disabled]="!canReceive" [(ngModel)]="receiptQuantities[item.id]" [placeholder]="translate.instant('PURCHASES.RECEIVE_COL_QTY')"></td></tr></tbody></table>
      </div>
      <div class="form-grid receipt-fields" *ngIf="canReceive"><label>{{ translate.instant('PURCHASES.RECEIVE_REF') }}<input nbInput [(ngModel)]="receiptForm.supplier_reference" [disabled]="receiving"></label><label class="dialog-field-wide">{{ translate.instant('PURCHASES.RECEIVE_NOTES') }}<textarea nbInput rows="2" [(ngModel)]="receiptForm.notes" [disabled]="receiving"></textarea></label></div>
      <div *ngIf="formError" class="state error">{{ formError }}</div>
      <button *ngIf="canReceive" nbButton status="success" [disabled]="receiving" (click)="receive()">{{ receiving ? translate.instant('PURCHASES.RECEIVE_PROCESSING') : translate.instant('PURCHASES.RECEIVE_BTN') }}</button>
      <div *ngIf="!canReceive" class="state">{{ translate.instant('PURCHASES.RECEIVE_HINT') }}</div>
      <h3 *ngIf="selected?.goods_receipts?.length">{{ translate.instant('PURCHASES.RECEIVE_HISTORY') }}</h3>
      <div class="table-wrap" *ngIf="selected?.goods_receipts?.length"><table class="data-table"><thead><tr><th>{{ translate.instant('PURCHASES.RECEIVE_HISTORY_COL_SVID') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_HISTORY_COL_DATE') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_HISTORY_COL_TOTAL') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_HISTORY_COL_REF') }}</th><th>{{ translate.instant('PURCHASES.RECEIVE_HISTORY_COL_NOTES') }}</th></tr></thead><tbody><tr *ngFor="let receipt of selected.goods_receipts"><td>{{ receipt.receipt_number }}</td><td>{{ receipt.received_at | date:'medium' }}</td><td>{{ receipt.total_amount | number:'1.2-2' }}</td><td>{{ receipt.supplier_reference || '—' }}</td><td>{{ receipt.notes || '—' }}</td></tr></tbody></table></div>
    </ng-template>
  `,
})
export class PosPurchasesComponent {
  @ViewChild('purchaseDetailsTemplate') purchaseDetailsTemplate: TemplateRef<unknown>;

  orders: PurchaseOrder[] = []; suppliers: Supplier[] = []; products: Product[] = []; warehouses: Array<{ id: string; name: string }> = [];
  selected: PurchaseOrder | null = null; showForm = false; loading = false; saving = false; receiving = false; errorMessage = ''; formError = ''; page = 1; perPage = 20; totalPages = 1; total = 0;
  productPage = 1; productLastPage = 1;
  detailsLoading = false;
  processingOrderId: string | null = null;
  receiptQuantities: Record<string, number> = {};
  receiptForm = { supplier_reference: '', notes: '' };
  newItemProductId: string = '';
  newItemQuantity: number = 1;
  newItemPrice: number = 0;
  form: PurchaseOrderPayload = { supplier_id: '', warehouse_id: '', expected_at: '', items: [] };
  constructor(
    private readonly ordersService: PurchaseOrderService,
    private readonly supplierService: SupplierService,
    private readonly productService: ProductService,
    private readonly feedback: OperationFeedbackService,
    private readonly dialogService: NbDialogService,
    readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    this.currencyService.currentCurrency$.subscribe(() => this.cdr.detectChanges());
    this.load(); this.loadReferences(); }
  load(page = this.page): void { this.loading = true; this.errorMessage = ''; this.ordersService.list(page, this.perPage).subscribe({ next: r => { this.orders = r.data; this.page = r.meta.current_page; this.totalPages = r.meta.last_page; this.total = r.meta.total; this.loading = false; }, error: error => { this.loading = false; this.errorMessage = this.feedback.error(error, this.translate.instant('PURCHASES.LOAD_FAILED')); } }); }
  get pageNumbers(): number[] { return Array.from({ length: this.totalPages }, (_, index) => index + 1); }
  exportPurchases(): void { this.ordersService.listAll().subscribe({ next: response => { downloadExcel('purchases.csv', [this.translate.instant('PURCHASES.LIST_COL_ORDER'), this.translate.instant('PURCHASES.LIST_COL_SUPPLIER'), this.translate.instant('PURCHASES.LIST_COL_STATUS'), this.translate.instant('PURCHASES.COL_TOTAL'), this.translate.instant('PURCHASES.RECEIVE_HISTORY_COL_DATE')], response.data.map(order => [order.purchase_order_number, order.supplier?.name, this.statusLabel(order.status), order.total_amount, order.ordered_at])); this.feedback.success(this.translate.instant('PURCHASES.EXPORT_SUCCESS')); }, error: (err) => this.feedback.error(err, 'تعذر تحميل بيانات التصدير.') }); }
  loadReferences(): void { this.supplierService.list(1).subscribe({ next: r => this.suppliers = r.data, error: () => this.formError = this.translate.instant('PURCHASES.LOAD_SUPPLIERS_FAILED') }); this.productService.referenceData().subscribe({ next: r => this.warehouses = r.data.warehouses, error: () => this.formError = this.translate.instant('PURCHASES.LOAD_WAREHOUSES_FAILED') }); this.loadProducts(); }
  loadProducts(page = 1, append = false): void { this.productService.list({ per_page: 25, page }).subscribe({ next: r => { this.products = append ? [...this.products, ...r.data] : r.data; this.productPage = r.meta.current_page; this.productLastPage = r.meta.last_page; }, error: () => this.formError = this.translate.instant('PURCHASES.LOAD_PRODUCTS_FAILED') }); }
  addItem(): void {
    if (!this.newItemProductId || this.newItemQuantity <= 0 || this.newItemPrice < 0) {
      this.formError = this.translate.instant('PURCHASES.ITEM_VALIDATION');
      return;
    }
    this.form.items.push({
      product_id: this.newItemProductId,
      quantity: this.newItemQuantity,
      unit_cost: this.newItemPrice
    });
    this.newItemProductId = '';
    this.newItemQuantity = 1;
    this.newItemPrice = 0;
    this.formError = '';
  }
  removeItem(index: number): void { this.form.items.splice(index, 1); }
  createOrder(): void { if (this.saving) return; if (!this.form.supplier_id || !this.form.warehouse_id || this.form.items.some(i => !i.product_id || i.quantity <= 0 || i.unit_cost < 0)) { this.formError = this.translate.instant('PURCHASES.PO_VALIDATION'); return; } this.saving = true; this.formError = ''; this.ordersService.create(this.form).subscribe({ next: () => { this.saving = false; this.showForm = false; this.feedback.success(this.translate.instant('PURCHASES.PO_CREATED')); this.form = { supplier_id: '', warehouse_id: '', expected_at: '', items: [] }; this.newItemProductId = ''; this.newItemQuantity = 1; this.newItemPrice = 0; this.load(1); }, error: error => { this.saving = false; this.formError = this.feedback.error(error, this.translate.instant('PURCHASES.PO_CREATE_FAILED')); } }); }
  view(id: string): void {
    if (this.detailsLoading) return;
    this.detailsLoading = true;
    this.ordersService.show(id).subscribe({
      next: r => {
        this.selected = r.data;
        this.receiptQuantities = {};
        this.receiptForm = { supplier_reference: '', notes: '' };
        this.detailsLoading = false;
        this.dialogService.open(DetailsDialogComponent, {
          context: {
            title: this.translate.instant('PURCHASES.PO_DETAIL_TITLE', { number: this.selected.purchase_order_number, status: this.statusLabel(this.selected.status) }),
            contentTemplate: this.purchaseDetailsTemplate,
            showFooter: false,
          },
        });
      },
      error: error => { this.detailsLoading = false; this.errorMessage = this.feedback.error(error, this.translate.instant('PURCHASES.PO_LOAD_FAILED')); },
    });
  }
  send(order: PurchaseOrder): void { if (this.processingOrderId) return; this.processingOrderId = order.id; this.ordersService.send(order.id).subscribe({ next: response => { this.processingOrderId = null; this.feedback.success(this.translate.instant('PURCHASES.PO_SEND_SUCCESS', { number: order.purchase_order_number })); this.load(this.page); if (this.selected?.id === order.id) this.selected = response.data; }, error: error => { this.processingOrderId = null; this.errorMessage = this.feedback.error(error, this.translate.instant('PURCHASES.PO_SEND_FAILED')); } }); }
  cancel(order: PurchaseOrder): void { if (this.processingOrderId) return; const reason = prompt(this.translate.instant('PURCHASES.PO_CANCEL_REASON')); if (!reason?.trim()) return; this.processingOrderId = order.id; this.ordersService.cancel(order.id, reason.trim()).subscribe({ next: () => { this.processingOrderId = null; this.feedback.success(this.translate.instant('PURCHASES.PO_CANCEL_SUCCESS', { number: order.purchase_order_number })); if (this.selected?.id === order.id) this.selected = null; this.load(this.page); }, error: error => { this.processingOrderId = null; this.errorMessage = this.feedback.error(error, this.translate.instant('PURCHASES.PO_CANCEL_FAILED')); } }); }
  outstanding(item: NonNullable<PurchaseOrder['items']>[number]): number { return Number(item.outstanding_quantity ?? item.ordered_quantity) - (item.outstanding_quantity === undefined ? Number(item.received_quantity ?? 0) : 0); }
  get canReceive(): boolean { return !!this.selected && ['sent', 'partially_received'].includes(this.selected.status); }
  statusLabel(status: string): string { const keys: Record<string, string> = { draft: 'PURCHASES.STATUS_DRAFT', sent: 'PURCHASES.STATUS_SENT', partially_received: 'PURCHASES.STATUS_PARTIAL', received: 'PURCHASES.STATUS_RECEIVED', cancelled: 'PURCHASES.STATUS_CANCELLED' }; return this.translate.instant(keys[status] ?? status); }
  private allowOnlyNumbers(event: KeyboardEvent, isPrice: boolean = false): void {
    const input = event.target as HTMLInputElement;
    const charCode = event.key.charCodeAt(0);
    if (charCode === 46 && input.value.includes('.')) {
      event.preventDefault();
    }
    if (!/^[0-9]$/.test(event.key) && event.key !== 'Backspace' && event.key !== 'Delete') {
      event.preventDefault();
    }
  }
  getProductName(productId: string): string {
    const product = this.products.find(p => p.id === productId);
    return product ? `${product.product_name} (${product.sku})` : '—';
  }
  receive(): void {
    if (this.receiving || !this.selected?.items?.length || !this.canReceive) return;
    const hasInvalidQuantity = this.selected.items.some(item => Number(this.receiptQuantities[item.id] || 0) > this.outstanding(item));
    if (hasInvalidQuantity) { this.formError = this.translate.instant('PURCHASES.RECEIVE_QTY_EXCEEDED'); return; }
    const items = this.selected.items.filter(item => Number(this.receiptQuantities[item.id] || 0) > 0).map(item => ({ purchase_order_item_id: item.id, quantity: Number(this.receiptQuantities[item.id]) }));
    if (!items.length) { this.formError = this.translate.instant('PURCHASES.RECEIVE_SELECT_ITEMS'); return; }
    this.receiving = true; this.formError = '';
    const selectedId = this.selected.id;
    this.ordersService.receive(selectedId, { items, ...(this.receiptForm.supplier_reference.trim() ? { supplier_reference: this.receiptForm.supplier_reference.trim() } : {}), ...(this.receiptForm.notes.trim() ? { notes: this.receiptForm.notes.trim() } : {}) }).subscribe({
      next: () => { this.receiving = false; this.feedback.success(this.translate.instant('PURCHASES.RECEIVE_SUCCESS')); this.load(this.page); this.view(selectedId); },
      error: error => { this.receiving = false; this.formError = this.feedback.error(error, this.translate.instant('PURCHASES.RECEIVE_FAILED')); },
    });
  }
}
