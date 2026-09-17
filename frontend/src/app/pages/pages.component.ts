import { Component, OnDestroy, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

import { MENU_ITEMS, PermissionMenuItem } from './pages-menu';
import { AuthService } from '../auth.service';

@Component({
  selector: 'ngx-pages',
  styleUrls: ['pages.component.scss'],
  template: `
    <ngx-one-column-layout>
      <nb-menu [items]="menu"></nb-menu>
      <router-outlet></router-outlet>
    </ngx-one-column-layout>
  `,
})
export class PagesComponent implements OnInit, OnDestroy {
  private readonly destroy$ = new Subject<void>();
  menu: PermissionMenuItem[] = [];

  constructor(
    private readonly authService: AuthService,
    private readonly translate: TranslateService,
  ) {}

  ngOnInit(): void {
    this.buildMenu();

    this.translate.onLangChange
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => this.buildMenu());
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private buildMenu(): void {
    this.menu = MENU_ITEMS
      .filter(item => !item.permission || this.authService.hasPermission(item.permission))
      .map(item => ({
        ...item,
        title: item.translationKey ? this.translate.instant(item.translationKey) : item.title,
      }));
  }
}
