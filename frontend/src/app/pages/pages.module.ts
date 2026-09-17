import { NgModule } from '@angular/core';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { NbMenuModule, NbTabsetModule, NbToggleModule, NbSelectModule } from '@nebular/theme';
import { NgxEchartsModule } from 'ngx-echarts';

import { ThemeModule } from '../@theme/theme.module';
import { PagesComponent } from './pages.component';
import { DashboardModule } from './dashboard/dashboard.module';
import { ECommerceModule } from './e-commerce/e-commerce.module';
import { PagesRoutingModule } from './pages-routing.module';
import { MiscellaneousModule } from './miscellaneous/miscellaneous.module';
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

@NgModule({
  imports: [
    PagesRoutingModule,
    FormsModule,
    ReactiveFormsModule,
    ThemeModule,
    NbMenuModule,
    NbTabsetModule,
    NbToggleModule,
    NbSelectModule,
    DashboardModule,
    ECommerceModule,
    MiscellaneousModule,
    NgxEchartsModule,
  ],
  declarations: [
    PagesComponent,
    PosDashboardComponent,
    PosSalesComponent,
    PosProductsComponent,
    PosInventoryComponent,
    PosStockMovementsComponent,
    PosCustomersComponent,
    PosReportsComponent,
    PosSettingsComponent,
    PosOrderHistoryComponent,
    PosSuppliersComponent,
    PosPurchasesComponent,
    PosUsersComponent,
    PosRolesComponent,
    ForbiddenComponent,
  ],
})
export class PagesModule {
}
