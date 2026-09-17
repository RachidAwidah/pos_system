<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CatalogManagementService
{
    public function __construct(
        public ProductImageService $productImages,
        private readonly InventoryService $inventory,
    ) {}

    /** @param array<string, mixed> $data */
    public function createProduct(array $data, ?User $user = null): Product
    {
        $image = $data['image'] ?? null;
        $warehouseId = $data['warehouse_id'] ?? null;
        $openingQuantity = (string) ($data['opening_quantity'] ?? '0');
        $reorderLevel = (string) ($data['reorder_level'] ?? '0');
        unset($data['image'], $data['warehouse_id'], $data['opening_quantity'], $data['reorder_level']);
        $storedImage = null;

        try {
            return DB::transaction(function () use ($data, $image, $warehouseId, $openingQuantity, $reorderLevel, $user, &$storedImage): Product {
                $product = Product::query()->create($data);
                if ($image instanceof UploadedFile) {
                    $storedImage = $this->productImages->storeUpload($product, $image);
                    $product->update(['image' => $storedImage]);
                    $product->refresh();
                }
                AuditLogService::created(Product::class, $product->id, $this->productValues($product));

                if ($product->type->tracksInventory() && is_string($warehouseId)) {
                    $warehouse = Warehouse::query()->active()->findOrFail($warehouseId);
                    $this->inventory->setOpeningBalance(
                        $product,
                        $warehouse,
                        $openingQuantity,
                        $reorderLevel,
                        (string) $product->cost_price,
                        $user,
                    );
                }

                return $product->load(['category', 'unit', 'tax', 'inventoryBalances.warehouse']);
            });
        } catch (Throwable $exception) {
            $this->productImages->deleteLocal($storedImage);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function updateProduct(Product $product, array $data): Product
    {
        $image = $data['image'] ?? null;
        $warehouseId = $data['warehouse_id'] ?? null;
        $reorderLevel = isset($data['reorder_level']) ? (string) $data['reorder_level'] : null;
        unset($data['image'], $data['warehouse_id'], $data['opening_quantity'], $data['reorder_level']);
        $oldImage = $product->image;
        $storedImage = match (true) {
            $image instanceof UploadedFile => $this->productImages->storeUpload($product, $image),
            is_string($image) => $image,
            default => null,
        };
        if ($storedImage !== null) {
            $data['image'] = $storedImage;
        }

        try {
            $updatedProduct = DB::transaction(function () use ($product, $data, $warehouseId, $reorderLevel): Product {
                if (($data['type'] ?? null) !== null
                    && $product->type === ProductType::Stock
                    && $data['type'] !== ProductType::Stock->value
                    && $product->inventoryBalances()->where(function ($query): void {
                        $query->where('quantity_on_hand', '!=', 0)->orWhere('quantity_reserved', '!=', 0);
                    })->exists()) {
                    throw new BusinessRuleException('A stock product with inventory cannot be changed to a non-stock type.');
                }

                $oldValues = $this->productValues($product);
                $product->update($data);
                $product->refresh();
                AuditLogService::updated(Product::class, $product->id, $oldValues, $this->productValues($product));

                if ($product->type->tracksInventory() && is_string($warehouseId) && $reorderLevel !== null) {
                    $warehouse = Warehouse::query()->active()->findOrFail($warehouseId);
                    $this->inventory->setReorderLevel($product, $warehouse, $reorderLevel);
                }

                return $product->load(['category', 'unit', 'tax', 'inventoryBalances.warehouse']);
            });
        } catch (Throwable $exception) {
            $this->productImages->deleteLocal($storedImage);

            throw $exception;
        }

        if ($storedImage !== null && $oldImage !== $storedImage) {
            $this->productImages->deleteLocal($oldImage);
        }

        return $updatedProduct;
    }

    public function fetchProductImage(Product $product): Product
    {
        return $this->updateProduct($product, [
            'image' => $this->productImages->fetchFromBarcode($product),
        ]);
    }

    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($product->orderItems()->exists() || $product->purchaseOrderItems()->exists()
                || $product->goodsReceiptItems()->exists() || $product->supplierProducts()->exists()
                || $product->stockMovements()->exists() || $product->inventoryCountItems()->exists()
                || $product->inventoryBalances()->where(function ($query): void {
                    $query->where('quantity_on_hand', '!=', 0)->orWhere('quantity_reserved', '!=', 0);
                })->exists()) {
                throw new BusinessRuleException('لا يمكن حذف المنتج لوجود مخزون أو فواتير أو حركات أو روابط تشغيلية محفوظة.');
            }
            AuditLogService::deleted(Product::class, $product->id, $this->productValues($product));
            $product->delete();
        });
    }

    /** @param array<string, mixed> $data */
    public function createCategory(array $data): Category
    {
        return DB::transaction(function () use ($data): Category {
            $category = Category::query()->create($data);
            AuditLogService::created(Category::class, $category->id, $this->categoryValues($category));

            return $category->load('parent');
        });
    }

    /** @param array<string, mixed> $data */
    public function updateCategory(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data): Category {
            $oldValues = $this->categoryValues($category);
            $category->update($data);
            $category->refresh();
            AuditLogService::updated(Category::class, $category->id, $oldValues, $this->categoryValues($category));

            return $category->load('parent');
        });
    }

    public function deleteCategory(Category $category): void
    {
        if ($category->children()->exists() || $category->products()->withTrashed()->exists()) {
            throw new BusinessRuleException('A category with children or products cannot be deleted.');
        }

        DB::transaction(function () use ($category): void {
            AuditLogService::deleted(Category::class, $category->id, $this->categoryValues($category));
            $category->delete();
        });
    }

    /** @return array<string, mixed> */
    private function productValues(Product $product): array
    {
        return [
            ...$product->only(['product_name', 'sku', 'barcode', 'unit_id', 'cost_price', 'price', 'description', 'image', 'tax_id', 'category_id']),
            'type' => $product->type->value,
        ];
    }

    /** @return array<string, mixed> */
    private function categoryValues(Category $category): array
    {
        return $category->only(['category_name', 'parent_id']);
    }
}
