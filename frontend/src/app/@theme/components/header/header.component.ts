import { Component, OnDestroy, OnInit } from '@angular/core';
import { NbMediaBreakpointsService, NbMenuService, NbSidebarService, NbThemeService } from '@nebular/theme';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

import { LayoutService } from '../../../@core/utils';
import { filter, finalize, map, takeUntil } from 'rxjs/operators';
import { merge, Subject } from 'rxjs';
import { AuthService, AuthUser } from '../../../auth.service';
import { SettingsService } from '../../../services/settings.service';
import { OperationFeedbackService } from '../../../services/operation-feedback.service';
import { CurrencyService, CurrencyInfo } from '../../../services/currency.service';

@Component({
  selector: 'ngx-header',
  styleUrls: ['./header.component.scss'],
  templateUrl: './header.component.html',
})
export class HeaderComponent implements OnInit, OnDestroy {

  private readonly destroy$ = new Subject<void>();
  private logoutPending = false;
  userPictureOnly = false;
  user: AuthUser | null = null;
  storeName = '';
  currentCurrency = 'USD';
  supportedCurrencies: CurrencyInfo[] = [];

  userMenu = [{ title: '', action: 'logout', translationKey: 'HEADER.LOGOUT' }];

  constructor(private sidebarService: NbSidebarService,
              private menuService: NbMenuService,
              private themeService: NbThemeService,
              private layoutService: LayoutService,
              private breakpointService: NbMediaBreakpointsService,
              private authService: AuthService,
              private settingsService: SettingsService,
              private router: Router,
              private translate: TranslateService,
              private feedback: OperationFeedbackService,
              private currencyService: CurrencyService) {
  }

  ngOnInit() {
    this.authService.user$.pipe(takeUntil(this.destroy$)).subscribe(user => this.user = user);

    merge(
      this.settingsService.list().pipe(map(response => response.data)),
      this.settingsService.updated$,
    )
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (settings) => {
          this.storeName = (settings.find(s => s.key === 'store_name')?.value as string) || '';
          const sypEnabled = settings.find(s => s.key === 'currency_syp_enabled');
          const tryEnabled = settings.find(s => s.key === 'currency_try_enabled');
          const defaultCurrency = settings.find(s => s.key === 'default_display_currency');

          const enabledCodes = ['USD']; // USD always shown
          if (sypEnabled?.value === 'true' || sypEnabled?.value === true) {
            enabledCodes.push('SYP');
          }
          if (tryEnabled?.value === 'true' || tryEnabled?.value === true) {
            enabledCodes.push('TRY');
          }

          this.supportedCurrencies = this.currencyService.getSupportedCurrencies()
            .filter(c => enabledCodes.includes(c.code));

          this.currencyService.applyDisplaySettings(defaultCurrency?.value as string, enabledCodes);
        },
        error: () => {
          this.supportedCurrencies = this.currencyService.getSupportedCurrencies();
        },
      });

    this.currencyService.currentCurrency$
      .pipe(takeUntil(this.destroy$))
      .subscribe(currency => this.currentCurrency = currency);

    this.updateMenuTitles();

    this.translate.onLangChange
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => this.updateMenuTitles());

    this.menuService.onItemClick().pipe(
      filter(event => event.tag === 'user-menu' && (event.item as any).action === 'logout'),
      takeUntil(this.destroy$),
    ).subscribe(() => this.logout());

    const { xl } = this.breakpointService.getBreakpointsMap();
    this.themeService.onMediaQueryChange()
      .pipe(
        map(([, currentBreakpoint]) => currentBreakpoint.width < xl),
        takeUntil(this.destroy$),
      )
      .subscribe((isLessThanXl: boolean) => this.userPictureOnly = isLessThanXl);
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  toggleSidebar(): boolean {
    this.sidebarService.toggle(true, 'menu-sidebar');
    this.layoutService.changeLayoutSize();

    return false;
  }

  navigateHome() {
    this.menuService.navigateHome();
    return false;
  }

  private updateMenuTitles(): void {
    this.userMenu = this.userMenu.map(item => ({
      ...item,
      title: this.translate.instant(item.translationKey),
    }));
  }

  onCurrencyChange(currencyCode: string): void {
    this.currencyService.setCurrency(currencyCode);
  }

  private logout(): void {
    if (this.logoutPending) {
      return;
    }
    this.logoutPending = true;
    this.authService.logout().pipe(
      finalize(() => this.logoutPending = false),
    ).subscribe({
      next: () => this.router.navigate(['/auth/login']),
      error: (error) => {
        const message = this.translate.instant(error.status === 401
          ? 'HEADER.LOGOUT_SESSION_EXPIRED' : 'HEADER.LOGOUT_FAILED');
        this.feedback.error(error.status === 401 ? null : error, message);
      },
    });
  }
}
