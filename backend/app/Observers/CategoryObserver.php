<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class CategoryObserver
{
    public function saving(Category $category): void
    {
        if (! $category->isDirty('parent_id') || $category->parent_id === null) {
            return;
        }

        $categoryId = $category->getKey();
        $ancestorId = $category->parent_id;
        $visitedIds = [];

        while ($ancestorId !== null) {
            if ($ancestorId === $categoryId || isset($visitedIds[$ancestorId])) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The selected parent would create a category cycle.',
                ]);
            }

            $visitedIds[$ancestorId] = true;
            $ancestor = Category::query()->select(['id', 'parent_id'])->find($ancestorId);

            if ($ancestor === null) {
                return;
            }

            $ancestorId = $ancestor->parent_id;
        }
    }
}
