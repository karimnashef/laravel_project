<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProductController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()->with('category')->paginate($request->input('per_page', 15));
        return ProductResource::collection($products)->response();
    }

    public function show(Product $product): JsonResponse
    {
        return (new ProductResource($product->load('category')))->response();
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()?->id;

        $product = Product::create($data);
        return (new ProductResource($product->load('category')))->response()->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        $product->update($data);
        return (new ProductResource($product->load('category')))->response();
    }

    public function softDelete(string $product_id): JsonResponse
    {
        $product = Product::findOrFail($product_id);
        $this->authorize('delete', $product);
        $product->delete();
        return response()->json(['message' => 'Product soft-deleted']);
    }

    public function restore(string $product_id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($product_id);
        $this->authorize('restore', $product);
        $product->restore();
        return response()->json(['message' => 'Product restored']);
    }

    public function destroy(string $product_id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($product_id);
        $this->authorize('forceDelete', $product);
        $product->forceDelete();
        return response()->json(['message' => 'Product permanently deleted']);
    }

    public function trashed(Request $request): JsonResponse
    {
        $products = Product::onlyTrashed()->with('category')->paginate($request->input('per_page', 15));
        return ProductResource::collection($products)->response();
    }
}
