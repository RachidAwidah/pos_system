<?php

namespace Tests;

use App\Models\Register;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function createTestRegister(): Register
    {
        $warehouse = Warehouse::query()
            ->active()
            ->defaultWarehouse()
            ->firstOrFail();

        return Register::factory()->create([
            'warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);
    }

    protected function createTestUser(): User
    {
        return User::factory()->create([
            'must_change_password' => false,
        ]);
    }
}
