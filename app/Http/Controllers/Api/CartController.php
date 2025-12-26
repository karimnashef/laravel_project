<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    protected function getCart(Request $request)
    {
        $user = $request->user();
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function index(Request $request): JsonResponse
    {
        $cart = $this->getCart($request);
        $cart->load('items.product');
        return response()->json($cart);
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $quantity = $data['quantity'] ?? 1;

        $cart = $this->getCart($request);

        $item = CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $product->id],
            ['quantity' =>

                \DB::raw("GREATEST(1, COALESCE(quantity,0) + {$quantity})"),
                'unit_price' => $product->price
            ]
        );

        // Node: because DB::raw returns expression not supported by updateOrCreate for value, fallback to manual update
        if (! $item->wasRecentlyCreated) {
            $item->quantity += $quantity;
            $item->unit_price = $product->price;
            $item->save();
        }

        $cart->load('items.product');
        return response()->json($cart);
    }

    public function update(Request $request, CartItem $item): JsonResponse
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        if ($data['quantity'] === 0) {
            $item->delete();
            return response()->json(['message' => 'Removed']);
        }

        $item->update(['quantity' => $data['quantity']]);
        return response()->json($item);
    }

    public function remove(CartItem $item): JsonResponse
    {
        $item->delete();
        return response()->json(['message' => 'Removed']);
    }
}
