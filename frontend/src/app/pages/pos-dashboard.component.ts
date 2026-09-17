import { Component, ChangeDetectorRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { ReportOverview, ReportService } from '../services/report.service';
import { Order, OrderService } from '../services/order.service';
import { downloadExcel } from '../shared/export.util';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { downloadElementScreenshot } from '../shared/screenshot.util';
import { CurrencyService } from '../services/currency.service';

@Component({
  selector: 'ngx-pos-dashboard',
  templateUrl: './pos-dashboard.component.html',
  styleUrls: ['./pos-dashboard.component.scss'],
})
export class PosDashboardComponent {
  overview: ReportOverview | null = null;
  loading = false;
  errorMessage = '';
  recentOrders: Order[] = [];
  ordersError = '';
  exportingImage = false;

  constructor(
    private readonly reportService: ReportService,
    private readonly orderService: OrderService,
    private readonly feedback: OperationFeedbackService,
    private readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly cdr: ChangeDetectorRef,
  ) {
    this.currencyService.currentCurrency$.subscribe(() => this.cdr.detectChanges());
    const today = new Date().toISOString().slice(0, 10);
    this.loading = true;
    this.reportService.overview(today, today).subscribe({
      next: response => {
        this.overview = response.data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.errorMessage = this.translate.instant('DASHBOARD.LOAD_FAILED');
      },
    });
    this.orderService.list().subscribe({
      next: response => this.recentOrders = response.data,
      error: () => this.ordersError = this.translate.instant('DASHBOARD.ORDERS_LOAD_FAILED'),
    });
  }

  get stats() {
    const summary = this.overview?.summary;
    return [
      { title: this.translate.instant('DASHBOARD.NET_SALES'), value: summary ? this.currencyService.formatAmount(Number(summary.net_sales)) : '—', change: '', tone: 'positive' },
      { title: this.translate.instant('DASHBOARD.TODAY_ORDERS'), value: summary?.orders_count ?? '—', change: '', tone: 'positive' },
      { title: this.translate.instant('DASHBOARD.GROSS_PROFIT'), value: summary ? this.currencyService.formatAmount(Number(summary.gross_profit)) : '—', change: '', tone: 'positive' },
      { title: this.translate.instant('DASHBOARD.AVG_ORDER'), value: summary ? this.currencyService.formatAmount(Number(summary.average_order_value)) : '—', change: '', tone: 'positive' },
    ];
  }

  async exportDashboardImage(): Promise<void> {
    const element = document.getElementById('dashboard-export-area');
    if (!element || this.exportingImage) {
      this.feedback.error(null, this.translate.instant('DASHBOARD.LOAD_CONTENT_FAILED'));
      return;
    }

    this.exportingImage = true;

    try {
      await downloadElementScreenshot(element, `dashboard-${new Date().toISOString().slice(0, 10)}.png`);
      this.feedback.success(this.translate.instant('DASHBOARD.EXPORT_SUCCESS'));
    } catch (error) {
      this.feedback.error(error, this.translate.instant('DASHBOARD.EXPORT_FAILED'));
    } finally {
      this.exportingImage = false;
    }
  }

  exportRecentOrders(): void {
    downloadExcel('dashboard-orders.xls', [this.translate.instant('DASHBOARD.TABLE_ORDER'), this.translate.instant('DASHBOARD.TABLE_CUSTOMER'), this.translate.instant('DASHBOARD.TABLE_AMOUNT'), this.translate.instant('DASHBOARD.TABLE_STATUS')], this.recentOrders.map(order => [
      order.invoice_number, order.customer?.name || this.translate.instant('DASHBOARD.CASH_CUSTOMER'), order.final_amount, order.payment_status,
    ]));
    this.feedback.success(this.translate.instant('COMMON.EXPORT_SUCCESS'));
  }
}
