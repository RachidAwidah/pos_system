import { Component, ViewChild, TemplateRef, ChangeDetectorRef } from '@angular/core';
import { NbDialogRef, NbDialogService } from '@nebular/theme';
import { Customer, CustomerService, CustomerStatement } from '../services/customer.service';
import { Order, OrderService } from '../services/order.service';
import { PaymentMethod, ShiftService } from '../services/shift.service';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { downloadExcel } from '../shared/export.util';
import { DetailsDialogComponent } from '../@theme/components/details-modal/details-dialog.component';
import { TranslateService } from '@ngx-translate/core';
import { CurrencyService } from '../services/currency.service';

type CustomerWithTotal = Customer & { invoices_total: string | number };

@Component({
  selector: 'ngx-pos-customers',
  styleUrls: ['./pos-customers.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header">
        <div>
          <span class="eyebrow">{{ ('CUSTOMERS.TITLE' | translate) }}</span>
          <h1>{{ ('CUSTOMERS.SUBTITLE' | translate) }}</h1>
        </div>
        <div><button nbButton status="basic" (click)="exportCustomers()">{{ ('CUSTOMERS.EXPORT_BTN' | translate) }}</button> <button nbButton status="primary" (click)="startCreate()">{{ ('CUSTOMERS.ADD_BTN' | translate) }}</button></div>
      </div>

      <nb-card>
        <nb-card-body>
          <div class="form-grid">
            <label>{{ ('CUSTOMERS.SEARCH_PLACEHOLDER' | translate) }}<input nbInput type="search" maxlength="100" [(ngModel)]="search" [disabled]="loading" (keyup.enter)="applySearch()" [placeholder]="('CUSTOMERS.SEARCH_INPUT' | translate)"></label>
            <div><button nbButton status="primary" [disabled]="loading" (click)="applySearch()">{{ ('COMMON.SEARCH' | translate) }}</button> <button nbButton status="basic" [disabled]="loading" (click)="search = ''; applySearch()">{{ ('CUSTOMERS.CLEAR_SEARCH' | translate) }}</button></div>
          </div>
          <div *ngIf="loading" class="state">{{ ('CUSTOMERS.LOADING' | translate) }}</div>
          <div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
          <table *ngIf="!loading && !errorMessage" class="data-table">
            <thead>
              <tr>
                <th>{{ ('CUSTOMERS.COL_NAME' | translate) }}</th>
                <th>{{ ('CUSTOMERS.COL_PHONE' | translate) }}</th>
                <th>{{ ('CUSTOMERS.COL_LOYALTY' | translate) }}</th>
                <th>{{ ('CUSTOMERS.COL_BALANCE' | translate) }}</th>
                <th>{{ ('CUSTOMERS.COL_ACTIONS' | translate) }}</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let customer of customers">
                <td>{{ customer.name }}</td>
                <td>{{ customer.phone || '—' }}</td>
                <td>{{ customer.loyalty_points | number }}</td>
                <td>{{ customer.invoices_total | appCurrency }}</td>
                <td class="actions">
                  <button nbButton size="tiny" status="info" (click)="openAccount(customer)">{{ ('CUSTOMERS.BTN_LEDGER' | translate) }}</button>
                  <button nbButton size="tiny" status="basic" (click)="startEdit(customer)">{{ ('CUSTOMERS.BTN_EDIT' | translate) }}</button>
                  <button nbButton size="tiny" status="success" (click)="startPayment(customer)">{{ ('CUSTOMERS.BTN_PAYMENT' | translate) }}</button>
                  <button nbButton size="tiny" status="danger" [disabled]="deletingIds.has(customer.id)" (click)="deleteCustomer(customer)">{{ deletingIds.has(customer.id) ? ('CUSTOMERS.BTN_DELETING' | translate) : ('CUSTOMERS.BTN_DELETE' | translate) }}</button>
                </td>
              </tr>
            </tbody>
          </table>
          <div *ngIf="!loading && !errorMessage && !customers.length" class="state">{{ ('CUSTOMERS.NO_CUSTOMERS' | translate) }}</div>
          <div *ngIf="!loading && !errorMessage && totalPages > 1" class="pagination">
            <button nbButton size="small" status="basic" [disabled]="page === 1" (click)="loadCustomers(page - 1)">{{ ('COMMON.PREVIOUS' | translate) }}</button>
            <button *ngFor="let pageNumber of pageNumbers" nbButton size="small" [status]="pageNumber === page ? 'primary' : 'basic'" (click)="loadCustomers(pageNumber)">{{ pageNumber }}</button>
            <select nbInput [(ngModel)]="perPage" (change)="loadCustomers(1)"><option [ngValue]="10">10</option><option [ngValue]="25">25</option><option [ngValue]="50">50</option></select><span>{{ translate.instant('COMMON.PAGE_INFO', { page, totalPages }) }} · {{ totalCustomers }} {{ ('CUSTOMERS.COUNT_LABEL' | translate) }}</span>
            <button nbButton size="small" status="basic" [disabled]="page === totalPages" (click)="loadCustomers(page + 1)">{{ ('COMMON.NEXT' | translate) }}</button>
          </div>
        </nb-card-body>
      </nb-card>
    </div>

    <ng-template #editFormTemplate>
      <div class="form-grid">
        <label>{{ ('CUSTOMERS.FORM_NAME' | translate) }}<input nbInput [(ngModel)]="customerForm.name"></label>
        <label>{{ ('CUSTOMERS.FORM_COMPANY' | translate) }}<input nbInput [(ngModel)]="customerForm.company_name"></label>
        <label>{{ ('CUSTOMERS.FORM_PHONE' | translate) }}<input nbInput [(ngModel)]="customerForm.phone"></label>
        <label>{{ ('CUSTOMERS.FORM_EMAIL' | translate) }}<input nbInput type="email" [(ngModel)]="customerForm.email"></label>
        <label class="dialog-field-wide">{{ ('CUSTOMERS.FORM_ADDRESS' | translate) }}<input nbInput [(ngModel)]="customerForm.address"></label>
        <label>{{ ('CUSTOMERS.FORM_TAX_ID' | translate) }}<input nbInput maxlength="100" [(ngModel)]="customerForm.tax_number"></label>
        <label>{{ ('CUSTOMERS.FORM_CREDIT_LIMIT' | translate) }}<input nbInput type="number" min="0" step="0.01" [(ngModel)]="customerForm.credit_limit"></label>
      </div>
      <div *ngIf="formError" class="state error">{{ formError }}</div>
      <div class="form-actions">
        <button nbButton status="primary" [disabled]="formLoading" (click)="createCustomer()">{{ formLoading ? ('COMMON.SAVING' | translate) : ('COMMON.SAVE' | translate) }}</button>
        <button nbButton status="basic" [disabled]="formLoading" (click)="closeForm()">{{ 'COMMON.CANCEL' | translate }}</button>
      </div>
    </ng-template>

    <ng-template #paymentFormTemplate>
      <div class="balance-summary">{{ ('CUSTOMERS.FORM_BALANCE' | translate) }} <strong>{{ paymentCustomer?.balance | appCurrency }}</strong></div>
      <div class="form-grid">
        <label>{{ ('CUSTOMERS.PAYMENT_INVOICE' | translate) }}<select nbInput [(ngModel)]="paymentForm.order_id" [disabled]="paymentLoading"><option value="">{{ ('CUSTOMERS.PAYMENT_INVOICE_SELECT' | translate) }}</option><option *ngFor="let order of customerOrders" [value]="order.id">{{ order.invoice_number }} - {{ (order.due_amount || order.final_amount) | appCurrency }}</option></select></label>
        <label>{{ ('CUSTOMERS.PAYMENT_METHOD' | translate) }}<select nbInput [(ngModel)]="paymentForm.payment_method_id" [disabled]="paymentLoading"><option value="">{{ ('CUSTOMERS.PAYMENT_METHOD_SELECT' | translate) }}</option><option *ngFor="let method of paymentMethods" [value]="method.id">{{ method.name }}</option></select></label>
        <label>{{ ('CUSTOMERS.PAYMENT_AMOUNT' | translate) }}<input nbInput type="number" min="0.01" step="0.01" [(ngModel)]="paymentForm.amount" [disabled]="paymentLoading"></label>
        <label>{{ ('CUSTOMERS.PAYMENT_REF' | translate) }}<input nbInput [(ngModel)]="paymentForm.reference_number" [disabled]="paymentLoading"></label>
        <label class="dialog-field-wide">{{ ('CUSTOMERS.PAYMENT_NOTES' | translate) }}<textarea nbInput rows="2" [(ngModel)]="paymentForm.notes" [disabled]="paymentLoading"></textarea></label>
      </div>
      <div class="invoice-pagination" *ngIf="customerOrderLastPage > 1"><span>{{ translate.instant('COMMON.PAGE_INFO', { page: customerOrderPage, totalPages: customerOrderLastPage }) }}</span><button nbButton size="small" status="basic" [disabled]="customerOrderPage === customerOrderLastPage || customerOrdersLoading" (click)="loadCustomerOrders(customerOrderPage + 1, true)">{{ ('CUSTOMERS.INVOICES_LOAD_MORE' | translate) }}</button></div>
      <div *ngIf="customerOrdersLoading" class="inline-state">{{ ('CUSTOMERS.INVOICES_LOADING' | translate) }}</div><div *ngIf="!customerOrdersLoading && !customerOrders.length" class="inline-state">{{ ('CUSTOMERS.INVOICES_EMPTY' | translate) }}</div>
      <div *ngIf="paymentError" class="state error">{{ paymentError }}</div>
      <div class="form-actions">
        <button nbButton status="primary" [disabled]="paymentLoading" (click)="savePayment()">{{ paymentLoading ? ('COMMON.SAVING' | translate) : ('COMMON.SAVE' | translate) }}</button>
        <button nbButton status="basic" [disabled]="paymentLoading" (click)="closePayment()">{{ 'COMMON.CANCEL' | translate }}</button>
      </div>
    </ng-template>

    <ng-template #accountTemplate>
      <div *ngIf="accountLoading" class="state">{{ ('CUSTOMERS.LEDGER_LOADING' | translate) }}</div>
      <div *ngIf="accountError" class="state error">{{ accountError }}</div>
      <ng-container *ngIf="statement && !statementLoading && !statementError">
        <p>{{ ('CUSTOMERS.LEDGER_SUMMARY' | translate) }}: {{ statement.summary.invoices_total }} · {{ ('CUSTOMERS.LEDGER_PAID' | translate) }}: {{ statement.summary.paid_total }} · {{ ('CUSTOMERS.LEDGER_DUE' | translate) }}: {{ statement.summary.due_total }} · {{ ('CUSTOMERS.LEDGER_BALANCE' | translate) }}: {{ statement.summary.balance }}</p>
        <p>{{ ('CUSTOMERS.LEDGER_HINT' | translate) }}</p>
        <div class="table-wrap" *ngIf="statement.data.length"><table class="data-table"><thead><tr><th>{{ ('CUSTOMERS.LEDGER_COL_DATE' | translate) }}</th><th>{{ ('CUSTOMERS.LEDGER_COL_TYPE' | translate) }}</th><th>{{ ('CUSTOMERS.LEDGER_COL_REF' | translate) }}</th><th>{{ ('CUSTOMERS.LEDGER_COL_DEBIT' | translate) }}</th><th>{{ ('CUSTOMERS.LEDGER_COL_CREDIT' | translate) }}</th><th>{{ ('CUSTOMERS.LEDGER_COL_BALANCE' | translate) }}</th></tr></thead><tbody><tr *ngFor="let entry of statement.data"><td>{{ entry.occurred_at | date:'medium' }}</td><td>{{ statementType(entry.entry_type) }}</td><td>{{ entry.reference || '—' }}</td><td>{{ entry.debit }}</td><td>{{ entry.credit }}</td><td>{{ entry.balance_after }}</td></tr></tbody></table></div>
        <div *ngIf="!statement.data.length" class="state">{{ ('CUSTOMERS.LEDGER_EMPTY' | translate) }}</div>
        <div class="pagination"><button nbButton size="small" [disabled]="statement.meta.current_page <= 1" (click)="loadStatement(statement.meta.current_page - 1)">{{ ('COMMON.PREVIOUS' | translate) }}</button><span>{{ statement.meta.current_page }} / {{ statement.meta.last_page }}</span><button nbButton size="small" [disabled]="statement.meta.current_page >= statement.meta.last_page" (click)="loadStatement(statement.meta.current_page + 1)">{{ ('COMMON.NEXT' | translate) }}</button></div>
      </ng-container>
      <div *ngIf="accountLoading" class="state">{{ ('CUSTOMERS.LEDGER_LOADING' | translate) }}</div>
      <div *ngIf="!accountLoading && accountOrders.length">
        <table class="data-table account-table"><thead><tr><th>{{ ('CUSTOMERS.INVOICES_COL_INVOICE' | translate) }}</th><th>{{ ('CUSTOMERS.LEDGER_COL_DATE' | translate) }}</th><th>{{ ('CUSTOMERS.INVOICES_COL_TOTAL' | translate) }}</th><th>{{ ('CUSTOMERS.INVOICES_COL_PAID' | translate) }}</th><th>{{ ('CUSTOMERS.INVOICES_COL_REMAINING' | translate) }}</th><th>{{ ('CUSTOMERS.INVOICES_COL_STATUS' | translate) }}</th><th>{{ ('CUSTOMERS.INVOICES_COL_DETAILS' | translate) }}</th></tr></thead><tbody><tr *ngFor="let order of accountOrders"><td>{{ order.invoice_number }}</td><td>{{ order.order_date | date:'medium' }}</td><td>{{ order.final_amount | number:'1.2-2' }}</td><td>{{ order.paid_amount | number:'1.2-2' }}</td><td>{{ order.due_amount | number:'1.2-2' }}</td><td>{{ order.payment_status }}</td><td><button nbButton size="tiny" status="basic" [disabled]="accountDetailsLoading" (click)="loadPaymentDetails(order)">{{ ('CUSTOMERS.INVOICES_BTN' | translate) }}</button></td></tr></tbody></table>
      </div>
      <div *ngIf="!accountLoading && !accountOrders.length" class="state">{{ ('CUSTOMERS.INVOICES_EMPTY_MSG' | translate) }}</div>
      <div *ngIf="accountDetailsLoading" class="inline-state">{{ ('CUSTOMERS.PAYMENTS_LOADING' | translate) }}</div>
      <div *ngIf="selectedAccountOrder" class="payment-details"><h3>{{ ('CUSTOMERS.PAYMENTS_TITLE' | translate) }} {{ selectedAccountOrder.invoice_number }}</h3><div class="table-wrap" *ngIf="selectedAccountOrder.payments?.length"><table class="data-table account-table"><thead><tr><th>{{ ('CUSTOMERS.LEDGER_COL_DATE' | translate) }}</th><th>{{ ('CUSTOMERS.PAYMENT_AMOUNT' | translate) }}</th><th>{{ ('CUSTOMERS.PAYMENT_METHOD' | translate) }}</th><th>{{ ('CUSTOMERS.LEDGER_COL_REF' | translate) }}</th><th>{{ ('CUSTOMERS.PAYMENT_NOTES' | translate) }}</th></tr></thead><tbody><tr *ngFor="let payment of selectedAccountOrder.payments"><td>{{ payment.paid_at | date:'medium' }}</td><td>{{ payment.amount | number:'1.2-2' }}</td><td>{{ payment.payment_method?.name || '—' }}</td><td>{{ payment.reference_number || '—' }}</td><td>{{ payment.notes || '—' }}</td></tr></tbody></table></div><div *ngIf="!selectedAccountOrder.payments?.length" class="state">{{ ('CUSTOMERS.PAYMENTS_EMPTY' | translate) }}</div></div>
    </ng-template>
  `,
})
export class PosCustomersComponent {
  @ViewChild('editFormTemplate') editFormTemplate: TemplateRef<unknown>;
  @ViewChild('paymentFormTemplate') paymentFormTemplate: TemplateRef<unknown>;
  @ViewChild('accountTemplate') accountTemplate: TemplateRef<unknown>;

  statement: CustomerStatement | null = null;
  statementLoading = false;
  statementError = '';

  loadStatement(page = 1): void {
    if (!this.accountCustomer) return;
    const id = this.accountCustomer.id;
    this.statementLoading = true;
    this.statementError = '';
    this.customerService.statement(id, page).subscribe({
      next: value => { if (this.accountCustomer?.id !== id) return; this.statement = value; this.statementLoading = false; },
      error: error => { if (this.accountCustomer?.id !== id) return; this.statementLoading = false; this.statementError = this.feedback.error(error, this.translate.instant('CUSTOMERS.LEDGER_FAILED')); },
    });
  }

  statementType(type: string): string {
    return ({ sale: this.translate.instant('CUSTOMERS.LEDGER_TYPES_SALE'), customer_payment: this.translate.instant('CUSTOMERS.LEDGER_TYPES_PAYMENT'), sales_return: this.translate.instant('CUSTOMERS.LEDGER_TYPES_REFUND'), adjustment: this.translate.instant('CUSTOMERS.LEDGER_TYPES_ADJUSTMENT') } as Record<string, string>)[type] || type;
  }
  customers: CustomerWithTotal[] = [];
  loading = false;
  errorMessage = '';
  page = 1;
  search = '';
  private appliedSearch = '';
  totalPages = 1;
  totalCustomers = 0;
  perPage = 20;
  showCreateForm = false;
  formLoading = false;
  formError = '';
  editingCustomerId: string | null = null;
  customerForm = this.emptyCustomerForm();
  paymentMethods: PaymentMethod[] = [];
  customerOrders: Order[] = [];
  customerOrderPage = 1;
  customerOrderLastPage = 1;
  customerOrdersLoading = false;
  paymentCustomer: Customer | null = null;
  paymentLoading = false;
  paymentError = '';
  paymentForm = { order_id: '', payment_method_id: '', amount: 0, reference_number: '', notes: '' };
  accountCustomer: Customer | null = null;
  accountOrders: Order[] = [];
  selectedAccountOrder: Order | null = null;
  accountLoading = false;
  accountDetailsLoading = false;
  accountError = '';
  accountOrderPage = 1;
  accountOrderLastPage = 1;
  deletingIds = new Set<string>();
  private activeDialog: NbDialogRef<unknown> | null = null;

  constructor(
    private readonly customerService: CustomerService,
    private readonly orderService: OrderService,
    private readonly shiftService: ShiftService,
    private readonly feedback: OperationFeedbackService,
    private readonly dialogService: NbDialogService,
    readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    this.currencyService.currentCurrency$.subscribe(() => this.cdr.detectChanges());
    this.loadCustomers();
    this.shiftService.referenceData().subscribe({
      next: response => { this.paymentMethods = response.data.payment_methods; this.paymentForm.payment_method_id = this.paymentMethods[0]?.id || ''; },
      error: error => this.feedback.error(error, this.translate.instant('CUSTOMERS.PAYMENT_METHODS_FAILED')),
    });
  }

  private emptyCustomerForm() {
    return { name: '', company_name: '', email: '', phone: '', address: '', tax_number: '', credit_limit: 0 };
  }

  startCreate(): void {
    this.editingCustomerId = null;
    this.customerForm = this.emptyCustomerForm();
    this.formError = '';
    this.activeDialog = this.dialogService.open(DetailsDialogComponent, { context: { title: this.translate.instant('CUSTOMERS.ADD_TITLE'), contentTemplate: this.editFormTemplate, showFooter: false } });
  }

  startEdit(customer: Customer): void {
    this.editingCustomerId = customer.id;
    this.customerForm = {
      name: customer.name,
      company_name: customer.company_name || '',
      email: customer.email || '',
      phone: customer.phone || '',
      address: customer.address || '',
      tax_number: customer.tax_number || '',
      credit_limit: Number(customer.credit_limit || 0),
    };
    this.formError = '';
    this.activeDialog = this.dialogService.open(DetailsDialogComponent, { context: { title: this.translate.instant('CUSTOMERS.EDIT_TITLE', { name: customer.name }), contentTemplate: this.editFormTemplate, showFooter: false } });
  }

  closeForm(): void {
    this.activeDialog?.close();
    this.activeDialog = null;
    this.editingCustomerId = null;
    this.formError = '';
  }

  startPayment(customer: Customer): void {
    this.paymentCustomer = customer;
    this.paymentError = '';
    this.paymentForm = { order_id: '', payment_method_id: this.paymentMethods[0]?.id || '', amount: 0, reference_number: '', notes: '' };
    this.customerOrdersLoading = true;
    this.customerOrders = [];
    this.customerOrderPage = 1;
    this.customerOrderLastPage = 1;
    this.loadCustomerOrdersForDialog(1);
  }

  closePayment(): void {
    this.activeDialog?.close();
    this.activeDialog = null;
    this.paymentCustomer = null;
    this.customerOrders = [];
    this.paymentError = '';
  }

  loadCustomerOrders(page = 1, append = false): void {
    if (!this.paymentCustomer) {
      return;
    }
    this.customerOrdersLoading = true;
    const customerId = this.paymentCustomer.id;
    this.orderService.list(page, 25, customerId, true).subscribe({
      next: response => {
        if (this.paymentCustomer?.id !== customerId) return;
        this.customerOrders = append ? [...this.customerOrders, ...response.data] : response.data;
        this.customerOrderPage = response.meta.current_page;
        this.customerOrderLastPage = response.meta.last_page;
        this.customerOrdersLoading = false;
      },
      error: error => {
        this.customerOrdersLoading = false;
        this.paymentError = this.feedback.error(error, this.translate.instant('CUSTOMERS.LOAD_FAILED'));
      },
    });
  }

  private loadCustomerOrdersForDialog(page = 1): void {
    if (!this.paymentCustomer) return;
    const customerId = this.paymentCustomer.id;
    this.orderService.list(page, 25, customerId, true).subscribe({
      next: response => {
        if (this.paymentCustomer?.id !== customerId) return;
        this.customerOrders = response.data;
        this.customerOrderPage = response.meta.current_page;
        this.customerOrderLastPage = response.meta.last_page;
        this.customerOrdersLoading = false;
        this.activeDialog = this.dialogService.open(DetailsDialogComponent, { context: { title: this.translate.instant('CUSTOMERS.PAYMENT_PROCESSING', { name: this.paymentCustomer.name }), contentTemplate: this.paymentFormTemplate, showFooter: false } });
      },
      error: error => {
        this.customerOrdersLoading = false;
        this.paymentError = this.feedback.error(error, this.translate.instant('CUSTOMERS.LOAD_FAILED'));
        this.activeDialog = this.dialogService.open(DetailsDialogComponent, { context: { title: this.translate.instant('CUSTOMERS.PAYMENT_PROCESSING', { name: this.paymentCustomer.name }), contentTemplate: this.paymentFormTemplate, showFooter: false } });
      },
    });
  }

  savePayment(): void {
    if (this.paymentLoading) {
      return;
    }
    if (!this.paymentCustomer || !this.paymentForm.order_id || !this.paymentForm.payment_method_id || Number(this.paymentForm.amount) <= 0) {
      this.paymentError = this.translate.instant('CUSTOMERS.PAYMENT_VALIDATION');
      return;
    }
    this.paymentLoading = true;
    this.paymentError = '';
    this.customerService.collectPayment(this.paymentCustomer.id, { ...this.paymentForm, amount: Number(this.paymentForm.amount) }).subscribe({
      next: () => {
        this.paymentLoading = false;
        this.activeDialog?.close();
        this.activeDialog = null;
        this.feedback.success(this.translate.instant('CUSTOMERS.PAYMENT_SUCCESS'));
        this.loadCustomers(this.page);
        if (this.accountCustomer) this.loadAccountOrders(1);
      },
      error: error => { this.paymentLoading = false; this.paymentError = this.feedback.error(error, this.translate.instant('CUSTOMERS.PAYMENT_FAILED')); },
    });
  }

  loadCustomers(page = this.page): void {
    this.loading = true;
    this.errorMessage = '';
    this.customerService.list(page, this.perPage, this.appliedSearch).subscribe({
      next: response => {
        this.customers = response.data as CustomerWithTotal[];
        this.page = response.meta.current_page;
        this.totalPages = response.meta.last_page;
        this.totalCustomers = response.meta.total;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.errorMessage = this.translate.instant('CUSTOMERS.LOAD_FAILED');
      },
    });
  }

  applySearch(): void {
    if (this.loading) return;
    this.appliedSearch = this.search.trim();
    this.loadCustomers(1);
  }

  exportCustomers(): void {
    this.customerService.listAll(this.appliedSearch).subscribe({
      next: response => {
        downloadExcel('customers.csv', [this.translate.instant('CUSTOMERS.COL_NAME'), this.translate.instant('CUSTOMERS.FORM_COMPANY'), this.translate.instant('CUSTOMERS.COL_PHONE'), this.translate.instant('CUSTOMERS.COL_BALANCE')], response.data.map(customer => [
          customer.name, customer.company_name, customer.phone, (customer as CustomerWithTotal).invoices_total,
        ]));
        this.feedback.success(this.translate.instant('CUSTOMERS.EXPORT_SUCCESS'));
      },
      error: (err) => this.feedback.error(err, this.translate.instant('CUSTOMERS.LOAD_FAILED')),
    });
  }

  get pageNumbers(): number[] {
    return Array.from({ length: this.totalPages }, (_, index) => index + 1);
  }

  createCustomer(): void {
    if (this.formLoading) {
      return;
    }
    if (!this.customerForm.name.trim()) {
      this.formError = this.translate.instant('CUSTOMERS.NAME_REQUIRED');
      return;
    }
    this.formLoading = true;
    this.formError = '';
    const wasEditing = !!this.editingCustomerId;
    const request = this.editingCustomerId
      ? this.customerService.update(this.editingCustomerId, this.customerForm)
      : this.customerService.create(this.customerForm);
    request.subscribe({
      next: () => {
        this.formLoading = false;
        this.activeDialog?.close();
        this.activeDialog = null;
        this.feedback.success(wasEditing ? this.translate.instant('CUSTOMERS.SAVE_SUCCESS') : this.translate.instant('CUSTOMERS.SAVE_SUCCESS_NEW'));
        this.loadCustomers(1);
      },
      error: error => {
        this.formLoading = false;
        this.formError = this.feedback.error(error, this.translate.instant('CUSTOMERS.SAVE_FAILED'));
      },
    });
  }

  deleteCustomer(customer: Customer): void {
    if (this.deletingIds.has(customer.id) || !confirm(this.translate.instant('CUSTOMERS.DELETE_CONFIRM', { name: customer.name }))) {
      return;
    }
    this.deletingIds.add(customer.id);
    this.customerService.delete(customer.id).subscribe({
      next: () => { this.deletingIds.delete(customer.id); this.feedback.success(this.translate.instant('CUSTOMERS.DELETE_SUCCESS')); this.loadCustomers(this.customers.length === 1 ? Math.max(1, this.page - 1) : this.page); },
      error: error => { this.deletingIds.delete(customer.id); this.errorMessage = this.feedback.error(error, this.translate.instant('CUSTOMERS.DELETE_FAILED')); },
    });
  }

  openAccount(customer: Customer): void {
    this.accountCustomer = customer;
    this.statement = null;
    this.loadStatement();
    this.selectedAccountOrder = null;
    this.loadAccountOrders(1);
    this.dialogService.open(DetailsDialogComponent, {
      context: {
        title: this.translate.instant('CUSTOMERS.LEDGER_TITLE', { name: customer.name }),
        contentTemplate: this.accountTemplate,
      },
    });
  }

  loadAccountOrders(page = 1, append = false): void {
    if (!this.accountCustomer) return;
    this.accountLoading = true;
    this.accountError = '';
    const customerId = this.accountCustomer.id;
    this.orderService.list(page, 100, customerId).subscribe({
      next: response => {
        if (this.accountCustomer?.id !== customerId) return;
        this.accountOrders = append ? [...this.accountOrders, ...response.data] : response.data;
        this.accountOrderPage = response.meta.current_page;
        this.accountOrderLastPage = response.meta.last_page;
        this.accountLoading = false;
      },
      error: error => { this.accountLoading = false; this.accountError = this.feedback.error(error, this.translate.instant('CUSTOMERS.LEDGER_LOAD_FAILED')); },
    });
  }

  loadPaymentDetails(order: Order): void {
    this.accountDetailsLoading = true;
    this.orderService.show(order.id).subscribe({
      next: response => { this.selectedAccountOrder = response.data; this.accountDetailsLoading = false; },
      error: error => { this.accountDetailsLoading = false; this.accountError = this.feedback.error(error, this.translate.instant('CUSTOMERS.PAYMENTS_LOAD_FAILED')); },
    });
  }
}
