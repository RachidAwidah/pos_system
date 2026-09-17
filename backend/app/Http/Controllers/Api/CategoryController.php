<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogService;
use App\Services\CatalogManagementService;
use App\Services\CategoryTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(CategoryTreeService $categoryTree): AnonymousResourceCollection
    {
        return CategoryResource::collection($categoryTree->tree());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request, CatalogManagementService $catalog): JsonResponse
    {
        return (new CategoryResource($catalog->createCategory($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category, CategoryTreeService $categoryTree): CategoryResource
    {
        AuditLogService::viewed(Category::class, $category->id);

        return new CategoryResource($categoryTree->subtree($category));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category, CatalogManagementService $catalog): CategoryResource
    {
        return new CategoryResource($catalog->updateCategory($category, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category, CatalogManagementService $catalog): Response
    {
        $catalog->deleteCategory($category);

        return response()->noContent();
    }
}
