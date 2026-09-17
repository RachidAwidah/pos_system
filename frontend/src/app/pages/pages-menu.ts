import { NbMenuItem } from '@nebular/theme';

export interface PermissionMenuItem extends NbMenuItem {
  permission?: string;
  translationKey?: string;
}

export const MENU_ITEMS: PermissionMenuItem[] = [
  {
    translationKey: 'MENU.DASHBOARD',
    title: '',
    icon: 'home-outline',
    link: '/pages/dashboard',
    home: true,
    permission: 'reports.view_financial',
  },
  {
    translationKey: 'MENU.POS',
    title: '',
    icon: 'shopping-cart-outline',
    link: '/pages/sales',
    permission: 'sales.create',
  },
  {
    translationKey: 'MENU.PRODUCTS',
    title: '',
    icon: 'cube-outline',
    link: '/pages/products',
    permission: 'products.view',
  },
  {
    translationKey: 'MENU.ORDERS',
    title: '',
    icon: 'file-text-outline',
    link: '/pages/orders',
    permission: 'sales.view',
  },
  {
    translationKey: 'MENU.INVENTORY',
    title: '',
    icon: 'archive-outline',
    link: '/pages/inventory',
    permission: 'inventory.view',
  },
  {
    translationKey: 'MENU.STOCK_MOVEMENTS',
    title: '',
    icon: 'swap-outline',
    link: '/pages/stock-movements',
    permission: 'inventory.view',
  },
  {
    translationKey: 'MENU.CUSTOMERS',
    title: '',
    icon: 'people-outline',
    link: '/pages/customers',
    permission: 'customers.view',
  },
  {
    translationKey: 'MENU.SUPPLIERS',
    title: '',
    icon: 'briefcase-outline',
    link: '/pages/suppliers',
    permission: 'suppliers.view',
  },
  {
    translationKey: 'MENU.PURCHASES',
    title: '',
    icon: 'shopping-bag-outline',
    link: '/pages/purchases',
    permission: 'purchases.view',
  },
  {
    translationKey: 'MENU.REPORTS',
    title: '',
    icon: 'pie-chart-outline',
    link: '/pages/reports',
    permission: 'reports.view_financial',
  },
  {
    translationKey: 'MENU.SETTINGS',
    title: '',
    icon: 'settings-outline',
    link: '/pages/settings',
    permission: 'settings.view',
  },
  {
    translationKey: 'MENU.USERS',
    title: '',
    icon: 'person-done-outline',
    link: '/pages/users',
    permission: 'users.view',
  },
  {
    translationKey: 'MENU.ROLES',
    title: '',
    icon: 'shield-outline',
    link: '/pages/roles',
    permission: 'roles.view',
  },
];
