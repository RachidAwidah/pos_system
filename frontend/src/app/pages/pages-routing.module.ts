import { RouterModule, Routes } from '@angular/router';
import { NgModule } from '@angular/core';

import { PagesComponent } from './pages.component';
import { PosDashboardComponent } from './pos-dashboard.component';
import { PosSalesComponent } from './pos-sales.component';
import { PosProductsComponent } from './pos-products.component';
import { PosInventoryComponent } from './pos-inventory.component';
import { PosStockMovementsComponent } from './pos-stock-movements/pos-stock-movements.component';
import { PosCustomersComponent } from './pos-customers.component';
import { PosReportsComponent } from './pos-reports.component';
import { PosSettingsComponent } from './pos-settings.component';
import { PosOrderHistoryComponent } from './pos-order-history.component';
import { PosSuppliersComponent } from './pos-suppliers.component';
import { PosPurchasesComponent } from './pos-purchases.component';
import { PosUsersComponent } from './pos-users.component';
import { PosRolesComponent } from './pos-roles.component';
import { ForbiddenComponent } from './forbidden.component';
import { NotFoundComponent } from './miscellaneous/not-found/not-found.component';
import { PermissionGuard } from '../permission.guard';

const routes: Routes = [{
  path: '',
  component: PagesComponent,
  children: [
    { path: 'dashboard', component: PosDashboardComponent, canActivate: [PermissionGuard], data: { permission: 'reports.view_financial' } },
    { path: 'sales', component: PosSalesComponent, canActivate: [PermissionGuard], data: { permission: 'sales.create' } },
    { path: 'orders', component: PosOrderHistoryComponent, canActivate: [PermissionGuard], data: { permission: 'sales.view' } },
    { path: 'suppliers', component: PosSuppliersComponent, canActivate: [PermissionGuard], data: { permission: 'suppliers.view' } },
    { path: 'purchases', component: PosPurchasesComponent, canActivate: [PermissionGuard], data: { permission: 'purchases.view' } },
    { path: 'users', component: PosUsersComponent, canActivate: [PermissionGuard], data: { permission: 'users.view' } },
    { path: 'roles', component: PosRolesComponent, canActivate: [PermissionGuard], data: { permission: 'roles.view' } },
    { path: 'products', component: PosProductsComponent, canActivate: [PermissionGuard], data: { permission: 'products.view' } },
    { path: 'inventory', component: PosInventoryComponent, canActivate: [PermissionGuard], data: { permission: 'inventory.view' } },
    { path: 'stock-movements', component: PosStockMovementsComponent, canActivate: [PermissionGuard], data: { permission: 'inventory.view' } },
    { path: 'customers', component: PosCustomersComponent, canActivate: [PermissionGuard], data: { permission: 'customers.view' } },
    { path: 'reports', component: PosReportsComponent, canActivate: [PermissionGuard], data: { permission: 'reports.view_financial' } },
    { path: 'settings', component: PosSettingsComponent, canActivate: [PermissionGuard], data: { permission: 'settings.view' } },
    { path: '403', component: ForbiddenComponent },
    { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
    { path: '**', component: NotFoundComponent },
  ],
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PagesRoutingModule {
}
