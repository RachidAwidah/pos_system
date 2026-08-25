<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontendTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')->assertOk()->assertSee('مرحباً بعودتك');
    }

    public function test_user_can_login_with_a_web_session(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['password_hash' => Hash::make('KnownPassword!123')]);

        $this->post('/login', [
            'email' => config('pos.admin_email'),
            'password' => 'KnownPassword!123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_authenticated_admin_can_view_main_frontend_pages(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('لوحة التحكم');
        $this->actingAs($admin)->get('/pos')->assertOk()->assertSee('شاشة البيع');
        $this->actingAs($admin)->get('/products')->assertOk()->assertSee('Bottled Water');
        $this->actingAs($admin)->get('/orders')->assertOk()->assertSee('سجل الفواتير');
        $this->actingAs($admin)->get('/reports')->assertOk()->assertSee('التقارير والتحليلات');
        $this->actingAs($admin)->get('/customers')->assertOk()->assertSee('Walk-in Customer');
        $this->actingAs($admin)->get('/suppliers')->assertOk()->assertSee('Main Supplier');
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee($admin->full_name);
        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('إنشاء مستخدم');
        $this->actingAs($admin)->get(route('users.edit', $admin))->assertOk()->assertSee('تعديل المستخدم');
        $this->actingAs($admin)->get('/roles')->assertOk()->assertSee('الأدوار والصلاحيات');
        $this->actingAs($admin)->get('/roles/create')->assertOk()->assertSee('إنشاء دور');
        $this->actingAs($admin)->get('/settings')->assertOk()->assertSee('الإعدادات العامة');
    }

    public function test_admin_can_create_a_web_user_with_a_role_and_the_user_can_login_normally(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        $cashierRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Web Cashier',
            'email' => 'web.cashier@example.com',
            'phone' => '555-0199',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'role_ids' => [$cashierRole->id],
            'must_change_password' => false,
        ])->assertRedirect(route('users.index'));

        $cashier = User::query()->where('email', 'web.cashier@example.com')->firstOrFail();
        $this->assertFalse($cashier->must_change_password);
        $this->assertTrue($cashier->hasRole('Cashier'));

        $this->post('/logout')->assertRedirect(route('login'));
        $this->post('/login', [
            'email' => 'web.cashier@example.com',
            'password' => 'Password!123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($cashier);
    }

    public function test_admin_can_manage_a_custom_role_from_the_web_interface(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        $permission = Permission::query()->where('permission_key', 'products.view')->firstOrFail();

        $this->actingAs($admin)->post('/roles', [
            'name' => 'Inventory Viewer',
            'permission_ids' => [$permission->id],
        ])->assertRedirect(route('roles.index'));

        $role = Role::query()->where('role_name', 'Inventory Viewer')->firstOrFail();
        $this->get(route('roles.edit', $role))->assertOk()->assertSee('products.view');

        $this->put(route('roles.update', $role), [
            'name' => 'Inventory Auditor',
            'permission_ids' => [$permission->id],
        ])->assertRedirect(route('roles.index'));

        $this->assertSame('Inventory Auditor', $role->refresh()->role_name);

        $this->delete(route('roles.destroy', $role))->assertRedirect(route('roles.index'));
        $this->assertNull(Role::query()->find($role->id));
    }

    public function test_web_login_is_rate_limited_by_email_and_ip_address(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'web.rate.limit@example.com',
                'password' => 'Incorrect!123',
            ])->assertRedirect('/login');
        }

        $this->post('/login', [
            'email' => 'web.rate.limit@example.com',
            'password' => 'Incorrect!123',
        ])->assertTooManyRequests();
    }
}
