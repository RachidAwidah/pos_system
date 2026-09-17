import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { ReportOverview, ReportService } from '../services/report.service';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { downloadElementScreenshot } from '../shared/screenshot.util';
import { CurrencyService } from '../services/currency.service';

const OCEAN_BLUE_PALETTE = ['#03045E', '#023E8A', '#0077B6', '#0096C7', '#00B4D8', '#48CAE4'];

@Component({
  selector: 'ngx-pos-reports',
  styleUrls: ['./pos-reports.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header">
        <div>
          <span class="eyebrow">{{ translate.instant('REPORTS.TITLE') }}</span>
          <h1>{{ translate.instant('REPORTS.SUBTITLE') }}</h1>
        </div>
        <button nbButton status="primary" (click)="exportReportImage()" [disabled]="!overview || loading || exportingImage">{{ exportingImage ? translate.instant('REPORTS.EXPORTING_IMAGE') : translate.instant('REPORTS.EXPORT_IMAGE') }}</button>
      </div>

       <nb-card>
      <nb-card-body class="report-filters">
        <label>{{ translate.instant('REPORTS.DATE_FROM') }} <input nbInput type="date" [(ngModel)]="fromDate"></label>
        <label>{{ translate.instant('REPORTS.DATE_TO') }} <input nbInput type="date" [(ngModel)]="toDate"></label>
        <button nbButton status="control" (click)="setPeriod('today')">{{ translate.instant('REPORTS.RANGE_TODAY') }}</button>
        <button nbButton status="control" (click)="setPeriod('month')">{{ translate.instant('REPORTS.RANGE_MONTH') }}</button>
        <button nbButton status="control" (click)="setPeriod('all')">{{ translate.instant('REPORTS.RANGE_ALL') }}</button>
        <button nbButton status="basic" (click)="loadReport()">{{ translate.instant('REPORTS.REFRESH') }}</button>
      </nb-card-body>
       </nb-card>
       <div class="report-export" #reportExport>

       <nb-card *ngIf="salesChartOptions">
         <nb-card-header>{{ translate.instant('REPORTS.CHART_TITLE') }}</nb-card-header>
         <nb-card-body>
           <div echarts [options]="salesChartOptions" class="chart-container"></div>
         </nb-card-body>
       </nb-card>

       <div class="cards">
        <nb-card>
          <nb-card-body>
            <strong>{{ translate.instant('REPORTS.CHART_NET_SALES') }}</strong>
            <h2>{{ overview?.summary?.net_total ? (overview.summary.net_total | appCurrency) : '—' }}</h2>
          </nb-card-body>
        </nb-card>
        <nb-card>
          <nb-card-body>
            <strong>{{ translate.instant('REPORTS.CHART_RETURNS') }}</strong>
            <h2>{{ overview?.summary?.returns_count ?? '—' }}</h2>
          </nb-card-body>
        </nb-card>
        <nb-card>
          <nb-card-body>
            <strong>{{ translate.instant('REPORTS.CHART_PROFIT') }}</strong>
            <h2>{{ overview?.summary?.gross_profit ? (overview.summary.gross_profit | appCurrency) : '—' }}</h2>
          </nb-card-body>
        </nb-card>
      </div>
      <nb-card *ngIf="loading || errorMessage">
        <nb-card-body class="state" [class.error]="errorMessage">
          {{ loading ? translate.instant('REPORTS.LOADING') : errorMessage }}
        </nb-card-body>
      </nb-card>
      <nb-card *ngIf="overview && !loading">
        <nb-card-body>
          {{ translate.instant('REPORTS.SUMMARY_PERIOD') }} {{ overview.period.from }} {{ translate.instant('REPORTS.SUMMARY_TO') }} {{ overview.period.to }}
          <div class="report-meta">
            <span>{{ translate.instant('REPORTS.SUMMARY_ORDERS') }} {{ overview.summary.orders_count }}</span>
            <span>{{ translate.instant('REPORTS.SUMMARY_AVG') }} {{ overview.summary.average_order_value | appCurrency }}</span>
            <span>{{ translate.instant('REPORTS.SUMMARY_MARGIN') }} {{ overview.summary.gross_margin_percentage }}%</span>
          </div>
        </nb-card-body>
      </nb-card>
      <div class="report-tables" *ngIf="overview && !loading">
        <nb-card>
          <nb-card-header>{{ translate.instant('REPORTS.TREND_TITLE') }}</nb-card-header>
          <nb-card-body>
            <table class="data-table" *ngIf="overview.trend.length">
              <thead><tr><th>{{ translate.instant('REPORTS.TREND_COL_DATE') }}</th><th>{{ translate.instant('REPORTS.TREND_COL_NET_SALES') }}</th><th>{{ translate.instant('REPORTS.TREND_COL_PROFIT') }}</th></tr></thead>
              <tbody>
                <tr *ngFor="let item of overview.trend">
                  <td>{{ item.date }}</td>
                  <td>{{ item.net_sales | appCurrency }}</td>
                  <td>{{ item.gross_profit | appCurrency }}</td>
                </tr>
              </tbody>
            </table>
            <div *ngIf="!overview.trend.length" class="state">{{ translate.instant('REPORTS.TREND_NO_DATA') }}</div>
          </nb-card-body>
        </nb-card>
        <nb-card>
          <nb-card-header>{{ translate.instant('REPORTS.TOP_PRODUCTS_TITLE') }}</nb-card-header>
          <nb-card-body>
            <table class="data-table" *ngIf="overview.top_products.length">
              <thead><tr><th>{{ translate.instant('REPORTS.TOP_COL_PRODUCT') }}</th><th>{{ translate.instant('REPORTS.TOP_COL_QTY') }}</th><th>{{ translate.instant('REPORTS.TOP_COL_SALES') }}</th></tr></thead>
              <tbody>
                <tr *ngFor="let product of overview.top_products">
                  <td>{{ product.product_name }}</td>
                  <td>{{ product.net_quantity }}</td>
                  <td>{{ product.net_sales | appCurrency }}</td>
                </tr>
              </tbody>
            </table>
            <div *ngIf="!overview.top_products.length" class="state">{{ translate.instant('REPORTS.TOP_NO_DATA') }}</div>
          </nb-card-body>
        </nb-card>
      </div>
      </div>
    </div>
  `,
})
export class PosReportsComponent implements OnInit {
  overview: ReportOverview | null = null;
  loading = false;
  errorMessage = '';
  exportingImage = false;
  fromDate: string;
  toDate: string;
  salesChartOptions: any = null;

  constructor(
    private readonly reportService: ReportService,
    private readonly feedback: OperationFeedbackService,
    readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    this.currencyService.currentCurrency$.subscribe(() => this.cdr.detectChanges());
    const today = new Date();
    this.toDate = today.toISOString().slice(0, 10);
    this.fromDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
  }

  ngOnInit(): void {
    this.loadReport();
  }

  buildSalesChart(): void {
    if (!this.overview?.trend?.length) {
      this.salesChartOptions = null;
      return;
    }

    const trend = this.overview.trend;

    this.salesChartOptions = {
      color: OCEAN_BLUE_PALETTE,
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        backgroundColor: '#ffffff',
        borderColor: '#0077B6',
        textStyle: { color: '#03045E' },
      },
      legend: {
        data: [this.translate.instant('REPORTS.CHART_LEGEND_NET'), this.translate.instant('REPORTS.CHART_LEGEND_PROFIT')],
        textStyle: { color: '#023E8A' },
      },
      grid: {
        left: '3%', right: '4%', bottom: '3%', containLabel: true,
      },
      xAxis: {
        type: 'category',
        data: trend.map(t => t.date),
        axisTick: { alignWithLabel: true },
        axisLine: { lineStyle: { color: '#0077B6' } },
        axisLabel: { textStyle: { color: '#023E8A' } },
      },
      yAxis: {
        type: 'value',
        axisLine: { lineStyle: { color: '#0077B6' } },
        splitLine: { lineStyle: { color: '#e0f0ff' } },
        axisLabel: { textStyle: { color: '#023E8A' } },
      },
      series: [
        {
          name: this.translate.instant('REPORTS.CHART_LEGEND_NET'),
          type: 'bar',
          barWidth: '35%',
          data: trend.map(t => Number(t.net_sales)),
          itemStyle: {
            color: {
              type: 'linear',
              x: 0, y: 0, x2: 0, y2: 1,
              colorStops: [
                { offset: 0, color: '#0096C7' },
                { offset: 1, color: '#0077B6' },
              ],
            },
            borderRadius: [4, 4, 0, 0],
          },
        },
        {
          name: this.translate.instant('REPORTS.CHART_LEGEND_PROFIT'),
          type: 'bar',
          barWidth: '35%',
          data: trend.map(t => Number(t.gross_profit)),
          itemStyle: {
            color: {
              type: 'linear',
              x: 0, y: 0, x2: 0, y2: 1,
              colorStops: [
                { offset: 0, color: '#48CAE4' },
                { offset: 1, color: '#00B4D8' },
              ],
            },
            borderRadius: [4, 4, 0, 0],
          },
        },
      ],
    };
  }

  loadReport(): void {
    this.loading = true;
    this.errorMessage = '';
    this.reportService.overview(this.fromDate, this.toDate).subscribe({
      next: response => {
        this.overview = response.data;
        this.loading = false;
        this.buildSalesChart();
      },
      error: () => {
        this.loading = false;
        this.errorMessage = this.translate.instant('REPORTS.LOAD_FAILED');
      },
    });
  }

  setPeriod(period: 'today' | 'month' | 'all'): void {
    const today = new Date();
    this.toDate = today.toISOString().slice(0, 10);
    if (period === 'today') {
      this.fromDate = this.toDate;
    } else if (period === 'month') {
      this.fromDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
    } else {
      this.fromDate = '2000-01-01';
    }
    this.loadReport();
  }

  async exportReportImage(): Promise<void> {
    const report = document.querySelector('.report-export');
    if (!(report instanceof HTMLElement) || !this.overview || this.exportingImage) {
      this.feedback.error(null, this.translate.instant('REPORTS.NO_REPORT'));
      return;
    }

    this.exportingImage = true;
    try {
      await downloadElementScreenshot(report, `report-${this.fromDate}-${this.toDate}.png`);
      this.feedback.success(this.translate.instant('REPORTS.EXPORT_SUCCESS'));
    } catch (error) {
      this.feedback.error(error, this.translate.instant('REPORTS.EXPORT_FAILED'));
    } finally {
      this.exportingImage = false;
    }
  }
}
