<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashSessionService;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\SalesReturnService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DemoPosSeeder extends Seeder
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly CashSessionService $cashSessionService,
        private readonly CheckoutService $checkoutService,
        private readonly SalesReturnService $salesReturnService,
    ) {}

    public function run(): void
    {
        if (Order::query()->where('notes', 'like', 'DEMO:%')->exists()) {
            $this->command?->warn('Demo POS data already exists; seeding skipped.');

            return;
        }

        $originalTestNow = CarbonImmutable::getTestNow();
        $realNow = CarbonImmutable::now();

        try {
            $catalog = $this->seedCatalog();
            $customers = $this->seedCustomers();
            $cashiers = $this->seedUsers();
            $this->seedSuppliers();
            $this->seedInventory($catalog['products'], $catalog['warehouse']);
            $this->seedSales($catalog, $customers, $cashiers);
            $this->createLowStockScenarios($catalog['products'], $catalog['warehouse'], $cashiers->first(), $realNow);
        } finally {
            CarbonImmutable::setTestNow($originalTestNow);
        }
    }

    /** @return array{products: Collection<int, Product>, warehouse: Warehouse, register: Register, payment_methods: Collection<string, PaymentMethod>} */
    private function seedCatalog(): array
    {
        $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $piece = Unit::query()->firstOrCreate(['symbol' => 'pc'], ['name' => 'قطعة', 'decimal_places' => 0]);
        $kilogram = Unit::query()->firstOrCreate(['symbol' => 'kg'], ['name' => 'كيلوغرام', 'decimal_places' => 3]);
        $taxExempt = Tax::query()->firstOrCreate(['tax_name' => 'Tax Exempt'], ['tax_percentage' => 0]);
        $standardTax = Tax::query()->firstOrCreate(['tax_name' => 'Standard Tax'], ['tax_percentage' => 10]);

        $categoryTree = [
            'مواد غذائية' => ['مشروبات', 'معلبات', 'وجبات خفيفة'],
            'المنزل' => ['منظفات', 'عناية شخصية'],
            'قرطاسية' => ['أدوات مكتبية'],
            'خدمات' => [],
        ];
        $categories = collect();
        foreach ($categoryTree as $parentName => $children) {
            $parent = Category::query()->firstOrCreate(['category_name' => $parentName], ['parent_id' => null]);
            $categories->put($parentName, $parent);
            foreach ($children as $childName) {
                $categories->put($childName, Category::query()->firstOrCreate(
                    ['category_name' => $childName],
                    ['parent_id' => $parent->id],
                ));
            }
        }

        $definitions = [
            ['مياه معدنية', 'مشروبات', 0.35, 0.75, 0], ['مشروب غازي', 'مشروبات', 0.65, 1.35, 10],
            ['عصير برتقال', 'مشروبات', 0.80, 1.60, 10], ['حليب كامل الدسم', 'مشروبات', 0.90, 1.75, 0],
            ['قهوة باردة', 'مشروبات', 1.10, 2.50, 10], ['تونة معلبة', 'معلبات', 1.25, 2.40, 10],
            ['فول معلب', 'معلبات', 0.55, 1.10, 0], ['ذرة معلبة', 'معلبات', 0.70, 1.45, 10],
            ['شيبس ملح', 'وجبات خفيفة', 0.45, 1.00, 10], ['شوكولا بالحليب', 'وجبات خفيفة', 0.75, 1.60, 10],
            ['بسكويت شاي', 'وجبات خفيفة', 0.60, 1.25, 10], ['مكسرات مشكلة', 'وجبات خفيفة', 4.20, 7.50, 10],
            ['أرز فاخر', 'مواد غذائية', 1.10, 1.85, 0, 'kg'], ['سكر أبيض', 'مواد غذائية', 0.75, 1.20, 0, 'kg'],
            ['طحين', 'مواد غذائية', 0.65, 1.10, 0, 'kg'], ['زيت نباتي', 'مواد غذائية', 2.80, 4.25, 10],
            ['سائل جلي', 'منظفات', 1.10, 2.25, 10], ['مسحوق غسيل', 'منظفات', 3.75, 6.50, 10],
            ['مناديل ورقية', 'المنزل', 0.85, 1.70, 10], ['أكياس نفايات', 'المنزل', 1.20, 2.35, 10],
            ['شامبو', 'عناية شخصية', 2.60, 4.75, 10], ['صابون يدين', 'عناية شخصية', 0.70, 1.45, 10],
            ['معجون أسنان', 'عناية شخصية', 1.30, 2.60, 10], ['فرشاة أسنان', 'عناية شخصية', 0.90, 1.85, 10],
            ['دفتر ملاحظات', 'أدوات مكتبية', 0.80, 1.75, 10], ['قلم أزرق', 'أدوات مكتبية', 0.15, 0.50, 10],
            ['ملف أوراق', 'أدوات مكتبية', 0.45, 1.10, 10], ['بطاريات', 'المنزل', 1.70, 3.25, 10],
            ['تغليف هدايا', 'خدمات', 0.20, 1.50, 10, 'pc', ProductType::NonStock],
            ['خدمة توصيل محلي', 'خدمات', 0.00, 3.00, 0, 'pc', ProductType::Service],
        ];

        $products = collect();
        foreach ($definitions as $index => $definition) {
            [$name, $category, $cost, $price, $tax] = $definition;
            $unitSymbol = $definition[5] ?? 'pc';
            $type = $definition[6] ?? ProductType::Stock;
            $products->push(Product::query()->updateOrCreate(['sku' => sprintf('DEMO-%03d', $index + 1)], [
                'product_name' => $name,
                'barcode' => sprintf('62910000%05d', $index + 1),
                'unit_id' => $unitSymbol === 'kg' ? $kilogram->id : $piece->id,
                'type' => $type,
                'cost_price' => $cost,
                'price' => $price,
                'description' => 'منتج تجريبي مخصص لاختبار نظام نقاط البيع والتقارير.',
                'tax_id' => $tax === 0 ? $taxExempt->id : $standardTax->id,
                'category_id' => $categories->get($category)->id,
            ]));
        }

        return [
            'products' => $products,
            'warehouse' => $warehouse,
            'register' => $register,
            'payment_methods' => PaymentMethod::query()->get()->keyBy('code'),
        ];
    }

    /** @return Collection<int, Customer> */
    private function seedCustomers()
    {
        $names = [
            'أحمد الخطيب', 'سارة العلي', 'محمد الحسن', 'نور الشامي', 'عمر المصري', 'ريم محمود',
            'خالد إبراهيم', 'ليان عثمان', 'سامر يوسف', 'هدى منصور', 'مازن علي', 'رنا سليمان',
            'زياد الأحمد', 'جود خليل', 'فادي مراد', 'ميساء حمدان', 'حسام دياب', 'نادين عباس',
            'وليد شاهين', 'دانا صباغ', 'رامي حمود', 'لمى درويش', 'باسل زيدان', 'روان ناصر',
            'أيمن سعيد', 'غزل حداد', 'كريم سليم', 'تالا عيسى', 'ياسر قاسم', 'مرام طه',
        ];

        return collect($names)->map(fn (string $name, int $index) => Customer::query()->updateOrCreate(
            ['email' => sprintf('demo.customer%02d@example.com', $index + 1)],
            [
                'name' => $name,
                'phone' => sprintf('099900%04d', $index + 1),
                'address' => 'عنوان تجريبي '.($index + 1),
                'credit_limit' => 100000,
                'balance' => 0,
                'loyalty_points' => 0,
            ],
        ));
    }

    /** @return Collection<int, User> */
    private function seedUsers()
    {
        $definitions = [
            ['مدير الفرع التجريبي', 'manager@demo.pos', 'Manager'],
            ['كاشير صباحي', 'cashier.one@demo.pos', 'Cashier'],
            ['كاشير مسائي', 'cashier.two@demo.pos', 'Cashier'],
        ];

        return collect($definitions)->map(function (array $definition): User {
            [$name, $email, $roleName] = $definition;
            $user = User::query()->updateOrCreate(['email' => $email], [
                'full_name' => $name,
                'phone' => null,
                'password_hash' => Hash::make('Demo!1234'),
                'must_change_password' => false,
            ]);
            $user->roles()->sync([Role::query()->where('role_name', $roleName)->firstOrFail()->id]);

            return $user;
        });
    }

    private function seedSuppliers(): void
    {
        foreach (['شركة الغذاء الحديثة', 'موزع المشروبات', 'شركة النظافة', 'مورد القرطاسية', 'مورد المنتجات الشخصية'] as $index => $name) {
            Supplier::query()->updateOrCreate(['email' => sprintf('supplier%02d@demo.pos', $index + 1)], [
                'name' => $name,
                'company_name' => $name,
                'phone' => sprintf('098800%04d', $index + 1),
                'payable_limit' => 100000,
                'balance' => 0,
            ]);
        }
    }

    /** @param Collection<int, Product> $products */
    private function seedInventory($products, Warehouse $warehouse): void
    {
        foreach ($products->filter(fn (Product $product) => $product->type->tracksInventory()) as $index => $product) {
            $this->inventoryService->setOpeningBalance(
                $product,
                $warehouse,
                $index < 5 ? '8000.000' : '2500.000',
                $index < 5 ? '40.000' : '15.000',
                (string) $product->cost_price,
            );
        }
    }

    /** @param array{products: Collection<int, Product>, warehouse: Warehouse, register: Register, payment_methods: Collection<string, PaymentMethod>} $catalog */
    private function seedSales(array $catalog, $customers, $cashiers): void
    {
        $startDate = now()->toImmutable()->startOfDay()->subDays(180);
        $saleNumber = 0;

        for ($day = 0; $day < 180; $day++) {
            $date = $startDate->addDays($day);
            $cashier = $cashiers[$day % $cashiers->count()];
            CarbonImmutable::setTestNow($date->setTime(8, 0));
            $shift = $this->cashSessionService->open($catalog['register'], $cashier, '150.00', 'DEMO: وردية يومية تجريبية');
            $ordersPerDay = 2 + ($day % 4);

            for ($dailyOrder = 0; $dailyOrder < $ordersPerDay; $dailyOrder++) {
                $saleNumber++;
                CarbonImmutable::setTestNow($date->setTime(9 + (($dailyOrder * 2) % 11), ($day * 7 + $dailyOrder * 13) % 60));
                $customer = $this->customerForSale($customers, $saleNumber);
                $items = $this->itemsForSale($catalog['products'], $day, $dailyOrder);
                [$discountType, $discountValue] = $this->discountForSale($saleNumber);
                $total = $this->checkoutTotal($items, $catalog['products'], $discountType, $discountValue);
                $payments = $this->paymentsForSale($catalog['payment_methods'], $total, $saleNumber, $customer !== null);
                $order = $this->checkoutService->checkout(
                    $cashier,
                    $shift,
                    $items,
                    $payments,
                    $customer,
                    $discountType,
                    $discountValue,
                    notes: 'DEMO: عملية بيع رقم '.$saleNumber,
                );

                if ($saleNumber % 19 === 0 && count($payments) === 1 && bccomp((string) $order->due_amount, '0.00', 2) === 0) {
                    CarbonImmutable::setTestNow(now()->addMinutes(20));
                    $this->returnFirstItem($order, $shift, $cashier, $payments[0], $saleNumber);
                }
            }

            CarbonImmutable::setTestNow($date->setTime(21, 30));
            $expectedCash = $this->expectedCash($shift->id, '150.00');
            $difference = match ($day % 12) {
                0 => '-2.00',
                1 => '1.50',
                default => '0.00',
            };
            $this->cashSessionService->close($shift, $cashier, bcadd($expectedCash, $difference, 2));
        }
    }

    private function customerForSale($customers, int $saleNumber): ?Customer
    {
        if ($saleNumber % 5 === 0) {
            return null;
        }
        if ($saleNumber % 2 === 0) {
            return $customers[$saleNumber % 3];
        }

        return $customers[3 + (($saleNumber * 7) % ($customers->count() - 3))];
    }

    /** @return array<int, array{product_id: string, quantity: string}> */
    private function itemsForSale($products, int $day, int $dailyOrder): array
    {
        $count = 1 + (($day + $dailyOrder) % 3);
        $items = [];
        for ($line = 0; $line < $count; $line++) {
            $productIndex = $line === 0
                ? ($day + $dailyOrder) % 5
                : 5 + (($day * 3 + $dailyOrder * 5 + $line * 7) % ($products->count() - 5));
            $product = $products[$productIndex];
            $quantity = $product->unit->decimal_places === 0
                ? (string) (1 + (($day + $dailyOrder + $line) % 3))
                : number_format(0.5 + ((($day + $line) % 4) * 0.25), 3, '.', '');
            $items[] = ['product_id' => $product->id, 'quantity' => $quantity];
        }

        return $items;
    }

    /** @return array{string, string} */
    private function discountForSale(int $saleNumber): array
    {
        return match (true) {
            $saleNumber % 13 === 0 => ['percentage', '7.50'],
            $saleNumber % 9 === 0 => ['fixed', '0.25'],
            default => ['none', '0.00'],
        };
    }

    /** @return array<int, array{payment_method_id: string, amount: string, amount_tendered?: string, reference_number?: string}> */
    private function paymentsForSale($methods, string $total, int $saleNumber, bool $hasCustomer): array
    {
        if ($hasCustomer && $saleNumber % 20 === 0) {
            $partial = bcdiv($total, '2', 2);

            return [[
                'payment_method_id' => $methods['CASH']->id,
                'amount' => $partial,
                'amount_tendered' => $partial,
            ]];
        }
        if ($saleNumber % 10 === 0) {
            $cashAmount = bcdiv(bcmul($total, '0.60', 4), '1', 2);
            $cardAmount = bcsub($total, $cashAmount, 2);

            return [
                ['payment_method_id' => $methods['CASH']->id, 'amount' => $cashAmount, 'amount_tendered' => $cashAmount],
                ['payment_method_id' => $methods['CARD']->id, 'amount' => $cardAmount, 'reference_number' => 'DEMO-SPLIT-'.$saleNumber],
            ];
        }
        if ($saleNumber % 4 === 0) {
            return [['payment_method_id' => $methods['CARD']->id, 'amount' => $total, 'reference_number' => 'DEMO-CARD-'.$saleNumber]];
        }
        if ($saleNumber % 7 === 0) {
            return [['payment_method_id' => $methods['BANK']->id, 'amount' => $total, 'reference_number' => 'DEMO-BANK-'.$saleNumber]];
        }

        return [['payment_method_id' => $methods['CASH']->id, 'amount' => $total, 'amount_tendered' => $total]];
    }

    private function checkoutTotal(array $items, $products, string $discountType, string $discountValue): string
    {
        $lines = collect($items)->map(function (array $item) use ($products): array {
            $product = $products->firstWhere('id', $item['product_id']);

            return [
                'subtotal' => $this->roundMoney(bcmul($item['quantity'], (string) $product->price, 6)),
                'tax_rate' => (string) ($product->tax?->tax_percentage ?? 0),
            ];
        })->values();
        $subtotal = $lines->reduce(fn (string $carry, array $line) => bcadd($carry, $line['subtotal'], 2), '0.00');
        $discount = match ($discountType) {
            'fixed' => bcadd($discountValue, '0', 2),
            'percentage' => $this->roundMoney(bcdiv(bcmul($subtotal, $discountValue, 6), '100', 6)),
            default => '0.00',
        };
        $remainingDiscount = $discount;
        $total = '0.00';

        foreach ($lines as $index => $line) {
            $lineDiscount = $index === $lines->count() - 1
                ? $remainingDiscount
                : $this->roundMoney(bcdiv(bcmul($discount, $line['subtotal'], 6), $subtotal, 6));
            $remainingDiscount = bcsub($remainingDiscount, $lineDiscount, 2);
            $taxable = bcsub($line['subtotal'], $lineDiscount, 2);
            $tax = $this->roundMoney(bcdiv(bcmul($taxable, $line['tax_rate'], 6), '100', 6));
            $total = bcadd($total, bcadd($taxable, $tax, 2), 2);
        }

        return $total;
    }

    private function returnFirstItem(Order $order, $shift, User $cashier, array $originalPayment, int $saleNumber): void
    {
        $item = $order->items->first();
        $refundAmount = bccomp((string) $item->quantity, '1.000', 3) === 0
            ? (string) $item->total_amount
            : $this->roundMoney(bcdiv((string) $item->total_amount, (string) $item->quantity, 6));
        $refund = [
            'payment_method_id' => $originalPayment['payment_method_id'],
            'amount' => $refundAmount,
        ];
        if (isset($originalPayment['reference_number'])) {
            $refund['reference_number'] = 'REFUND-'.$originalPayment['reference_number'];
        }

        $this->salesReturnService->complete(
            $order,
            $shift,
            $cashier,
            [[
                'order_item_id' => $item->id,
                'quantity' => '1',
                'restock' => $saleNumber % 57 !== 0,
                'reason' => $saleNumber % 57 === 0 ? 'DEMO: منتج تالف' : 'DEMO: تغيير رأي العميل',
            ]],
            [$refund],
            'DEMO: مرتجع تجريبي',
        );
    }

    private function expectedCash(string $shiftId, string $openingCash): string
    {
        $cashPayments = (string) Payment::query()
            ->where('shift_id', $shiftId)
            ->where('type', PaymentType::Payment->value)
            ->where('status', PaymentStatus::Completed->value)
            ->whereHas('paymentMethod', fn ($query) => $query->where('category', 'cash'))
            ->sum('amount');
        $cashRefunds = (string) Payment::query()
            ->where('shift_id', $shiftId)
            ->where('type', PaymentType::Refund->value)
            ->where('status', PaymentStatus::Completed->value)
            ->whereHas('paymentMethod', fn ($query) => $query->where('category', 'cash'))
            ->sum('amount');

        return bcsub(bcadd($openingCash, $cashPayments, 2), $cashRefunds, 2);
    }

    private function createLowStockScenarios($products, Warehouse $warehouse, User $user, CarbonImmutable $realNow): void
    {
        CarbonImmutable::setTestNow($realNow);
        foreach ($products->filter(fn (Product $product) => $product->type->tracksInventory())->take(4) as $index => $product) {
            $balance = $product->inventoryBalances()->where('warehouse_id', $warehouse->id)->firstOrFail();
            $target = number_format(2 + $index, 3, '.', '');
            if (bccomp((string) $balance->quantity_on_hand, $target, 3) !== 0) {
                $this->inventoryService->adjustTo($product, $warehouse, $target, $user, notes: 'DEMO: ضبط رصيد لاختبار تنبيه المخزون');
            }
        }
    }

    private function roundMoney(string $value): string
    {
        return bcadd(bcadd($value, '0.005', 3), '0', 2);
    }
}
