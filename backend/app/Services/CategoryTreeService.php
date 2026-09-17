<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryTreeService
{
    /** @return Collection<int, Category> */
    public function tree(): Collection
    {
        $categories = Category::query()
            ->select(['id', 'category_name', 'parent_id'])
            ->withCount(['children', 'products'])
            ->orderBy('category_name')
            ->get();

        $categoriesByParent = $categories->groupBy(
            fn (Category $category): string => $category->parent_id ?? '__root__',
        );

        $build = function (string $parentKey, array $ancestorNames = [], array $ancestorIds = []) use (&$build, $categoriesByParent): Collection {
            return $categoriesByParent->get($parentKey, collect())
                ->map(function (Category $category) use (&$build, $ancestorNames, $ancestorIds): Category {
                    $pathNames = [...$ancestorNames, $category->category_name];
                    $pathIds = [...$ancestorIds, $category->id];

                    $category->setAttribute('depth', count($ancestorIds));
                    $category->setAttribute('path', implode(' / ', $pathNames));
                    $category->setAttribute('path_ids', $pathIds);
                    $children = $build($category->id, $pathNames, $pathIds);
                    $category->setRelation('children', $children);
                    $category->setAttribute(
                        'tree_products_count',
                        (int) $category->products_count + $children->sum('tree_products_count'),
                    );

                    return $category;
                })
                ->values();
        };

        return $build('__root__');
    }

    /**
     * @param  Collection<int, Category>  $tree
     * @return Collection<int, Category>
     */
    public function flatten(Collection $tree): Collection
    {
        $categories = collect();

        $append = function (Collection $nodes) use (&$append, $categories): void {
            foreach ($nodes as $category) {
                $categories->push($category);
                $append($category->children);
            }
        };

        $append($tree);

        return $categories;
    }

    public function subtree(Category $category): Category
    {
        $node = $this->flatten($this->tree())->firstWhere('id', $category->id);

        return $node ?? $category->setRelation('children', collect());
    }

    /** @return array<int, string> */
    public function descendantIds(string $categoryId, ?Collection $tree = null): array
    {
        $category = $this->flatten($tree ?? $this->tree())->firstWhere('id', $categoryId);

        return $category === null
            ? []
            : $this->flatten(collect([$category]))->pluck('id')->all();
    }
}
