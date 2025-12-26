<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $favorites = $user->favorites()->with('product')->get();
        return response()->json(['favorites' => $favorites]);
    }

    public function toggle(Request $request, string $product_id): JsonResponse
    {
        $user = $request->user();
        $exists = Favorite::where('user_id', $user->id)->where('product_id', $product_id)->first();

        if ($exists) {
            $exists->delete();
            return response()->json(['message' => 'Removed']);
        }

        $fav = Favorite::create(['user_id' => $user->id, 'product_id' => $product_id]);
        return response()->json($fav, 201);
    }
}
