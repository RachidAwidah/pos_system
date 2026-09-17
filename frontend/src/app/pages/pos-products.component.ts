import { Component, ChangeDetectorRef, TemplateRef, ViewChild, OnDestroy } from '@angular/core';
import { NbDialogService, NbDialogRef } from '@nebular/theme';
import { DetailsDialogComponent } from '../@theme/components/details-modal/details-dialog.component';
import { TranslateService } from '@ngx-translate/core';
import { Category, CategoryService } from '../services/category.service';
import { Product, ProductListResponse, ProductReferenceData, ProductService } from '../services/product.service';
import { downloadExcel } from '../shared/export.util';
import { OperationFeedbackService } from '../services/operation-feedback.service';
import { CurrencyService } from '../services/currency.service';

@Component({
  selector: 'ngx-pos-products',
  styleUrls: ['./pos-products.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header">
        <div>
          <span class="eyebrow">{{ translate.instant('PRODUCTS.TITLE') }}</span>
          <h1>{{ translate.instant('PRODUCTS.SUBTITLE') }}</h1>
          <p class="summary" *ngIf="totalProducts">{{ translate.instant('PRODUCTS.TOTAL') }} {{ totalProducts }}</p>
        </div>
        <button nbButton status="primary" [disabled]="savingProduct || loadingProduct" (click)="startCreateProduct()">{{ translate.instant('PRODUCTS.ADD_NEW') }}</button>
      </div>

      <ng-template #productFormTemplate>
          <div class="create-form">
            <div class="product-field">
              <label for="product-product_name">{{ translate.instant('PRODUCTS.FORM_NAME') }}</label>
              <input id="product-product_name" nbInput placeholder="{{ translate.instant('PRODUCTS.FORM_NAME') }}" [(ngModel)]="newProduct.product_name" />
            </div>
            <div class="product-field">
              <label for="product-sku">{{ translate.instant('PRODUCTS.FORM_SKU') }}</label>
              <input aria-describedby="product-sku-hint" id="product-sku" nbInput placeholder="SKU *" [(ngModel)]="newProduct.sku" />
              <small id="product-sku-hint">{{ translate.instant('PRODUCTS.FORM_SKU_HINT') }}</small>
            </div>
            <div class="product-field">
              <label for="product-barcode">{{ translate.instant('PRODUCTS.FORM_BARCODE') }}</label>
              <input id="product-barcode" nbInput placeholder="{{ translate.instant('PRODUCTS.FORM_BARCODE') }}" [(ngModel)]="newProduct.barcode" />
            </div>
            <div class="product-field">
              <label for="product-type">{{ translate.instant('PRODUCTS.FORM_TYPE') }}</label>
              <select id="product-type" nbInput [(ngModel)]="newProduct.type" [disabled]="!!editingProduct">
              <option value="stock">{{ translate.instant('PRODUCTS.FORM_TYPE_STOCKED') }}</option><option value="non_stock">{{ translate.instant('PRODUCTS.FORM_TYPE_UNSTOCKED') }}</option><option value="service">{{ translate.instant('PRODUCTS.FORM_TYPE_SERVICE') }}</option>
            </select>
            </div>
            <div class="product-field">
              <label for="product-category_id">{{ translate.instant('PRODUCTS.FORM_CATEGORY') }}</label>
              <select id="product-category_id" nbInput [(ngModel)]="newProduct.category_id"><option value="">{{ translate.instant('PRODUCTS.FORM_CATEGORY') }}</option><option *ngFor="let category of reference.categories" [value]="category.id">{{ category.path || category.category_name }}</option></select>
            </div>
            <div class="product-field">
              <label for="product-unit_id">{{ translate.instant('PRODUCTS.FORM_UNIT') }}</label>
              <select id="product-unit_id" nbInput [(ngModel)]="newProduct.unit_id"><option value="">{{ translate.instant('PRODUCTS.FORM_UNIT') }}</option><option *ngFor="let unit of reference.units" [value]="unit.id">{{ unit.name }} ({{ unit.symbol }})</option></select>
            </div>
            <div class="product-field">
              <label for="product-tax_id">{{ translate.instant('PRODUCTS.FORM_TAX') }}</label>
              <select id="product-tax_id" nbInput [(ngModel)]="newProduct.tax_id"><option value="">{{ translate.instant('PRODUCTS.FORM_NO_TAX') }}</option><option *ngFor="let tax of reference.taxes" [value]="tax.id">{{ tax.tax_name }} ({{ tax.tax_percentage }}%)</option></select>
            </div>
            <div class="product-field" *ngIf="!editingProduct || editingProduct.cost_price !== undefined">
              <label for="product-cost_price">{{ translate.instant('PRODUCTS.FORM_COST') }}</label>
              <input aria-describedby="product-cost_price-hint" id="product-cost_price" nbInput type="number" min="0" placeholder="{{ translate.instant('PRODUCTS.FORM_COST') }}" [(ngModel)]="newProduct.cost_price" />
              <small id="product-cost_price-hint">{{ translate.instant('PRODUCTS.FORM_COST_HINT') }}</small>
            </div>
            <div class="product-field">
              <label for="product-price">{{ translate.instant('PRODUCTS.FORM_SELL_PRICE') }}</label>
              <input aria-describedby="product-price-hint" id="product-price" nbInput type="number" min="0" placeholder="{{ translate.instant('PRODUCTS.FORM_SELL_PRICE') }}" [(ngModel)]="newProduct.price" />
              <small id="product-price-hint">{{ translate.instant('PRODUCTS.FORM_PRICE_HINT') }}</small>
            </div>
            <div class="product-field" *ngIf="!editingProduct && newProduct.type === 'stock'">
              <label for="product-warehouse_id">{{ translate.instant('PRODUCTS.FORM_WAREHOUSE') }}</label>
              <select id="product-warehouse_id" nbInput [(ngModel)]="newProduct.warehouse_id"><option value="">{{ translate.instant('PRODUCTS.FORM_WAREHOUSE') }}</option><option *ngFor="let warehouse of reference.warehouses" [value]="warehouse.id">{{ warehouse.name }}</option></select>
            </div>
            <div class="product-field" *ngIf="!editingProduct && newProduct.type === 'stock'">
              <label for="product-opening_quantity">{{ translate.instant('PRODUCTS.FORM_OPENING_QTY') }}</label>
              <input id="product-opening_quantity" aria-describedby="product-opening_quantity-hint" nbInput type="number" min="0" placeholder="{{ translate.instant('PRODUCTS.FORM_OPENING_QTY') }}" [(ngModel)]="newProduct.opening_quantity" />
              <small id="product-opening_quantity-hint">{{ translate.instant('PRODUCTS.FORM_OPENING_HINT') }}</small>
            </div>
            <div class="product-field" *ngIf="!editingProduct && newProduct.type === 'stock'">
              <label for="product-reorder_level">{{ translate.instant('PRODUCTS.FORM_REORDER') }}</label>
              <input id="product-reorder_level" aria-describedby="product-reorder_level-hint" nbInput type="number" min="0" placeholder="{{ translate.instant('PRODUCTS.FORM_REORDER') }}" [(ngModel)]="newProduct.reorder_level" />
              <small id="product-reorder_level-hint">{{ translate.instant('PRODUCTS.FORM_REORDER_HINT') }}</small>
            </div>
            <div class="product-field product-field--wide">
              <label for="product-description">{{ translate.instant('PRODUCTS.FORM_DESCRIPTION') }}</label>
              <textarea id="product-description" nbInput placeholder="{{ translate.instant('PRODUCTS.FORM_DESCRIPTION') }}" [(ngModel)]="newProduct.description"></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button nbButton status="primary" (click)="createProduct()" [disabled]="savingProduct">{{ translate.instant('PRODUCTS.FORM_SAVE') }}</button>
            <button nbButton status="basic" [disabled]="savingProduct" (click)="closeProductForm()">{{ translate.instant('PRODUCTS.FORM_CANCEL') }}</button>
          </div>
          <div *ngIf="createError" class="state error">{{ createError }}</div>
      </ng-template>

      <nb-card>
        <nb-card-header>{{ translate.instant('PRODUCTS.CAT_ADD_BRANCH') }}</nb-card-header>
        <nb-card-body>
          <div class="category-create-form">
            <input nbInput placeholder="{{ translate.instant('PRODUCTS.CAT_NEW_NAME') }}" [(ngModel)]="newCategoryName" />
            <select nbInput [(ngModel)]="newCategoryParentId">
              <option [ngValue]="null">{{ translate.instant('PRODUCTS.CAT_PARENT') }}</option>
              <option *ngFor="let category of flatCategories" [ngValue]="category.id">{{ category.path || category.category_name }}</option>
            </select>
            <button nbButton status="basic" [disabled]="categorySaving" (click)="createCategory()">{{ translate.instant('PRODUCTS.CAT_ADD') }}</button>
          </div>
          <div *ngIf="categoryError" class="state error">{{ categoryError }}</div>
        </nb-card-body>
      </nb-card>

      <nb-card *ngIf="editingCategory">
        <nb-card-header>{{ translate.instant('PRODUCTS.CAT_EDIT_TITLE') }} {{ editingCategory.category_name }}</nb-card-header>
        <nb-card-body>
          <div class="category-create-form">
            <input nbInput placeholder="{{ translate.instant('PRODUCTS.CAT_NAME') }}" [(ngModel)]="editingCategoryName" />
            <select nbInput [(ngModel)]="editingCategoryParentId">
              <option [ngValue]="null">{{ translate.instant('PRODUCTS.CAT_PARENT') }}</option>
              <option *ngFor="let category of editableParentCategories" [ngValue]="category.id">{{ category.path || category.category_name }}</option>
            </select>
            <button nbButton status="primary" [disabled]="categorySaving" (click)="updateCategory()">{{ translate.instant('PRODUCTS.CAT_SAVE') }}</button>
          </div>
          <div *ngIf="categoryError" class="state error">{{ categoryError }}</div>
        </nb-card-body>
      </nb-card>

      <div class="catalog-layout">
        <nb-card class="category-card">
          <nb-card-body>
            <div class="category-heading">
              <div>
                <strong>{{ translate.instant('PRODUCTS.CAT_TREE') }}</strong>
                <p>{{ translate.instant('PRODUCTS.CAT_HINT') }}</p>
              </div>
            </div>
            <button nbButton fullWidth size="small" [status]="selectedCategoryId ? 'basic' : 'primary'" (click)="selectCategory(null)">
              {{ translate.instant('PRODUCTS.CAT_ALL') }} <span class="count">{{ totalProducts }}</span>
            </button>
            <div *ngIf="categoriesLoading" class="state compact">{{ translate.instant('PRODUCTS.CAT_LOADING') }}</div>
            <div *ngIf="categoriesError" class="state error compact">{{ categoriesError }}</div>
            <ul class="category-tree" *ngIf="!categoriesLoading && !categoriesError">
              <ng-container *ngTemplateOutlet="categoryNodes; context: { $implicit: categories }"></ng-container>
            </ul>
          </nb-card-body>
        </nb-card>

        <section class="products-column">
          <nb-card>
            <nb-card-body class="search-bar">
              <input
                nbInput
                type="search"
                [(ngModel)]="search"
                (keyup.enter)="loadProducts(1)"
                placeholder="{{ translate.instant('PRODUCTS.SEARCH_PLACEHOLDER') }}"
                class="search-input"
              />
              <button nbButton status="primary" (click)="loadProducts(1)">{{ translate.instant('PRODUCTS.SEARCH_BTN') }}</button>
              <button nbButton status="basic" (click)="search = ''; loadProducts(1)">{{ translate.instant('PRODUCTS.CLEAR_SEARCH') }}</button>
              <button nbButton status="basic" (click)="exportProducts()">تصدير Excel</button>
            </nb-card-body>
          </nb-card>

          <nb-card>
            <nb-card-body class="filters">
              <label class="low-stock-filter">
                <input type="checkbox" [(ngModel)]="lowStock" (change)="loadProducts(1)" />
                {{ translate.instant('PRODUCTS.LOW_STOCK_ONLY') }}
              </label>
              <select nbInput [(ngModel)]="productType" (change)="loadProducts(1)">
                <option value="">{{ translate.instant('PRODUCTS.TYPE_ALL') }}</option>
                <option value="stock">{{ translate.instant('PRODUCTS.TYPE_STOCKED') }}</option>
                <option value="non_stock">{{ translate.instant('PRODUCTS.TYPE_UNSTOCKED') }}</option>
                <option value="service">{{ translate.instant('PRODUCTS.TYPE_SERVICES') }}</option>
              </select>
              <select nbInput [(ngModel)]="perPage" (change)="loadProducts(1)">
                <option [ngValue]="10">{{ translate.instant('PRODUCTS.PER_PAGE_10') }}</option>
                <option [ngValue]="25">{{ translate.instant('PRODUCTS.PER_PAGE_25') }}</option>
                <option [ngValue]="50">{{ translate.instant('PRODUCTS.PER_PAGE_50') }}</option>
              </select>
            </nb-card-body>
          </nb-card>

          <nb-card>
            <nb-card-body>
              <div *ngIf="loading" class="state">{{ translate.instant('PRODUCTS.LOADING') }}</div>
              <div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
              <div *ngIf="!loading && !errorMessage && products.length === 0" class="state">{{ translate.instant('PRODUCTS.NO_MATCH') }}</div>
              <table *ngIf="!loading && !errorMessage && products.length" class="data-table">
            <thead>
              <tr>
                <th>{{ translate.instant('PRODUCTS.COL_NAME') }}</th>
                <th>SKU</th>
                <th>{{ translate.instant('PRODUCTS.COL_CATEGORY') }}</th>
                <th>{{ translate.instant('PRODUCTS.COL_PRICE') }}</th>
                <th>{{ translate.instant('PRODUCTS.COL_STOCK') }}</th>
                <th>{{ translate.instant('PRODUCTS.COL_STATUS') }}</th>
                <th>{{ translate.instant('PRODUCTS.COL_ACTIONS') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let product of products">
                <td>{{ product.product_name }}</td>
                <td>{{ product.sku }}</td>
                <td>{{ product.category?.category_name || translate.instant('PRODUCTS.NO_CATEGORY') }}</td>
                <td>{{ product.price | appCurrency }} <span class="unit">{{ product.unit?.symbol || '' }}</span></td>
                <td>{{ product.inventory?.quantity_available ?? '—' }}</td>
                <td>
                  <span class="status" [class.warning]="isLowStock(product)" [class.success]="!isLowStock(product)">
                    {{ isLowStock(product) ? translate.instant('PRODUCTS.STATUS_LOW') : translate.instant('PRODUCTS.STATUS_IN_STOCK') }}
                  </span>
                </td>
                <td>
                  <div class="action-buttons">
                    <button nbButton size="tiny" status="basic" [disabled]="savingProduct || loadingProduct || deletingProductIds.has(product.id)" (click)="startEditProduct(product)">{{ translate.instant('PRODUCTS.EDIT') }}</button>
                    <button nbButton size="tiny" status="danger" [disabled]="savingProduct || loadingProduct || deletingProductIds.has(product.id)" (click)="deleteProduct(product)">{{ translate.instant(deletingProductIds.has(product.id) ? 'PRODUCTS.CAT_DELETING' : 'PRODUCTS.DELETE') }}</button>
                  </div>
                  <div *ngIf="productDeleteErrors[product.id]" class="delete-error" role="alert">{{ productDeleteErrors[product.id] }}</div>
                </td>
              </tr>
            </tbody>
              </table>
              <div *ngIf="!loading && !errorMessage && totalPages > 1" class="pagination">
                <button nbButton size="small" status="basic" [disabled]="page === 1" (click)="loadProducts(page - 1)">{{ translate.instant('COMMON.PREVIOUS') }}</button>
                <button *ngFor="let pageNumber of pageNumbers" nbButton size="small" [status]="pageNumber === page ? 'primary' : 'basic'" (click)="loadProducts(pageNumber)">{{ pageNumber }}</button>
                <span>صفحة {{ page }} من {{ totalPages }}</span>
                <button nbButton size="small" status="basic" [disabled]="page === totalPages" (click)="loadProducts(page + 1)">{{ translate.instant('COMMON.NEXT') }}</button>
              </div>
            </nb-card-body>
          </nb-card>
        </section>
      </div>

      <ng-template #categoryNodes let-nodes>
        <li *ngFor="let category of nodes">
          <div class="category-row" [class.selected]="selectedCategoryId === category.id">
            <button
              *ngIf="category.children?.length"
              type="button"
              class="toggle"
              (click)="toggleCategory(category.id)"
              [attr.aria-label]="isCategoryExpanded(category.id) ? translate.instant('PRODUCTS.CAT_CLOSE') + ' ' + category.category_name : translate.instant('PRODUCTS.CAT_OPEN') + ' ' + category.category_name"
            >
              {{ isCategoryExpanded(category.id) ? '⌄' : '‹' }}
            </button>
            <span *ngIf="!category.children?.length" class="toggle-placeholder">•</span>
            <button type="button" class="category-link" (click)="selectCategory(category.id)">
              <span>{{ category.category_name }}</span>
              <span class="count">{{ category.tree_products_count || 0 }}</span>
            </button>
            <button type="button" class="category-action" (click)="startEditCategory(category)" [attr.aria-label]="translate.instant('PRODUCTS.CAT_EDIT')">{{ translate.instant('PRODUCTS.EDIT') }}</button>
            <button type="button" class="category-action danger" [disabled]="deletingCategoryIds.has(category.id)" (click)="deleteCategory(category)" [attr.aria-label]="translate.instant('PRODUCTS.CAT_DELETE')">{{ deletingCategoryIds.has(category.id) ? translate.instant('PRODUCTS.CAT_DELETING') : translate.instant('PRODUCTS.DELETE') }}</button>
          </div>
          <ul *ngIf="category.children?.length && isCategoryExpanded(category.id)" class="category-children">
            <ng-container *ngTemplateOutlet="categoryNodes; context: { $implicit: category.children }"></ng-container>
          </ul>
        </li>
      </ng-template>
    </div>
  `,
})
export class PosProductsComponent implements OnDestroy {
  @ViewChild('productFormTemplate', { static: true }) productFormTemplate!: TemplateRef<unknown>;
  private productDialog: NbDialogRef<DetailsDialogComponent> | null = null;
  products: Product[] = [];
  search = '';
  lowStock = false;
  productType = '';
  loading = false;
  errorMessage = '';
  page = 1;
  totalPages = 1;
  totalProducts = 0;
  categories: Category[] = [];
  selectedCategoryId: string | null = null;
  expandedCategoryIds = new Set<string>();
  flatCategories: Category[] = [];
  categoriesLoading = false;
  categoriesError = '';
  newCategoryName = '';
  newCategoryParentId: string | null = null;
  categorySaving = false;
  categoryError = '';
  editingCategory: Category | null = null;
  editingCategoryName = '';
  editingCategoryParentId: string | null = null;
  showCreateForm = false;
  savingProduct = false;
  loadingProduct = false;
  editingProduct: Product | null = null;
  deletingProductIds = new Set<string>();
  productDeleteErrors: Record<string, string> = {};
  createError = '';
  deletingCategoryIds = new Set<string>();
  reference: ProductReferenceData = { categories: [], units: [], taxes: [], warehouses: [] };
  newProduct: Record<string, string | number | null> = {
    product_name: '', sku: '', barcode: null, type: 'stock', category_id: '', unit_id: '', tax_id: null,
    cost_price: 0, price: 0, warehouse_id: '', opening_quantity: 0, reorder_level: 0,
  };
  perPage = 25;

  constructor(
    private readonly productService: ProductService,
    private readonly categoryService: CategoryService,
    private readonly feedback: OperationFeedbackService,
    readonly translate: TranslateService,
    private readonly currencyService: CurrencyService,
    private readonly cdr: ChangeDetectorRef,
    private readonly dialogService: NbDialogService,
  ) {
    this.currencyService.currentCurrency$.subscribe(() => this.cdr.detectChanges());
    this.loadCategories();
    this.loadProducts();
    this.loadReferenceData();
  }

  loadReferenceData(): void {
    this.productService.referenceData().subscribe({
      next: response => this.reference = response.data,
      error: () => this.createError = this.translate.instant('PRODUCTS.ERR_LOAD_REF_DATA'),
    });
  }

  createProduct(): void {
    if (this.savingProduct) return;
    this.savingProduct = true;
    if (this.productDialog) this.productDialog.componentRef.instance.closeDisabled = true;
    this.createError = '';
    const payload = { ...this.newProduct };
    if (this.editingProduct || payload.type !== 'stock') {
      delete payload.warehouse_id;
      delete payload.opening_quantity;
      delete payload.reorder_level;
    }
    payload.tax_id = payload.tax_id || null;
    payload.barcode = payload.barcode || null;
    const editing = this.editingProduct;
    if (editing) {
      delete payload.type;
      if (editing.cost_price === undefined || Number(payload.cost_price) === Number(editing.cost_price)) delete payload.cost_price;
      if (Number(payload.price) === Number(editing.price)) delete payload.price;
    }
    const request = editing ? this.productService.update(editing.id, payload) : this.productService.create(payload);
    request.subscribe({
      next: () => {
        this.savingProduct = false;
        this.productDialog?.close();
        this.productDialog = null;
        this.showCreateForm = false;
        this.newProduct = { product_name: '', sku: '', barcode: null, type: 'stock', category_id: '', unit_id: '', tax_id: null, cost_price: 0, price: 0, warehouse_id: '', opening_quantity: 0, reorder_level: 0 };
        this.editingProduct = null;
        this.feedback.success(editing ? this.translate.instant('PRODUCTS.MSG_UPDATED') : this.translate.instant('PRODUCTS.MSG_CREATED'));
        this.loadProducts(editing ? this.page : 1);
      },
      error: error => {
        this.savingProduct = false;
        if (this.productDialog) this.productDialog.componentRef.instance.closeDisabled = false;
        this.createError = this.feedback.error(error, this.translate.instant('PRODUCTS.ERR_SAVE'));
      },
    });
  }

  startCreateProduct(): void {
    if (this.savingProduct || this.loadingProduct) return;
    this.editingProduct = null;
    this.newProduct = { product_name: '', sku: '', barcode: null, type: 'stock', category_id: '', unit_id: '', tax_id: null, cost_price: 0, price: 0, warehouse_id: '', opening_quantity: 0, reorder_level: 0, description: '' };
    this.createError = '';
    this.showCreateForm = true;
    this.productDialog = this.dialogService.open(DetailsDialogComponent, {
      context: { title: this.translate.instant('PRODUCTS.ADD_TITLE'), contentTemplate: this.productFormTemplate, showFooter: false },
      closeOnBackdropClick: false,
      closeOnEsc: false,
    });
    this.productDialog.onClose.subscribe(() => {
      this.productDialog = null;
      this.showCreateForm = false;
      this.editingProduct = null;
    });
  }

  startEditProduct(product: Product): void {
    if (this.savingProduct || this.loadingProduct) return;
    this.loadingProduct = true;
    this.productService.show(product.id).subscribe({
      next: response => {
        const item = response.data;
        this.editingProduct = item;
        this.newProduct = { product_name: item.product_name, sku: item.sku, barcode: item.barcode, type: item.type, category_id: item.category?.id || '', unit_id: item.unit?.id || '', tax_id: item.tax?.id || null, cost_price: item.cost_price ?? null, price: item.price, description: item.description || '' };
        this.createError = '';
        this.showCreateForm = true;
        this.loadingProduct = false;
        this.productDialog = this.dialogService.open(DetailsDialogComponent, {
          context: { title: this.translate.instant('PRODUCTS.EDIT_TITLE'), contentTemplate: this.productFormTemplate, showFooter: false },
          closeOnBackdropClick: false,
          closeOnEsc: false,
        });
        this.productDialog.onClose.subscribe(() => {
          this.productDialog = null;
          this.showCreateForm = false;
          this.editingProduct = null;
        });
      },
      error: error => { this.loadingProduct = false; this.feedback.error(error, this.translate.instant('PRODUCTS.ERR_LOAD_PRODUCT')); },
    });
  }

  deleteProduct(product: Product): void {
    if (this.deletingProductIds.has(product.id) || !confirm(this.translate.instant('PRODUCTS.DELETE_CONFIRM', { name: product.product_name }))) return;
    delete this.productDeleteErrors[product.id];
    this.deletingProductIds.add(product.id);
    this.productService.delete(product.id).subscribe({
      next: () => {
        this.deletingProductIds.delete(product.id);
        if (this.editingProduct?.id === product.id) { this.editingProduct = null; this.showCreateForm = false; }
        this.feedback.success(this.translate.instant('PRODUCTS.DELETE_SUCCESS'));
        this.loadProducts(this.products.length === 1 ? Math.max(1, this.page - 1) : this.page);
      },
      error: error => {
        this.deletingProductIds.delete(product.id);
        this.productDeleteErrors[product.id] = this.feedback.error(error, this.translate.instant('PRODUCTS.DELETE_FAILED'));
      },
    });
  }

  closeProductForm(): void {
    if (this.savingProduct) return;
    this.productDialog?.close();
    this.productDialog = null;
    this.showCreateForm = false;
    this.editingProduct = null;
  }

  ngOnDestroy(): void {
    this.productDialog?.close();
  }

  loadCategories(): void {
    this.categoriesLoading = true;
    this.categoryService.list().subscribe({
      next: (response) => {
        this.categories = response.data;
        this.flatCategories = this.flattenCategories(this.categories);
        this.categories.forEach(category => this.expandedCategoryIds.add(category.id));
        this.categoriesLoading = false;
      },
      error: () => {
        this.categoriesLoading = false;
        this.categoriesError = this.translate.instant('PRODUCTS.ERR_LOAD_CATEGORIES');
      },
    });
  }

  createCategory(): void {
    if (this.categorySaving) return;
    const categoryName = this.newCategoryName.trim();
    if (!categoryName) {
      this.categoryError = this.translate.instant('PRODUCTS.ERR_ENTER_CAT_NAME');
      return;
    }
    this.categorySaving = true;
    this.categoryError = '';
    this.categoryService.create({ category_name: categoryName, parent_id: this.newCategoryParentId }).subscribe({
      next: () => {
        this.categorySaving = false;
        this.newCategoryName = '';
        this.newCategoryParentId = null;
        this.feedback.success(this.translate.instant('PRODUCTS.MSG_CAT_CREATED'));
        this.loadCategories();
        this.loadReferenceData();
      },
      error: error => {
        this.categorySaving = false;
        this.categoryError = this.feedback.error(error, this.translate.instant('PRODUCTS.ERR_ADD_CATEGORY'));
      },
    });
  }

  startEditCategory(category: Category): void {
    this.editingCategory = category;
    this.editingCategoryName = category.category_name;
    this.editingCategoryParentId = category.parent_id;
    this.categoryError = '';
  }

  updateCategory(): void {
    if (this.categorySaving) return;
    if (!this.editingCategory || !this.editingCategoryName.trim()) {
      this.categoryError = this.translate.instant('PRODUCTS.ERR_ENTER_CAT_NAME');
      return;
    }
    if (this.editingCategoryParentId === this.editingCategory.id || this.isDescendant(this.editingCategoryParentId, this.editingCategory.id)) {
      this.categoryError = this.translate.instant('PRODUCTS.ERR_CAT_SELF_REFERENCE');
      return;
    }

    this.categorySaving = true;
    this.categoryError = '';
    this.categoryService.update(this.editingCategory.id, {
      category_name: this.editingCategoryName.trim(),
      parent_id: this.editingCategoryParentId,
    }).subscribe({
      next: () => {
        this.categorySaving = false;
        this.editingCategory = null;
        this.feedback.success(this.translate.instant('PRODUCTS.MSG_CAT_UPDATED'));
        this.loadCategories();
        this.loadReferenceData();
      },
      error: error => {
        this.categorySaving = false;
        this.categoryError = this.feedback.error(error, this.translate.instant('PRODUCTS.ERR_UPDATE_CATEGORY'));
      },
    });
  }

  deleteCategory(category: Category): void {
    if (this.deletingCategoryIds.has(category.id) || !confirm(this.translate.instant('PRODUCTS.CONFIRM_DELETE_CATEGORY', { name: category.category_name }))) {
      return;
    }
    this.deletingCategoryIds.add(category.id);
    this.categoryError = '';
    this.categoryService.delete(category.id).subscribe({
      next: () => {
        this.deletingCategoryIds.delete(category.id);
        if (this.selectedCategoryId === category.id) {
          this.selectCategory(null);
        }
        this.loadCategories();
        this.loadReferenceData();
        this.feedback.success(this.translate.instant('PRODUCTS.MSG_CAT_DELETED'));
      },
      error: error => { this.deletingCategoryIds.delete(category.id); this.categoryError = this.feedback.error(error, this.translate.instant('PRODUCTS.ERR_DELETE_CATEGORY')); },
    });
  }

  get editableParentCategories(): Category[] {
    if (!this.editingCategory) {
      return this.flatCategories;
    }
    return this.flatCategories.filter(category => category.id !== this.editingCategory?.id && !this.isDescendant(category.id, this.editingCategory?.id));
  }

  private isDescendant(candidateId: string | null, ancestorId: string | undefined): boolean {
    if (!candidateId || !ancestorId) {
      return false;
    }
    const ancestor = this.flatCategories.find(category => category.id === ancestorId);
    return !!ancestor?.children?.some(child => child.id === candidateId || this.isDescendant(candidateId, child.id));
  }

  private flattenCategories(nodes: Category[]): Category[] {
    return nodes.reduce<Category[]>((flat, category) => [
      ...flat,
      category,
      ...this.flattenCategories(category.children || []),
    ], []);
  }

  selectCategory(categoryId: string | null): void {
    this.selectedCategoryId = categoryId;
    this.loadProducts(1);
  }

  exportProducts(): void {
    this.productService.listAll({
      search: this.search,
      low_stock: this.lowStock,
      category_id: this.selectedCategoryId,
      type: this.productType || null,
    }).subscribe({
      next: response => {
        downloadExcel('products.csv', ['الاسم', 'SKU', 'النوع', 'السعر', 'التصنيف'], response.data.map(product => [
          product.product_name, product.sku, product.type, product.price, product.category?.category_name,
        ]));
        this.feedback.success(this.translate.instant('PRODUCTS.MSG_EXPORT_READY'));
      },
      error: (err) => this.feedback.error(err, this.translate.instant('PRODUCTS.ERR_EXPORT')),
    });
  }

  toggleCategory(categoryId: string): void {
    if (this.expandedCategoryIds.has(categoryId)) {
      this.expandedCategoryIds.delete(categoryId);
    } else {
      this.expandedCategoryIds.add(categoryId);
    }
  }

  isCategoryExpanded(categoryId: string): boolean {
    return this.expandedCategoryIds.has(categoryId);
  }

  loadProducts(page = this.page): void {
    this.loading = true;
    this.errorMessage = '';
    this.productService.list({
      search: this.search,
      low_stock: this.lowStock,
      category_id: this.selectedCategoryId,
      type: this.productType || null,
      per_page: this.perPage,
      page,
    }).subscribe({
      next: (response: ProductListResponse) => {
        this.products = response.data;
        this.page = response.meta.current_page;
        this.totalPages = response.meta.last_page;
        this.totalProducts = response.meta.total;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.errorMessage = this.translate.instant('PRODUCTS.ERR_LOAD_PRODUCTS');
      },
    });
  }

  isLowStock(product: Product): boolean {
    const inventory = product.inventory;
    if (!inventory) {
      return false;
    }

    return Number(inventory.quantity_available) <= Number(inventory.reorder_level);
  }

  get pageNumbers(): number[] {
    return Array.from({ length: this.totalPages }, (_, index) => index + 1);
  }
}
