<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Product;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');
        if (! $product instanceof Product) {
            return false;
        }

        return $this->user() && $this->user()->can('update', $product);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $ignore = $product ? $product->id : 'NULL';

        return [
            'name' => "sometimes|required|string|max:255|unique:products,name,{$ignore}",
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'active' => 'boolean',
        ];
    }
}
