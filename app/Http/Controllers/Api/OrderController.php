<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->with('items.product')->latest()->get();
        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        return response()->json($order->load('items.product'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipping_address' => 'required|array',
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->with('items.product')->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $order = DB::transaction(function () use ($cart, $user, $data) {
            $total = 0;
            $order = Order::create([
                'user_id' => $user->id,
                'total' => 0,
                'shipping_address' => $data['shipping_address'],
                'status' => 'pending',
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $price = $item->unit_price;
                $qty = $item->quantity;
                $total += $price * $qty;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                ]);

                // decrease stock
                $product = $item->product;
                if ($product->stock !== null) {
                    $product->decrement('stock', $qty);
                }
            }

            $order->update(['total' => $total]);

            // clear cart
            $cart->items()->delete();

            return $order;
        });

        return response()->json($order->load('items.product'), 201);
    }
}
