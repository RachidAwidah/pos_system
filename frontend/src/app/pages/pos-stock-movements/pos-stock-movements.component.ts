import { Component } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { StockMovement, StockMovementService } from '../../services/stock-movement.service';
import { ProductReferenceData, ProductService } from '../../services/product.service';
import { downloadExcel } from '../../shared/export.util';
import { OperationFeedbackService } from '../../services/operation-feedback.service';

@Component({
  selector: 'ngx-pos-stock-movements',
  styleUrls: ['./pos-stock-movements.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header">
        <div>
          <span class="eyebrow">{{ translate.instant('STOCK_MOVEMENTS.TITLE') }}</span>
          <h1>{{ translate.instant('STOCK_MOVEMENTS.SUBTITLE') }}</h1>
          <p class="summary" *ngIf="total">{{ translate.instant('STOCK_MOVEMENTS.TOTAL') }} {{ total }}</p>
        </div>
        <button nbButton status="primary" (click)="exportExcel()">{{ translate.instant('STOCK_MOVEMENTS.EXPORT') }}</button>
      </div>

      <nb-card>
        <nb-card-body class="filters">
          <input nbInput type="search" [(ngModel)]="search" (keyup.enter)="load(1)"
                 placeholder="{{ translate.instant('STOCK_MOVEMENTS.SEARCH_PLACEHOLDER') }}" class="search-input" />
          <select nbInput [(ngModel)]="movementType" (change)="load(1)">
            <option value="">{{ translate.instant('STOCK_MOVEMENTS.TYPE_ALL') }}</option>
            <option *ngFor="let t of movementTypes" [value]="t">{{ getTypeLabel(t) }}</option>
          </select>
          <select nbInput [(ngModel)]="warehouseId" (change)="load(1)">
            <option value="">{{ translate.instant('STOCK_MOVEMENTS.WAREHOUSE_ALL') }}</option>
            <option *ngFor="let w of warehouses" [value]="w.id">{{ w.name }}</option>
          </select>
          <input nbInput type="date" [(ngModel)]="dateFrom" (change)="load(1)"
                 placeholder="{{ translate.instant('STOCK_MOVEMENTS.DATE_FROM') }}" />
          <input nbInput type="date" [(ngModel)]="dateTo" (change)="load(1)"
                 placeholder="{{ translate.instant('STOCK_MOVEMENTS.DATE_TO') }}" />
          <button nbButton status="basic" (click)="resetFilters()">{{ translate.instant('STOCK_MOVEMENTS.RESET') }}</button>
        </nb-card-body>
      </nb-card>

      <nb-card>
        <nb-card-body>
          <div *ngIf="loading" class="state">{{ translate.instant('STOCK_MOVEMENTS.LOADING') }}</div>
          <div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
          <div *ngIf="!loading && !errorMessage && movements.length === 0" class="state">{{ translate.instant('STOCK_MOVEMENTS.NO_DATA') }}</div>

          <div class="table-wrapper" *ngIf="!loading && !errorMessage && movements.length">
            <table class="data-table">
              <thead>
                <tr>
                  <th>{{ translate.instant('STOCK_MOVEMENTS.COL_DATE') }}</th>
                  <th>{{ translate.instant('STOCK_MOVEMENTS.COL_PRODUCT') }}</th>
                  <th>{{ translate.instant('STOCK_MOVEMENTS.COL_TYPE') }}</th>
                  <th>{{ translate.instant('STOCK_MOVEMENTS.COL_QUANTITY') }}</th>
                  <th>{{ translate.instant('STOCK_MOVEMENTS.COL_WAREHOUSE') }}</th>
                  <th>{{ translate.instant('STOCK_MOVEMENTS.COL_USER') }}</th>
                  <th>{{ translate.instant('STOCK_MOVEMENTS.COL_NOTES') }}</th>
                  <th>{{ translate.instant('STOCK_MOVEMENTS.COL_REFERENCE') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr *ngFor="let m of movements">
                  <td>{{ m.occurred_at | date:'short' }}</td>
                  <td>
                    <span class="product-name">{{ m.product?.product_name || '—' }}</span>
                    <span class="sku">{{ m.product?.sku }}</span>
                  </td>
                  <td><span class="badge" [ngClass]="'badge-' + m.movement_type">{{ getTypeLabel(m.movement_type) }}</span></td>
                  <td [ngClass]="{ 'qty-positive': quantityDelta(m) > 0, 'qty-negative': quantityDelta(m) < 0 }">
                    {{ quantityDelta(m) > 0 ? '+' : '' }}{{ m.quantity_delta }}
                  </td>
                  <td>{{ m.warehouse?.name || '—' }}</td>
                  <td>{{ m.user?.full_name || '—' }}</td>
                  <td class="notes-cell">{{ m.notes || '—' }}</td>
                  <td>
                    <span *ngIf="m.order_id">{{ translate.instant('STOCK_MOVEMENTS.REF_SALE') }}</span>
                    <span *ngIf="m.purchase_order_id">{{ translate.instant('STOCK_MOVEMENTS.REF_PURCHASE') }}</span>
                    <span *ngIf="m.goods_receipt_id">{{ translate.instant('STOCK_MOVEMENTS.REF_RECEIPT') }}</span>
                    <span *ngIf="m.inventory_count_id">{{ translate.instant('STOCK_MOVEMENTS.REF_COUNT') }}</span>
                    <span *ngIf="m.transfer_batch_id">{{ translate.instant('STOCK_MOVEMENTS.REF_TRANSFER') }}</span>
                    <span *ngIf="!m.order_id && !m.purchase_order_id && !m.goods_receipt_id && !m.inventory_count_id && !m.transfer_batch_id">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div *ngIf="!loading && !errorMessage && totalPages > 1" class="pagination">
            <button nbButton size="small" status="basic" [disabled]="page === 1" (click)="load(page - 1)">{{ translate.instant('COMMON.PREVIOUS') }}</button>
            <button *ngFor="let pn of pageNumbers" nbButton size="small" [status]="pn === page ? 'primary' : 'basic'" (click)="load(pn)">{{ pn }}</button>
            <span>{{ translate.instant('STOCK_MOVEMENTS.PAGE_INFO', { current: page, last: totalPages }) }}</span>
            <button nbButton size="small" status="basic" [disabled]="page === totalPages" (click)="load(page + 1)">{{ translate.instant('COMMON.NEXT') }}</button>
          </div>
        </nb-card-body>
      </nb-card>
    </div>
  `,
})
export class PosStockMovementsComponent {
  movements: StockMovement[] = [];
  loading = false;
  errorMessage = '';
  page = 1;
  totalPages = 1;
  total = 0;

  search = '';
  movementType = '';
  warehouseId = '';
  dateFrom = '';
  dateTo = '';
  perPage = 25;

  warehouses: Array<{ id: string; name: string }> = [];

  movementTypes = [
    'opening', 'sale', 'purchase', 'customer_return', 'supplier_return',
    'adjustment', 'damage', 'transfer_in', 'transfer_out',
  ];

  constructor(
    private readonly stockMovementService: StockMovementService,
    private readonly productService: ProductService,
    private readonly feedback: OperationFeedbackService,
    readonly translate: TranslateService,
  ) {
    this.loadWarehouses();
    this.load();
  }

  getTypeLabel(type: string): string {
    return this.translate.instant('STOCK_MOVEMENTS.TYPE_' + type.toUpperCase());
  }

  quantityDelta(m: StockMovement): number {
    return parseFloat(m.quantity_delta) || 0;
  }

  load(page = this.page): void {
    this.loading = true;
    this.errorMessage = '';

    this.stockMovementService.list({
      per_page: this.perPage,
      page,
      search: this.search || null,
      movement_type: this.movementType || null,
      warehouse_id: this.warehouseId || null,
      from: this.dateFrom || null,
      to: this.dateTo || null,
    }).subscribe({
      next: (res) => {
        this.movements = res.data;
        this.page = res.current_page;
        this.totalPages = res.last_page;
        this.total = res.total;
        this.loading = false;
      },
      error: (err) => {
        this.errorMessage = this.feedback.error(err, this.translate.instant('STOCK_MOVEMENTS.LOAD_FAILED'));
        this.loading = false;
      },
    });
  }

  resetFilters(): void {
    this.search = '';
    this.movementType = '';
    this.warehouseId = '';
    this.dateFrom = '';
    this.dateTo = '';
    this.load(1);
  }

  exportExcel(): void {
    this.stockMovementService.listAll({
      search: this.search || null,
      movement_type: this.movementType || null,
      warehouse_id: this.warehouseId || null,
      from: this.dateFrom || null,
      to: this.dateTo || null,
    }).subscribe({
      next: (res) => {
        const headers = [
          this.translate.instant('STOCK_MOVEMENTS.COL_DATE'),
          this.translate.instant('STOCK_MOVEMENTS.COL_PRODUCT'),
          'SKU',
          this.translate.instant('STOCK_MOVEMENTS.COL_TYPE'),
          this.translate.instant('STOCK_MOVEMENTS.COL_QUANTITY'),
          this.translate.instant('STOCK_MOVEMENTS.COL_WAREHOUSE'),
          this.translate.instant('STOCK_MOVEMENTS.COL_USER'),
          this.translate.instant('STOCK_MOVEMENTS.COL_NOTES'),
        ];
        const rows = res.data.map(m => [
          m.occurred_at,
          m.product?.product_name || '',
          m.product?.sku || '',
          this.getTypeLabel(m.movement_type),
          parseFloat(m.quantity_delta),
          m.warehouse?.name || '',
          m.user?.full_name || '',
          m.notes || '',
        ]);
        downloadExcel('stock-movements.csv', headers, rows);
        this.feedback.success(this.translate.instant('STOCK_MOVEMENTS.EXPORT_SUCCESS'));
      },
      error: (err) => {
        this.feedback.error(err, this.translate.instant('STOCK_MOVEMENTS.EXPORT_FAILED'));
      },
    });
  }

  get pageNumbers(): number[] {
    const pages: number[] = [];
    const start = Math.max(1, this.page - 2);
    const end = Math.min(this.totalPages, this.page + 2);
    for (let i = start; i <= end; i++) {
      pages.push(i);
    }
    return pages;
  }

  private loadWarehouses(): void {
    this.productService.referenceData().subscribe({
      next: (res) => {
        this.warehouses = res.data.warehouses;
      },
      error: () => {},
    });
  }
}
