<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_read_versioned_report_endpoints_as_json(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $query = '?from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString();

        $this->getJson('/v1/reports/overview'.$query)
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.period')
                ->has('data.summary.orders_count')
                ->has('data.summary.gross_profit')
                ->has('data.trend')
                ->has('data.top_products')
                ->has('data.top_customers')
                ->has('data.payment_methods'));

        $this->getJson('/v1/reports/profit'.$query)
            ->assertOk()
            ->assertJsonStructure(['data' => ['period', 'summary', 'previous_period', 'changes', 'trend']]);
        $this->getJson('/v1/reports/products'.$query)->assertOk()->assertJsonStructure(['data' => ['period', 'products']]);
        $this->getJson('/v1/reports/customers'.$query)->assertOk()->assertJsonStructure(['data' => ['period', 'customers']]);
        $this->getJson('/v1/reports/sales'.$query)->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_report_filters_reject_an_invalid_date_range(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);

        $this->getJson('/v1/reports/overview?from=2030-05-02&to=2030-05-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_cashier_cannot_read_financial_profit_report(): void
    {
        $cashier = User::factory()->create(['must_change_password' => false]);
        $cashier->roles()->sync([Role::query()->where('role_name', 'Cashier')->firstOrFail()->id]);
        Sanctum::actingAs($cashier);

        $this->getJson('/v1/reports/profit')->assertForbidden();
        $this->getJson('/v1/reports/overview')->assertForbidden();
        $this->getJson('/v1/reports/products')->assertForbidden();
    }
}
