<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_category_can_belong_to_a_parent_and_expose_its_children(): void
    {
        $parent = Category::query()->where('category_name', 'Food')->firstOrFail();
        $child = Category::query()->where('category_name', 'General')->firstOrFail();

        $child->parent()->associate($parent);
        $child->save();

        $this->assertTrue($child->refresh()->parent->is($parent));
        $this->assertTrue($parent->children()->whereKey($child)->exists());
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $category = Category::query()->where('category_name', 'Food')->firstOrFail();
        $category->parent_id = $category->id;

        $this->expectException(ValidationException::class);

        $category->save();
    }

    public function test_category_parent_cannot_be_one_of_its_descendants(): void
    {
        $parent = Category::query()->where('category_name', 'Food')->firstOrFail();
        $child = Category::query()->where('category_name', 'General')->firstOrFail();

        $child->parent()->associate($parent);
        $child->save();
        $parent->parent()->associate($child);

        $this->expectException(ValidationException::class);

        $parent->save();
    }
}
