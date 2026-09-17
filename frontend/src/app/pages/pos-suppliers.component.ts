import { Component, ViewChild, TemplateRef, ChangeDetectorRef } from '@angular/core';
import { forkJoin } from 'rxjs';
import { NbDialogRef, NbDialogService } from '@nebular/theme';
import { TranslateService } from '@ngx-translate/core';
import { CurrencyService } from '../services/currency.service';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { PurchaseOrder, PurchaseOrderService } from '../services/purchase-order.service';
import { Supplier, SupplierLedgerEntry, SupplierPayment, SupplierService } from '../services/supplier.service';
import { PaymentMethod, ShiftService } from '../services/shift.service';
import { downloadExcel } from '../shared/export.util';
import { DetailsDialogComponent } from '../@theme/components/details-modal/details-dialog.component';

@Component({
  selector: 'ngx-pos-suppliers',
  styleUrls: ['./pos-suppliers.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header"><div><span class="eyebrow">{{ ('SUPPLIERS.TITLE' | translate) }}</span><h1>{{ ('SUPPLIERS.SUBTITLE' | translate) }}</h1></div><div><button nbButton status="basic" (click)="exportSuppliers()">{{ 'SUPPLIERS.EXPORT_BTN' | translate }}</button> <button nbButton status="primary" (click)="startCreate()">{{ 'SUPPLIERS.ADD_BTN' | translate }}</button></div></div>
      <nb-card><nb-card-body>
        <div class="form-grid">
          <label>{{ ('SUPPLIERS.SEARCH_PLACEHOLDER' | translate) }}<input nbInput type="search" maxlength="100" [(ngModel)]="search" [disabled]="loading" (keyup.enter)="applySearch()" placeholder="الاسم، الشركة، الهاتف، البريد أو الرقم الضريبي"></label>
          <div><button nbButton status="primary" [disabled]="loading" (click)="applySearch()">{{ ('COMMON.SEARCH' | translate) }}</button> <button nbButton status="basic" [disabled]="loading" (click)="search = ''; applySearch()">{{ ('SUPPLIERS.CLEAR_SEARCH' | translate) }}</button></div>
        </div>
        <div *ngIf="loading" class="state">{{ ('SUPPLIERS.LOADING' | translate) }}</div><div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
        <table *ngIf="!loading && !errorMessage" class="data-table"><thead><tr><th>{{ ('SUPPLIERS.COL_NAME' | translate) }}</th><th>{{ ('SUPPLIERS.COL_COMPANY' | translate) }}</th><th>{{ ('SUPPLIERS.COL_PHONE' | translate) }}</th><th>{{ ('SUPPLIERS.COL_TOTAL' | translate) }}</th><th>{{ ('SUPPLIERS.COL_ACTIONS' | translate) }}</th></tr></thead>
          <tbody><tr *ngFor="let supplier of suppliers"><td>{{ supplier.name }}</td><td>{{ supplier.company_name || '—' }}</td><td>{{ supplier.phone || '—' }}</td><td>{{ supplier.purchases_total | appCurrency }}</td>
            <td class="actions"><button nbButton size="tiny" status="info" (click)="openAccount(supplier)">{{ ('SUPPLIERS.BTN_LEDGER' | translate) }}</button><button nbButton size="tiny" status="basic" (click)="startEdit(supplier)">{{ ('SUPPLIERS.BTN_EDIT' | translate) }}</button><button nbButton size="tiny" status="success" (click)="startPayment(supplier)">{{ ('SUPPLIERS.BTN_PAYMENT' | translate) }}</button><button nbButton size="tiny" status="danger" [disabled]="deletingIds.has(supplier.id)" (click)="remove(supplier)">{{ deletingIds.has(supplier.id) ? ('SUPPLIERS.BTN_DELETING' | translate) : ('SUPPLIERS.BTN_DELETE' | translate) }}</button></td></tr></tbody>
        </table>
        <div *ngIf="!loading && !errorMessage && !suppliers.length" class="state">{{ ('SUPPLIERS.NO_SUPPLIERS' | translate) }}</div>
        <div *ngIf="!loading && !errorMessage && totalPages > 1" class="pagination"><select nbInput [(ngModel)]="perPage" (change)="load(1)"><option [ngValue]="10">10</option><option [ngValue]="25">25</option><option [ngValue]="50">50</option></select><button nbButton size="small" status="basic" [disabled]="page === 1" (click)="load(page - 1)">{{ ('COMMON.PREVIOUS' | translate) }}</button><button *ngFor="let pageNumber of pageNumbers" nbButton size="small" [status]="pageNumber === page ? 'primary' : 'basic'" (click)="load(pageNumber)">{{ pageNumber }}</button><span>{{ translate.instant('COMMON.PAGE_INFO', { page, totalPages }) }}</span><button nbButton size="small" status="basic" [disabled]="page === totalPages" (click)="load(page + 1)">{{ ('COMMON.NEXT' | translate) }}</button></div>
      </nb-card-body></nb-card>
    </div>

    <ng-template #editFormTemplate>
      <div class="form-grid">
        <label>{{ ('SUPPLIERS.FORM_NAME' | translate) }}<input nbInput [(ngModel)]="form.name"></label><label>{{ ('SUPPLIERS.FORM_COMPANY' | translate) }}<input nbInput [(ngModel)]="form.company_name"></label>
        <label>{{ ('SUPPLIERS.FORM_PHONE' | translate) }}<input nbInput [(ngModel)]="form.phone"></label><label>{{ ('SUPPLIERS.FORM_EMAIL' | translate) }}<input nbInput type="email" [(ngModel)]="form.email"></label>
        <label class="dialog-field-wide">{{ ('SUPPLIERS.FORM_ADDRESS' | translate) }}<input nbInput [(ngModel)]="form.address"></label><label>{{ ('SUPPLIERS.FORM_CREDIT_LIMIT' | translate) }}<input nbInput type="number" min="0" [(ngModel)]="form.payable_limit"></label>
        <label>{{ ('SUPPLIERS.FORM_TAX_ID' | translate) }}<input nbInput maxlength="100" [(ngModel)]="form.tax_number"></label>
      </div>
      <div *ngIf="formError" class="state error">{{ formError }}</div>
      <div class="form-actions">
        <button nbButton status="primary" [disabled]="saving" (click)="save()">{{ saving ? ('COMMON.SAVING' | translate) : ('COMMON.SAVE' | translate) }}</button>
        <button nbButton status="basic" [disabled]="saving" (click)="activeDialog?.close()">{{ 'COMMON.CANCEL' | translate }}</button>
      </div>
    </ng-template>

    <ng-template #paymentFormTemplate>
      <div class="balance-summary">{{ ('SUPPLIERS.FORM_BALANCE' | translate) }} <strong>{{ paymentSupplier?.balance | appCurrency }}</strong></div>
      <div class="form-grid">
        <label>{{ ('SUPPLIERS.PAYMENT_METHOD' | translate) }}<select nbInput [(ngModel)]="paymentForm.payment_method_id" [disabled]="paymentLoading"><option value="">{{ ('SUPPLIERS.PAYMENT_METHOD_SELECT' | translate) }}</option><option *ngFor="let method of paymentMethods" [value]="method.id">{{ method.name }}</option></select></label>
        <label>{{ ('SUPPLIERS.PAYMENT_AMOUNT' | translate) }}<input nbInput type="number" min="0.01" step="0.01" [(ngModel)]="paymentForm.amount" [disabled]="paymentLoading"></label>
        <label>{{ ('SUPPLIERS.PAYMENT_PO' | translate) }}<select nbInput [(ngModel)]="paymentForm.purchase_order_id" [disabled]="paymentLoading || purchaseOrdersLoading"><option value="">{{ ('SUPPLIERS.PAYMENT_GENERIC' | translate) }}</option><option *ngFor="let order of supplierPurchaseOrders" [value]="order.id">{{ order.purchase_order_number }} — {{ order.status }}</option></select></label>
        <label>{{ ('SUPPLIERS.PAYMENT_REF' | translate) }}<input nbInput [(ngModel)]="paymentForm.reference_number" [disabled]="paymentLoading"></label>
        <label class="dialog-field-wide">{{ ('SUPPLIERS.PAYMENT_NOTES' | translate) }}<textarea nbInput rows="2" [(ngModel)]="paymentForm.notes" [disabled]="paymentLoading"></textarea></label>
      </div>
      <div *ngIf="purchaseOrdersLoading" class="inline-state">{{ ('SUPPLIERS.PO_LOADING' | translate) }}</div>
      <div *ngIf="paymentError" class="state error">{{ paymentError }}</div>
      <div class="form-actions">
        <button nbButton status="primary" [disabled]="paymentLoading" (click)="savePayment()">{{ paymentLoading ? ('COMMON.SAVING' | translate) : ('COMMON.SAVE' | translate) }}</button>
        <button nbButton status="basic" [disabled]="paymentLoading" (click)="activeDialog?.close()">{{ 'COMMON.CANCEL' | translate }}</button>
      </div>
    </ng-template>

    <ng-template #accountTemplate>
      <div *ngIf="productsLoading" class="state">{{ ('SUPPLIERS.PRODUCTS_LOADING' | translate) }}</div>
      <div *ngIf="productsError" class="state error">{{ productsError }}</div>
      <ng-container *ngIf="!productsLoading && !productsError">
        <h4>{{ ('SUPPLIERS.PRODUCTS_TITLE' | translate) }}</h4>
        <div class="table-wrap" *ngIf="supplierProducts.length"><table class="data-table"><thead><tr><th>{{ ('SUPPLIERS.PRODUCTS_COL_NAME' | translate) }}</th><th>SKU</th><th>{{ ('SUPPLIERS.PRODUCTS_COL_SKU' | translate) }}</th><th>{{ ('SUPPLIERS.PRODUCTS_COL_COST' | translate) }}</th><th>{{ ('SUPPLIERS.PRODUCTS_COL_MIN' | translate) }}</th><th>{{ ('SUPPLIERS.PRODUCTS_COL_PREFERRED' | translate) }}</th></tr></thead><tbody><tr *ngFor="let link of supplierProducts"><td>{{ link.product?.product_name }}</td><td>{{ link.product?.sku }}</td><td>{{ link.supplier_sku || '—' }}</td><td>{{ link.last_cost ?? '—' }}</td><td>{{ link.minimum_order_quantity }}</td><td>{{ link.is_preferred ? ('SUPPLIERS.PRODUCTS_PREFERRED_YES' | translate) : ('SUPPLIERS.PRODUCTS_PREFERRED_NO' | translate) }}</td></tr></tbody></table></div>
        <div *ngIf="!supplierProducts.length" class="state">{{ ('SUPPLIERS.PRODUCTS_EMPTY' | translate) }}</div>
      </ng-container>
      <div *ngIf="accountLoading" class="state">{{ ('SUPPLIERS.LEDGER_LOADING' | translate) }}</div>
      <div *ngIf="accountError" class="state error">{{ accountError }}</div>
      <ng-container *ngIf="!accountLoading && !accountError">
        <h3>{{ ('SUPPLIERS.LEDGER_PAYMENTS_TITLE' | translate) }}</h3>
        <div class="table-wrap" *ngIf="payments.length"><table class="data-table account-table"><thead><tr><th>{{ ('SUPPLIERS.LEDGER_COL_DATE' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_AMOUNT' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_METHOD' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_PO' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_REF' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_NOTES' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_BALANCE' | translate) }}</th></tr></thead><tbody><tr *ngFor="let payment of payments"><td>{{ payment.paid_at | date:'medium' }}</td><td>{{ payment.amount | appCurrency }}</td><td>{{ payment.payment_method?.name || '—' }}</td><td>{{ payment.purchase_order?.purchase_order_number || ('SUPPLIERS.LEDGER_ON_ACCOUNT' | translate) }}</td><td>{{ payment.reference_number || '—' }}</td><td>{{ payment.notes || '—' }}</td><td>{{ paymentBalance(payment)?.balance_before ?? '—' }} / {{ paymentBalance(payment)?.balance_after ?? '—' }}</td></tr></tbody></table></div>
        <div *ngIf="!payments.length" class="state">{{ ('SUPPLIERS.LEDGER_PAYMENTS_EMPTY' | translate) }}</div>
        <h3>{{ ('SUPPLIERS.LEDGER_MOVEMENTS_TITLE' | translate) }}</h3>
        <div class="table-wrap" *ngIf="ledgerEntries.length"><table class="data-table account-table"><thead><tr><th>{{ ('SUPPLIERS.LEDGER_COL_DATE' | translate) }}</th><th>النوع</th><th>{{ ('SUPPLIERS.LEDGER_COL_CHANGE' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_BEFORE' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_AFTER' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_REF' | translate) }}</th><th>{{ ('SUPPLIERS.LEDGER_COL_DESCRIPTION' | translate) }}</th></tr></thead><tbody><tr *ngFor="let entry of ledgerEntries"><td>{{ entry.occurred_at | date:'medium' }}</td><td>{{ ledgerType(entry.entry_type) }}</td><td>{{ entry.amount_delta | number:'1.2-2' }}</td><td>{{ entry.balance_before | number:'1.2-2' }}</td><td>{{ entry.balance_after | number:'1.2-2' }}</td><td>{{ entry.purchase_order?.purchase_order_number || entry.goods_receipt?.receipt_number || '—' }}</td><td>{{ entry.description || '—' }}</td></tr></tbody></table></div>
        <div *ngIf="!ledgerEntries.length" class="state">{{ ('SUPPLIERS.LEDGER_EMPTY' | translate) }}</div>
      </ng-container>
    </ng-template>
  `,
})
export class PosSuppliersComponent {
  @ViewChild('editFormTemplate') editFormTemplate: TemplateRef<unknown>;
  @ViewChild('paymentFormTemplate') paymentFormTemplate: TemplateRef<unknown>;
  @ViewChild('accountTemplate') accountTemplate: TemplateRef<unknown>;

  supplierProducts: Array<{ id: string; supplier_sku: string | null; last_cost?: string; minimum_order_quantity: string; is_preferred: boolean; product: { product_name: string; sku: string } | null }> = [];
  productsLoading = false;
  productsError = '';
  productsPage = 1;
  productsLastPage = 1;

  loadSupplierProducts(page = 1): void {
    if (!this.accountSupplier) return;
    const id = this.accountSupplier.id;
    this.productsLoading = true;
    this.productsError = '';
    this.service.products(id, page).subscribe({
      next: response => { if (this.accountSupplier?.id !== id) return; this.supplierProducts = response.data; this.productsPage = response.meta.current_page; this.productsLastPage = response.meta.last_page; this.productsLoading = false; },
      error: error => { if (this.accountSupplier?.id !== id) return; this.productsLoading = false; this.productsError = this.feedback.error(error, this.translate.instant('SUPPLIERS.PRODUCTS_LOAD_FAILED')); },
    });
  }
  search = '';
  private appliedSearch = '';
  suppliers: Supplier[] = []; paymentMethods: PaymentMethod[] = []; paymentSupplier: Supplier | null = null; loading = false; errorMessage = ''; page = 1; perPage = 20; totalPages = 1; total = 0;
  showForm = false; saving = false; paymentLoading = false; formError = ''; paymentError = ''; editingId: string | null = null;
  supplierPurchaseOrders: PurchaseOrder[] = [];
  payments: SupplierPayment[] = [];
  ledgerEntries: SupplierLedgerEntry[] = [];
  accountSupplier: Supplier | null = null;
  accountLoading = false;
  accountError = '';
  purchaseOrdersLoading = false;
  deletingIds = new Set<string>();
  paymentForm = { payment_method_id: '', amount: 0, purchase_order_id: '', reference_number: '', notes: '' };
  form = this.emptyForm();
  private activeDialog: NbDialogRef<unknown> | null = null;
  constructor(
    private readonly service: SupplierService,
    private readonly shiftService: ShiftService,
    private readonly purchaseOrderService: PurchaseOrderService,
    private readonly feedback: OperationFeedbackService,
    private readonly dialogService: NbDialogService,
    readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    this.currencyService.currentCurrency$.subscribe(() => this.cdr.detectChanges());
    this.load();
    this.shiftService.referenceData().subscribe({
      next: response => {
        this.paymentMethods = response.data.payment_methods;
        this.paymentForm.payment_method_id = this.paymentMethods[0]?.id || '';
      },
      error: error => this.feedback.error(error, this.translate.instant('SUPPLIERS.PAYMENT_METHODS_FAILED')),
    });
  }
  private emptyForm() { return { name: '', company_name: '', email: '', phone: '', address: '', tax_number: '', payable_limit: 0 }; }
  load(page = this.page): void { this.loading = true; this.errorMessage = ''; this.service.list(page, this.perPage, this.appliedSearch).subscribe({ next: r => { this.suppliers = r.data; this.page = r.meta.current_page; this.totalPages = r.meta.last_page; this.total = r.meta.total; this.loading = false; }, error: error => { this.loading = false; this.errorMessage = this.feedback.error(error, this.translate.instant('SUPPLIERS.LOAD_FAILED')); } }); }
  applySearch(): void { if (this.loading) return; this.appliedSearch = this.search.trim(); this.load(1); }
  get pageNumbers(): number[] { return Array.from({ length: this.totalPages }, (_, index) => index + 1); }
  exportSuppliers(): void { this.service.listAll(this.appliedSearch).subscribe({ next: response => { downloadExcel('suppliers.csv', [this.translate.instant('SUPPLIERS.COL_NAME'), this.translate.instant('SUPPLIERS.COL_COMPANY'), this.translate.instant('SUPPLIERS.COL_PHONE'), this.translate.instant('SUPPLIERS.COL_TOTAL')], response.data.map(supplier => [supplier.name, supplier.company_name, supplier.phone, supplier.purchases_total])); this.feedback.success(this.translate.instant('SUPPLIERS.EXPORT_SUCCESS')); }, error: (err) => this.feedback.error(err, 'تعذر تحميل بيانات التصدير.') }); }
  startCreate(): void { this.editingId = null; this.form = this.emptyForm(); this.formError = ''; this.activeDialog = this.dialogService.open(DetailsDialogComponent, { context: { title: this.translate.instant('SUPPLIERS.ADD_TITLE'), contentTemplate: this.editFormTemplate, showFooter: false } }); }
  startEdit(supplier: Supplier): void {
    this.editingId = supplier.id;
    this.form = { name: supplier.name, company_name: supplier.company_name || '', email: supplier.email || '', phone: supplier.phone || '', address: supplier.address || '', tax_number: supplier.tax_number || '', payable_limit: Number(supplier.payable_limit || 0) };
    this.formError = '';
    this.activeDialog = this.dialogService.open(DetailsDialogComponent, { context: { title: this.translate.instant('SUPPLIERS.EDIT_TITLE', { name: supplier.name }), contentTemplate: this.editFormTemplate, showFooter: false } });
  }
  startPayment(supplier: Supplier): void {
    this.paymentSupplier = supplier;
    this.paymentError = '';
    this.paymentForm = { payment_method_id: this.paymentMethods[0]?.id || '', amount: 0, purchase_order_id: '', reference_number: '', notes: '' };
    this.purchaseOrdersLoading = true;
    this.purchaseOrderService.list(1, 100, { supplier_id: supplier.id }).subscribe({
      next: response => {
        this.supplierPurchaseOrders = response.data.filter(order => order.status !== 'cancelled');
        this.purchaseOrdersLoading = false;
        this.activeDialog = this.dialogService.open(DetailsDialogComponent, { context: { title: this.translate.instant('SUPPLIERS.PAYMENT_TITLE', { name: supplier.name }), contentTemplate: this.paymentFormTemplate, showFooter: false } });
      },
      error: error => {
        this.purchaseOrdersLoading = false;
        this.paymentError = this.feedback.error(error, 'تعذر تحميل طلبات الشراء الخاصة بالمورد.');
        this.activeDialog = this.dialogService.open(DetailsDialogComponent, { context: { title: this.translate.instant('SUPPLIERS.PAYMENT_TITLE', { name: supplier.name }), contentTemplate: this.paymentFormTemplate, showFooter: false } });
      },
    });
  }
  save(): void {
    if (this.saving) return;
    if (!this.form.name.trim()) { this.formError = this.translate.instant('SUPPLIERS.NAME_REQUIRED'); return; }
    const wasEditing = !!this.editingId;
    this.saving = true;
    this.formError = '';
    const request = this.editingId ? this.service.update(this.editingId, this.form) : this.service.create(this.form);
    request.subscribe({
      next: () => { this.saving = false; this.activeDialog?.close(); this.activeDialog = null; this.feedback.success(wasEditing ? this.translate.instant('SUPPLIERS.SAVE_SUCCESS') : this.translate.instant('SUPPLIERS.SAVE_SUCCESS_NEW')); this.load(1); },
      error: error => { this.saving = false; this.formError = this.feedback.error(error, this.translate.instant('SUPPLIERS.SAVE_FAILED')); },
    });
  }
  savePayment(): void {
    if (this.paymentLoading || !this.paymentSupplier) return;
    if (!this.paymentForm.payment_method_id || Number(this.paymentForm.amount) <= 0) { this.paymentError = this.translate.instant('SUPPLIERS.PAYMENT_VALIDATION'); return; }
    const supplier = this.paymentSupplier;
    const payload = {
      payment_method_id: this.paymentForm.payment_method_id,
      amount: Number(this.paymentForm.amount),
      ...(this.paymentForm.purchase_order_id ? { purchase_order_id: this.paymentForm.purchase_order_id } : {}),
      ...(this.paymentForm.reference_number.trim() ? { reference_number: this.paymentForm.reference_number.trim() } : {}),
      ...(this.paymentForm.notes.trim() ? { notes: this.paymentForm.notes.trim() } : {}),
    };
    this.paymentLoading = true;
    this.paymentError = '';
    this.service.pay(supplier.id, payload).subscribe({
      next: () => {
        this.paymentLoading = false;
        this.activeDialog?.close();
        this.activeDialog = null;
        this.feedback.success(this.translate.instant('SUPPLIERS.PAYMENT_SUCCESS', { name: supplier.name }));
        this.load(this.page);
        if (this.accountSupplier?.id === supplier.id) this.loadAccount();
      },
      error: error => { this.paymentLoading = false; this.paymentError = this.feedback.error(error, this.translate.instant('SUPPLIERS.PAYMENT_FAILED')); },
    });
  }
  openAccount(supplier: Supplier): void {
    this.accountSupplier = supplier;
    this.supplierProducts = [];
    this.loadAccount();
    this.loadSupplierProducts();
    this.dialogService.open(DetailsDialogComponent, {
      context: {
        title: this.translate.instant('SUPPLIERS.LEDGER_TITLE', { name: supplier.name }),
        contentTemplate: this.accountTemplate,
      },
    });
  }
  loadAccount(): void {
    if (!this.accountSupplier) return;
    this.accountLoading = true;
    this.accountError = '';
    forkJoin({ payments: this.service.payments(this.accountSupplier.id, 1, 100), ledger: this.service.ledger(this.accountSupplier.id, 1, 100) }).subscribe({
      next: response => { this.payments = response.payments.data; this.ledgerEntries = response.ledger.data; this.accountLoading = false; },
      error: error => { this.accountLoading = false; this.accountError = this.feedback.error(error, this.translate.instant('SUPPLIERS.LEDGER_LOAD_FAILED')); },
    });
  }
  paymentBalance(payment: SupplierPayment): SupplierLedgerEntry | undefined { return this.ledgerEntries.find(entry => entry.supplier_payment_id === payment.id); }
  ledgerType(type: string): string { const labels: Record<string, string> = { purchase: this.translate.instant('SUPPLIERS.LEDGER_TYPES_RECEIPT'), payment: this.translate.instant('SUPPLIERS.LEDGER_TYPES_PAYMENT'), adjustment: this.translate.instant('SUPPLIERS.LEDGER_TYPES_ADJUSTMENT') }; return labels[type] ?? type; }
  remove(supplier: Supplier): void {
    if (this.deletingIds.has(supplier.id) || !confirm(this.translate.instant('SUPPLIERS.DELETE_CONFIRM', { name: supplier.name }))) return;
    this.deletingIds.add(supplier.id);
    this.service.delete(supplier.id).subscribe({
      next: () => { this.deletingIds.delete(supplier.id); this.feedback.success(this.translate.instant('SUPPLIERS.DELETE_SUCCESS')); this.load(this.suppliers.length === 1 ? Math.max(1, this.page - 1) : this.page); },
      error: error => { this.deletingIds.delete(supplier.id); this.errorMessage = this.feedback.error(error, this.translate.instant('SUPPLIERS.DELETE_FAILED')); },
    });
  }
}
