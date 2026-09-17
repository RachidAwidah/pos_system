<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CatalogManagementService;
use App\Services\CategoryTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(CategoryTreeService $categoryTree): View
    {
        $tree = $categoryTree->tree();

        return view('categories.index', [
            'categoryTree' => $tree,
            'categoryOptions' => $categoryTree->flatten($tree),
        ]);
    }

    public function store(StoreCategoryRequest $request, CatalogManagementService $catalog): RedirectResponse
    {
        $catalog->createCategory($request->validated());

        return redirect()->route('categories.index')->with('status', 'تم إنشاء التصنيف بنجاح.');
    }

    public function update(UpdateCategoryRequest $request, Category $category, CatalogManagementService $catalog): RedirectResponse
    {
        $catalog->updateCategory($category, $request->validated());

        return redirect()->route('categories.index')->with('status', 'تم تحديث التصنيف بنجاح.');
    }

    public function destroy(Category $category, CatalogManagementService $catalog): RedirectResponse
    {
        $catalog->deleteCategory($category);

        return redirect()->route('categories.index')->with('status', 'تم حذف التصنيف بنجاح.');
    }
}
