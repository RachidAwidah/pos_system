<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_categories_api_returns_an_arbitrarily_nested_tree_with_paths(): void
    {
        $this->authenticateAdmin();
        [$root, $child, $grandchild] = $this->createThreeLevelTree();

        $response = $this->getJson('/v1/categories')->assertOk();
        $rootNode = collect($response->json('data'))->firstWhere('id', $root->id);

        $this->assertNotNull($rootNode);
        $this->assertSame(0, $rootNode['depth']);
        $this->assertSame('Food', $rootNode['path']);
        $this->assertSame($child->id, $rootNode['children'][0]['id']);
        $this->assertSame(1, $rootNode['children'][0]['depth']);
        $this->assertSame($grandchild->id, $rootNode['children'][0]['children'][0]['id']);
        $this->assertSame('Food / Drinks / Juice', $rootNode['children'][0]['children'][0]['path']);

        $this->getJson("/v1/categories/{$root->id}")
            ->assertOk()
            ->assertJsonPath('data.children.0.children.0.id', $grandchild->id);
    }

    public function test_category_cannot_be_moved_below_one_of_its_descendants(): void
    {
        $this->authenticateAdmin();
        [$root, , $grandchild] = $this->createThreeLevelTree();

        $this->patchJson("/v1/categories/{$root->id}", [
            'parent_id' => $grandchild->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        $this->assertNull($root->refresh()->parent_id);
    }

    public function test_admin_can_view_and_manage_the_category_tree_from_the_web_interface(): void
    {
        $admin = $this->admin();
        [$root, $child, $grandchild] = $this->createThreeLevelTree();

        $this->actingAs($admin)
            ->get('/categories')
            ->assertOk()
            ->assertSee('شجرة التصنيفات')
            ->assertSee('Food / Drinks / Juice');

        $this->actingAs($admin)->post('/categories', [
            'category_name' => 'Fresh Juice',
            'parent_id' => $grandchild->id,
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'category_name' => 'Fresh Juice',
            'parent_id' => $grandchild->id,
        ]);

        $this->actingAs($admin)->put(route('categories.update', $child), [
            'category_name' => 'Cold Drinks',
            'parent_id' => $root->id,
        ])->assertRedirect(route('categories.index'));

        $this->assertSame('Cold Drinks', $child->refresh()->category_name);
    }

    /** @return array{Category, Category, Category} */
    private function createThreeLevelTree(): array
    {
        $root = Category::factory()->create(['category_name' => 'Food', 'parent_id' => null]);
        $child = Category::factory()->create(['category_name' => 'Drinks', 'parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['category_name' => 'Juice', 'parent_id' => $child->id]);

        return [$root, $child, $grandchild];
    }

    private function authenticateAdmin(): void
    {
        Sanctum::actingAs($this->admin());
    }

    private function admin(): User
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);

        return $admin;
    }
}
