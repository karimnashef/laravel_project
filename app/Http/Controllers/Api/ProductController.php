<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::all();
        return response()->json($products);
    }

    public function showByCategory(string $category_id): JsonResponse
    {
        $products = Product::where('category_id', $category_id)->get();
        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load('category'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:products,name',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'user_id' => 'nullable|exists:users,id',
            'active' => 'boolean',
        ]);

        $product = Product::create($data);
        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:products,name,' . $product->id,
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'active' => 'boolean',
        ]);

        $product->update($data);
        return response()->json($product);
    }

    public function softDelete(string $product_id): JsonResponse
    {
        $product = Product::findOrFail($product_id);
        $product->delete();
        return response()->json(['message' => 'Product soft-deleted']);
    }

    public function restore(string $product_id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($product_id);
        $product->restore();
        return response()->json(['message' => 'Product restored']);
    }

    public function destroy(string $product_id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($product_id);
        $product->forceDelete();
        return response()->json(['message' => 'Product permanently deleted']);
    }

    public function trashed(): JsonResponse
    {
        $products = Product::onlyTrashed()->get();
        return response()->json($products);
    }
}
