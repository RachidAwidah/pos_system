import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { SettingsService, StoreSetting } from '../services/settings.service';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { LanguageService } from '../@core/services/language.service';
import { TranslateService } from '@ngx-translate/core';
import { ExchangeRateService } from '../services/exchange-rate.service';

@Component({
  selector: 'ngx-pos-settings',
  styleUrls: ['./pos-settings.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header">
        <div>
          <span class="eyebrow">{{ 'SETTINGS.TITLE' | translate }}</span>
          <h1>{{ 'SETTINGS.TITLE' | translate }}</h1>
        </div>
      </div>

      <div *ngIf="loading" class="state">{{ 'SETTINGS.LOADING' | translate }}</div>
      <div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>

      <form *ngIf="!loading && !errorMessage" [formGroup]="settingsForm" (ngSubmit)="save()">
        <nb-card class="settings-tabs-card">
          <nb-tabset fullWidth>
            <!-- Tab 1: General -->
            <nb-tab [tabTitle]="'SETTINGS.TABS.GENERAL' | translate">
              <div class="tab-content">
                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.GENERAL.LANGUAGE' | translate }}</label>
                  <nb-select formControlName="language" fullWidth>
                    <nb-option value="ar">{{ 'SETTINGS.GENERAL.LANGUAGE_AR' | translate }}</nb-option>
                    <nb-option value="en">English</nb-option>
                  </nb-select>
                  <span class="form-hint">{{ 'SETTINGS.GENERAL.LANGUAGE_HINT' | translate }}</span>
                </div>

                <div class="form-group form-group-toggle">
                  <div class="toggle-row">
                    <label class="form-label">{{ 'SETTINGS.GENERAL.RTL_ENABLED' | translate }}</label>
                    <nb-toggle formControlName="rtl_enabled" status="primary" labelPosition="end"></nb-toggle>
                  </div>
                  <span class="form-hint">{{ 'SETTINGS.GENERAL.RTL_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.GENERAL.DATE_FORMAT' | translate }}</label>
                  <nb-select formControlName="date_format" fullWidth>
                    <nb-option value="Y-m-d">Y-m-d (2026-09-10)</nb-option>
                    <nb-option value="d/m/Y">d/m/Y (10/09/2026)</nb-option>
                    <nb-option value="m/d/Y">m/d/Y (09/10/2026)</nb-option>
                    <nb-option value="d-m-Y">d-m-Y (10-09-2026)</nb-option>
                  </nb-select>
                  <span class="form-hint">{{ 'SETTINGS.GENERAL.DATE_FORMAT_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.GENERAL.DEFAULT_DISPLAY_CURRENCY' | translate }}</label>
                  <nb-select formControlName="default_display_currency" fullWidth>
                    <nb-option value="USD">{{ 'SETTINGS.GENERAL.CURRENCY_USD' | translate }}</nb-option>
                    <nb-option value="SYP">{{ 'SETTINGS.GENERAL.CURRENCY_SYP' | translate }}</nb-option>
                    <nb-option value="TRY">{{ 'SETTINGS.GENERAL.CURRENCY_TRY' | translate }}</nb-option>
                  </nb-select>
                  <span class="form-hint">{{ 'SETTINGS.GENERAL.DEFAULT_DISPLAY_CURRENCY_HINT' | translate }}</span>
                </div>
              </div>
            </nb-tab>

            <!-- Tab 2: Store -->
            <nb-tab [tabTitle]="'SETTINGS.TABS.STORE' | translate">
              <div class="tab-content">
                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.STORE.NAME' | translate }}</label>
                  <input nbInput type="text" fullWidth formControlName="store_name" [placeholder]="'SETTINGS.STORE.NAME' | translate" />
                  <span class="form-hint">{{ 'SETTINGS.STORE.NAME_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.STORE.EMAIL' | translate }}</label>
                  <input nbInput type="email" fullWidth formControlName="store_email" placeholder="store@example.com" />
                  <span class="form-hint">{{ 'SETTINGS.STORE.EMAIL_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.STORE.PHONE' | translate }}</label>
                  <input nbInput type="tel" fullWidth formControlName="store_phone" placeholder="+966..." />
                  <span class="form-hint">{{ 'SETTINGS.STORE.PHONE_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.STORE.ADDRESS' | translate }}</label>
                  <textarea nbInput fullWidth formControlName="store_address" [placeholder]="'SETTINGS.STORE.ADDRESS_PLACEHOLDER' | translate" rows="3"></textarea>
                  <span class="form-hint">{{ 'SETTINGS.STORE.ADDRESS_HINT' | translate }}</span>
                </div>
              </div>
            </nb-tab>

            <!-- Tab 3: Tax -->
            <nb-tab [tabTitle]="'SETTINGS.TABS.TAX' | translate">
              <div class="tab-content">
                <div class="form-group form-group-toggle">
                  <div class="toggle-row">
                    <label class="form-label">{{ 'SETTINGS.TAX.INCLUDED' | translate }}</label>
                    <nb-toggle formControlName="tax_included" status="primary" labelPosition="end"></nb-toggle>
                  </div>
                  <span class="form-hint">{{ 'SETTINGS.TAX.INCLUDED_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.TAX.DEFAULT_RATE' | translate }}</label>
                  <input nbInput type="number" fullWidth formControlName="default_tax_rate" placeholder="15" step="0.01" min="0" max="100" />
                  <span class="form-hint">{{ 'SETTINGS.TAX.DEFAULT_RATE_HINT' | translate }}</span>
                </div>
              </div>
            </nb-tab>

            <!-- Tab 4: Loyalty -->
            <nb-tab [tabTitle]="'SETTINGS.TABS.LOYALTY' | translate">
              <div class="tab-content">
                <div class="form-group form-group-toggle">
                  <div class="toggle-row">
                    <label class="form-label">{{ 'SETTINGS.LOYALTY.ENABLED' | translate }}</label>
                    <nb-toggle formControlName="loyalty_enabled" status="primary" labelPosition="end"></nb-toggle>
                  </div>
                  <span class="form-hint">{{ 'SETTINGS.LOYALTY.ENABLED_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.LOYALTY.POINTS_PER_CURRENCY' | translate }}</label>
                  <input nbInput type="number" fullWidth formControlName="loyalty_points_per_currency" placeholder="1" step="0.1" min="0" />
                  <span class="form-hint">{{ 'SETTINGS.LOYALTY.POINTS_PER_CURRENCY_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.LOYALTY.POINT_VALUE' | translate }}</label>
                  <input nbInput type="number" fullWidth formControlName="loyalty_point_value" placeholder="0.01" step="0.01" min="0" />
                  <span class="form-hint">{{ 'SETTINGS.LOYALTY.POINT_VALUE_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.LOYALTY.MINIMUM_REDEMPTION' | translate }}</label>
                  <input nbInput type="number" fullWidth formControlName="loyalty_minimum_redemption_points" placeholder="100" min="0" />
                  <span class="form-hint">{{ 'SETTINGS.LOYALTY.MINIMUM_REDEMPTION_HINT' | translate }}</span>
                </div>
              </div>
            </nb-tab>

            <!-- Tab 5: Printing -->
            <nb-tab [tabTitle]="'SETTINGS.TABS.PRINTING' | translate">
              <div class="tab-content">
                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.PRINTING.THERMAL_WIDTH' | translate }}</label>
                  <input nbInput type="number" fullWidth formControlName="thermal_printer_width" placeholder="80" min="58" max="120" />
                  <span class="form-hint">{{ 'SETTINGS.PRINTING.THERMAL_WIDTH_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.PRINTING.FOOTER_MESSAGE' | translate }}</label>
                  <textarea nbInput fullWidth formControlName="invoice_footer_message" [placeholder]="'SETTINGS.PRINTING.FOOTER_PLACEHOLDER' | translate" rows="3"></textarea>
                  <span class="form-hint">{{ 'SETTINGS.PRINTING.FOOTER_MESSAGE_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.PRINTING.COST_METHOD' | translate }}</label>
                  <nb-select formControlName="cost_method" fullWidth>
                    <nb-option value="average">{{ 'SETTINGS.PRINTING.COST_METHOD_AVERAGE' | translate }}</nb-option>
                    <nb-option value="FIFO">{{ 'SETTINGS.PRINTING.COST_METHOD_FIFO' | translate }}</nb-option>
                    <nb-option value="LIFO">{{ 'SETTINGS.PRINTING.COST_METHOD_LIFO' | translate }}</nb-option>
                  </nb-select>
                  <span class="form-hint">{{ 'SETTINGS.PRINTING.COST_METHOD_HINT' | translate }}</span>
                </div>
              </div>
            </nb-tab>

            <!-- Tab 6: Payment -->
            <nb-tab [tabTitle]="'SETTINGS.TABS.PAYMENT' | translate">
              <div class="tab-content">
                <div class="form-group form-group-toggle">
                  <div class="toggle-row">
                    <label class="form-label">{{ 'SETTINGS.PAYMENT.NEGATIVE_STOCK' | translate }}</label>
                    <nb-toggle formControlName="allow_negative_stock" status="primary" labelPosition="end"></nb-toggle>
                  </div>
                  <span class="form-hint">{{ 'SETTINGS.PAYMENT.NEGATIVE_STOCK_HINT' | translate }}</span>
                </div>

                <div class="currency-section">
                  <h4>{{ 'SETTINGS.PAYMENT.CURRENCY_SECTION' | translate }}</h4>
                  
                  <div class="form-group form-group-toggle">
                    <div class="toggle-row">
                      <label class="form-label">{{ 'SETTINGS.PAYMENT.CURRENCY_SYP_ENABLED' | translate }}</label>
                      <nb-toggle formControlName="currency_syp_enabled" status="primary" labelPosition="end"></nb-toggle>
                    </div>
                    <span class="form-hint">{{ 'SETTINGS.PAYMENT.CURRENCY_SYP_HINT' | translate }}</span>
                  </div>

                  <div class="form-group form-group-toggle">
                    <div class="toggle-row">
                      <label class="form-label">{{ 'SETTINGS.PAYMENT.CURRENCY_TRY_ENABLED' | translate }}</label>
                      <nb-toggle formControlName="currency_try_enabled" status="primary" labelPosition="end"></nb-toggle>
                    </div>
                    <span class="form-hint">{{ 'SETTINGS.PAYMENT.CURRENCY_TRY_HINT' | translate }}</span>
                  </div>

                  <div class="exchange-rates-info">
                    <div class="rates-header">
                      <span>{{ 'SETTINGS.PAYMENT.EXCHANGE_RATES' | translate }}</span>
                      <button nbButton size="small" status="primary" (click)="refreshExchangeRates()" [disabled]="refreshingRates">
                        {{ (refreshingRates ? 'SETTINGS.PAYMENT.REFRESHING_RATES' : 'SETTINGS.PAYMENT.REFRESH_RATES') | translate }}
                      </button>
                    </div>
                    <div class="rates-content" *ngIf="exchangeRates">
                      <div class="rate-item">
                        <span class="rate-label">USD/SYP:</span>
                        <span class="rate-value">{{ exchangeRates['SYP'] || '-' }}</span>
                      </div>
                      <div class="rate-item">
                        <span class="rate-label">USD/TRY:</span>
                        <span class="rate-value">{{ exchangeRates['TRY'] || '-' }}</span>
                      </div>
                      <div class="rate-updated" *ngIf="lastRatesUpdate">
                        {{ 'SETTINGS.PAYMENT.LAST_UPDATED' | translate }}: {{ lastRatesUpdate | date:'medium' }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-group form-group-toggle">
                  <div class="toggle-row">
                    <label class="form-label">{{ 'SETTINGS.PAYMENT.STRIPE_ENABLED' | translate }}</label>
                    <nb-toggle formControlName="stripe_enabled" status="primary" labelPosition="end"></nb-toggle>
                  </div>
                  <span class="form-hint">{{ 'SETTINGS.PAYMENT.STRIPE_HINT' | translate }}</span>
                </div>

                <div class="form-group">
                  <label class="form-label">{{ 'SETTINGS.PAYMENT.STRIPE_PUB_KEY' | translate }}</label>
                  <input nbInput type="text" fullWidth formControlName="stripe_publishable_key" placeholder="pk_test_..." autocomplete="off" />
                  <span class="form-hint">{{ 'SETTINGS.PAYMENT.STRIPE_PUB_HINT' | translate }}</span>
                </div>

                <div class="stripe-secret-notice">
                  <nb-icon icon="lock-outline"></nb-icon>
                  <span>{{ 'SETTINGS.PAYMENT.STRIPE_SECRET_INFO' | translate }}</span>
                </div>
              </div>
            </nb-tab>
          </nb-tabset>
        </nb-card>

        <div class="settings-footer">
          <button nbButton status="basic" type="button" (click)="resetDefaults()" class="btn-reset">
            {{ 'SETTINGS.RESTORE_DEFAULTS' | translate }}
          </button>
          <button nbButton status="primary" type="submit" [disabled]="saving" class="btn-save">
            {{ (saving ? 'COMMON.SAVING' : 'COMMON.SAVE') | translate }}
          </button>
        </div>
      </form>
    </div>
  `,
})
export class PosSettingsComponent implements OnInit, OnDestroy {
  private readonly destroy$ = new Subject<void>();

  settingsForm!: FormGroup;
  allSettings: StoreSetting[] = [];
  loading = true;
  saving = false;
  errorMessage = '';
  exchangeRates: { [currency: string]: number } = {};
  lastRatesUpdate: Date | null = null;
  refreshingRates = false;

  private readonly defaults: Record<string, unknown> = {
    language: 'ar',
    rtl_enabled: true,
    date_format: 'Y-m-d',
    default_display_currency: 'USD',
    store_name: 'My POS Store',
    store_email: '',
    store_phone: '',
    store_address: '',
    tax_included: false,
    default_tax_rate: 0,
    loyalty_enabled: true,
    loyalty_points_per_currency: 1,
    loyalty_point_value: 0.01,
    loyalty_minimum_redemption_points: 100,
    thermal_printer_width: 80,
    invoice_footer_message: '',
    cost_method: 'average',
    allow_negative_stock: false,
    stripe_enabled: false,
    stripe_publishable_key: '',
    currency_syp_enabled: true,
    currency_try_enabled: true,
  };

  private previousLanguage = 'ar';

  constructor(
    private readonly fb: FormBuilder,
    private readonly settingsService: SettingsService,
    private readonly feedback: OperationFeedbackService,
    private readonly languageService: LanguageService,
    private readonly translate: TranslateService,
    private readonly exchangeRateService: ExchangeRateService,
  ) {}

  ngOnInit(): void {
    this.settingsForm = this.buildForm();
    this.loadSettings();
    this.loadExchangeRates();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  save(): void {
    if (this.settingsForm.invalid) {
      return;
    }

    this.saving = true;
    const formValue = this.settingsForm.getRawValue();

    const payload: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(formValue)) {
      payload[key] = value;
    }

    this.settingsService.bulkUpdate(payload)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.allSettings = response.data;
          this.saving = false;
          this.feedback.success(this.translate.instant('SETTINGS.SAVE_SUCCESS'));

          const newLang = formValue.language as string;
          if (newLang && newLang !== this.previousLanguage) {
            this.languageService.setLanguage(newLang);
            this.previousLanguage = newLang;
          }
        },
        error: (err) => {
          this.saving = false;
          this.feedback.error(err, this.translate.instant('SETTINGS.SAVE_FAILED'));
        },
      });
  }

  resetDefaults(): void {
    this.settingsForm.reset(this.defaults);
  }

  refreshExchangeRates(): void {
    this.refreshingRates = true;
    this.exchangeRateService.refreshRates()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.exchangeRates = response.rates;
          this.lastRatesUpdate = new Date();
          this.refreshingRates = false;
          this.feedback.success(this.translate.instant('SETTINGS.PAYMENT.RATES_REFRESHED'));
        },
        error: () => {
          this.refreshingRates = false;
          this.feedback.error(null, this.translate.instant('SETTINGS.PAYMENT.RATES_REFRESH_FAILED'));
        },
      });
  }

  private loadExchangeRates(): void {
    this.exchangeRateService.getRates()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.exchangeRates = response.rates;
          this.lastRatesUpdate = new Date(response.cached_at);
        },
        error: () => {
          // Use cached rates if available
          this.exchangeRates = this.exchangeRateService.getCurrentRates();
        },
      });
  }

  private loadSettings(): void {
    this.settingsService.list()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.allSettings = response.data;
          this.populateForm();
          this.loading = false;

          const langSetting = this.allSettings.find(s => s.key === 'language');
          if (langSetting) {
            this.previousLanguage = langSetting.value as string;
          }
        },
        error: () => {
          this.loading = false;
          this.errorMessage = this.translate.instant('SETTINGS.LOAD_FAILED');
        },
      });
  }

  private buildForm(): FormGroup {
    return this.fb.group({
      language: ['ar'],
      rtl_enabled: [true],
      date_format: ['Y-m-d'],
      default_display_currency: ['USD'],
      store_name: [''],
      store_email: [''],
      store_phone: [''],
      store_address: [''],
      tax_included: [false],
      default_tax_rate: [0],
      loyalty_enabled: [true],
      loyalty_points_per_currency: [1],
      loyalty_point_value: [0.01],
      loyalty_minimum_redemption_points: [100],
      thermal_printer_width: [80],
      invoice_footer_message: [''],
      cost_method: ['average'],
      allow_negative_stock: [false],
      stripe_enabled: [false],
      stripe_publishable_key: [''],
      currency_syp_enabled: [true],
      currency_try_enabled: [true],
    });
  }

  private populateForm(): void {
    const values: Record<string, unknown> = {};

    for (const setting of this.allSettings) {
      if (setting.key in this.defaults) {
        values[setting.key] = setting.value;
      }
    }

    this.settingsForm.reset(values);
  }
}
