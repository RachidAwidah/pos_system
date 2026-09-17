<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartyCrudApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manage_customers_and_suppliers(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);

        $customerResponse = $this->postJson('/v1/customers', [
            'name' => 'API Customer',
            'email' => 'api.customer@example.com',
            'credit_limit' => '100.00',
        ])->assertCreated();
        $customer = Customer::query()->findOrFail($customerResponse->json('data.id'));
        $this->getJson("/v1/customers/{$customer->id}")->assertOk();
        $this->patchJson("/v1/customers/{$customer->id}", ['phone' => '555-0101'])
            ->assertOk()
            ->assertJsonPath('data.phone', '555-0101');
        $this->deleteJson("/v1/customers/{$customer->id}")->assertNoContent();
        $this->assertSoftDeleted($customer);

        $supplierResponse = $this->postJson('/v1/suppliers', [
            'name' => 'API Supplier',
            'email' => 'api.supplier@example.com',
            'payable_limit' => '500.00',
        ])->assertCreated();
        $supplier = Supplier::query()->findOrFail($supplierResponse->json('data.id'));
        $this->getJson("/v1/suppliers/{$supplier->id}")->assertOk();
        $this->patchJson("/v1/suppliers/{$supplier->id}", ['phone' => '555-0202'])
            ->assertOk()
            ->assertJsonPath('data.phone', '555-0202');
        $this->deleteJson("/v1/suppliers/{$supplier->id}")->assertNoContent();

        $this->assertSoftDeleted($supplier);
    }
}
