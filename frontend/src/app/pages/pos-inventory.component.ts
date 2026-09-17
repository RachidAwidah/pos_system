import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { Product, ProductService } from '../services/product.service';
import { downloadExcel } from '../shared/export.util';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { TranslateService } from '@ngx-translate/core';
import { CurrencyService } from '../services/currency.service';

const STOCK_CATEGORY_PALETTE = ['#2563EB', '#EA580C', '#16A34A', '#9333EA', '#DB2777', '#CA8A04', '#0D9488', '#475569'];

@Component({
  selector: 'ngx-pos-inventory',
  styleUrls: ['./pos-inventory.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header">
        <div>
          <span class="eyebrow">{{ translate.instant('INVENTORY.TITLE') }}</span>
          <h1>{{ translate.instant('INVENTORY.SUBTITLE') }}</h1>
        </div>
        <div><button nbButton status="basic" [disabled]="loading || !products.length" (click)="exportInventory()">تصدير Excel</button></div>
      </div>
      <div class="cards">
        <nb-card>
          <nb-card-body>
            <strong>{{ translate.instant('INVENTORY.CURRENT_STOCK') }}</strong>
            <h2>{{ totalQuantity | number:'1.0-3' }}</h2>
          </nb-card-body>
        </nb-card>
        <nb-card>
          <nb-card-body>
            <strong>{{ translate.instant('INVENTORY.OUT_OF_STOCK') }}</strong>
            <h2>{{ outOfStockCount }}</h2>
          </nb-card-body>
        </nb-card>
        <nb-card>
          <nb-card-body>
            <strong>{{ translate.instant('INVENTORY.LOW_STOCK') }}</strong>
            <h2>{{ lowStockCount }}</h2>
          </nb-card-body>
        </nb-card>
      </div>

      <div class="charts-row" *ngIf="!loading && products.length">
        <nb-card>
          <nb-card-header>{{ translate.instant('INVENTORY.STOCK_BY_CATEGORY') }}</nb-card-header>
          <nb-card-body>
            <div *ngIf="stockPieOptions; else noPieData" echarts [options]="stockPieOptions" class="chart-container"></div>
            <ng-template #noPieData>
              <div class="chart-empty">{{ translate.instant('INVENTORY.NO_CHART_DATA') }}</div>
            </ng-template>
          </nb-card-body>
        </nb-card>
        <nb-card>
          <nb-card-header>{{ translate.instant('INVENTORY.LOW_STOCK_TITLE') }}</nb-card-header>
          <nb-card-body>
            <div *ngIf="lowStockBarOptions; else noBarData" echarts [options]="lowStockBarOptions" class="chart-container"></div>
            <ng-template #noBarData>
              <div class="chart-empty">{{ translate.instant('INVENTORY.LOW_STOCK_EMPTY') }}</div>
            </ng-template>
          </nb-card-body>
        </nb-card>
      </div>

      <nb-card>
        <nb-card-body>
          <div *ngIf="loading" class="state">{{ translate.instant('INVENTORY.LOADING') }}</div>
          <div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
          <div *ngIf="!loading && !errorMessage && products.length === 0" class="state">{{ translate.instant('INVENTORY.NO_DATA') }}</div>
          <table *ngIf="!loading && !errorMessage && products.length" class="data-table">
            <thead>
              <tr>
                <th>{{ translate.instant('INVENTORY.COL_PRODUCT') }}</th>
                <th>SKU</th>
                <th>{{ translate.instant('INVENTORY.COL_WAREHOUSE') }}</th>
                <th>{{ translate.instant('INVENTORY.COL_AVAILABLE') }}</th>
                <th>{{ translate.instant('INVENTORY.COL_RESERVED') }}</th>
                <th>{{ translate.instant('INVENTORY.COL_REORDER') }}</th>
                <th>{{ translate.instant('INVENTORY.COL_STATUS') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let product of products">
                <td>{{ product.product_name }}</td>
                <td>{{ product.sku }}</td>
                <td>{{ product.inventory?.warehouse_name ?? '—' }}</td>
                <td>{{ product.inventory?.quantity_available ?? '—' }}</td>
                <td>{{ product.inventory?.quantity_reserved ?? '—' }}</td>
                <td>{{ product.inventory?.reorder_level ?? '—' }}</td>
                <td>
                  <span class="status" [class.danger]="isOutOfStock(product)" [class.warning]="isLowStock(product) && !isOutOfStock(product)" [class.success]="!isLowStock(product)">
                    {{ isOutOfStock(product) ? translate.instant('INVENTORY.STATUS_OUT') : (isLowStock(product) ? translate.instant('INVENTORY.STATUS_LOW') : translate.instant('INVENTORY.STATUS_IN_STOCK')) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </nb-card-body>
      </nb-card>
    </div>
  `,
})
export class PosInventoryComponent implements OnInit {
  products: Product[] = [];
  loading = false;
  errorMessage = '';
  totalQuantity = 0;
  lowStockCount = 0;
  outOfStockCount = 0;
  stockPieOptions: any = null;
  lowStockBarOptions: any = null;

  constructor(
    private readonly productService: ProductService,
    private readonly feedback: OperationFeedbackService,
    readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    this.currencyService.currentCurrency$.subscribe(() => this.cdr.detectChanges());
  }

  ngOnInit(): void {
    this.loadInventory();
  }

  buildCharts(): void {
    this.buildPieChart();
    this.buildLowStockChart();
  }

  buildPieChart(): void {
    const pieData = this.buildPieData();
    if (!pieData.length || (pieData.length === 1 && pieData[0].name === this.translate.instant('INVENTORY.NO_DATA_TABLE'))) {
      this.stockPieOptions = null;
      return;
    }

    this.stockPieOptions = {
      color: STOCK_CATEGORY_PALETTE,
      tooltip: {
        trigger: 'item',
        formatter: '{b}: {c} ({d}%)',
        backgroundColor: '#ffffff',
        borderColor: '#0077B6',
        textStyle: { color: '#03045E' },
      },
      legend: {
        orient: 'vertical',
        right: '5%',
        top: 'center',
        textStyle: { color: '#023E8A' },
      },
      series: [
        {
          name: this.translate.instant('INVENTORY.CHART_NAME'),
          type: 'pie',
          radius: ['40%', '70%'],
          center: ['40%', '50%'],
          avoidLabelOverlap: true,
          label: { show: false },
          emphasis: {
            label: { show: true, fontSize: 14, fontWeight: 'bold' },
          },
          labelLine: { show: false },
          itemStyle: { borderColor: '#ffffff', borderWidth: 2 },
          data: pieData,
        },
      ],
    };
  }

  buildLowStockChart(): void {
    const labels = this.buildLowStockLabels();
    const data = this.buildLowStockData();

    if (!labels.length) {
      this.lowStockBarOptions = null;
      return;
    }

    this.lowStockBarOptions = {
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        backgroundColor: '#ffffff',
        borderColor: '#0077B6',
        textStyle: { color: '#03045E' },
      },
      grid: {
        left: '3%', right: '4%', bottom: '3%', containLabel: true,
      },
      xAxis: {
        type: 'value',
        axisLine: { lineStyle: { color: '#0077B6' } },
        splitLine: { lineStyle: { color: '#e0f0ff' } },
        axisLabel: { textStyle: { color: '#023E8A' } },
      },
      yAxis: {
        type: 'category',
        data: labels,
        axisLine: { lineStyle: { color: '#0077B6' } },
        axisLabel: { textStyle: { color: '#023E8A', fontSize: 11 } },
      },
      series: [
        {
          name: this.translate.instant('INVENTORY.CHART_AVAILABLE'),
          type: 'bar',
          barWidth: '60%',
          data: data,
          itemStyle: {
            color: (params: any) => {
              const val = params.value;
              if (val <= 0) return '#03045E';
              if (val <= 5) return '#0077B6';
              return '#00B4D8';
            },
            borderRadius: [0, 4, 4, 0],
          },
          label: {
            show: true,
            position: 'right',
            textStyle: { color: '#023E8A', fontSize: 11 },
          },
        },
      ],
    };
  }

  buildPieData(): Array<{ name: string; value: number }> {
    const categoryMap = new Map<string, number>();
    for (const product of this.products) {
      const cat = product.category?.category_name || this.translate.instant('INVENTORY.NO_CATEGORY');
      const qty = Number(product.inventory?.quantity_available ?? 0);
      categoryMap.set(cat, (categoryMap.get(cat) || 0) + qty);
    }
    const result: Array<{ name: string; value: number }> = [];
    categoryMap.forEach((value, name) => {
      result.push({ name, value });
    });
    return result;
  }

  buildLowStockLabels(): string[] {
    const low = this.products
      .filter(p => this.isLowStock(p) || this.isOutOfStock(p))
      .sort((a, b) => Number(a.inventory?.quantity_available ?? 0) - Number(b.inventory?.quantity_available ?? 0))
      .slice(0, 10);
    return low.map(p => p.product_name.length > 18 ? p.product_name.substring(0, 18) + '…' : p.product_name);
  }

  buildLowStockData(): number[] {
    const low = this.products
      .filter(p => this.isLowStock(p) || this.isOutOfStock(p))
      .sort((a, b) => Number(a.inventory?.quantity_available ?? 0) - Number(b.inventory?.quantity_available ?? 0))
      .slice(0, 10);
    return low.map(p => Number(p.inventory?.quantity_available ?? 0));
  }

  loadInventory(): void {
    this.loading = true;
    this.errorMessage = '';
    this.productService.list({ per_page: 100, page: 1 }).subscribe({
      next: (response) => {
        this.products = response.data.filter(product => product.inventory);
        this.totalQuantity = this.products.reduce(
          (total, product) => total + Number(product.inventory?.quantity_available ?? 0),
          0,
        );
        this.lowStockCount = this.products.filter(product => this.isLowStock(product)).length;
        this.outOfStockCount = this.products.filter(product => this.isOutOfStock(product)).length;
        this.loading = false;
        this.buildCharts();
      },
      error: () => {
        this.loading = false;
        this.errorMessage = this.translate.instant('INVENTORY.LOAD_FAILED');
      },
    });
  }

  isLowStock(product: Product): boolean {
    const inventory = product.inventory;
    return !!inventory && Number(inventory.quantity_available) <= Number(inventory.reorder_level);
  }

  isOutOfStock(product: Product): boolean {
    return Number(product.inventory?.quantity_available ?? 0) <= 0;
  }

  exportInventory(): void {
    this.productService.listAll().subscribe({
      next: response => {
        const productsWithData = response.data.filter(product => product.inventory);
        downloadExcel('inventory.csv', [
          this.translate.instant('INVENTORY.COL_PRODUCT'),
          'SKU',
          this.translate.instant('INVENTORY.COL_WAREHOUSE'),
          this.translate.instant('INVENTORY.COL_AVAILABLE'),
          this.translate.instant('INVENTORY.COL_RESERVED'),
          this.translate.instant('INVENTORY.COL_REORDER'),
        ], productsWithData.map(product => [
          product.product_name, product.sku, product.inventory?.warehouse_name ?? '—', product.inventory?.quantity_available, product.inventory?.quantity_reserved, product.inventory?.reorder_level,
        ]));
        this.feedback.success(this.translate.instant('INVENTORY.EXPORT_SUCCESS'));
      },
      error: (err) => this.feedback.error(err, this.translate.instant('INVENTORY.EXPORT_FAILED')),
    });
  }
}
