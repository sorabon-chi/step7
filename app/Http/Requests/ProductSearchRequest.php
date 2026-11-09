<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductSearchRequest extends FormRequest
{
    protected $redirectRoute = 'products.index';

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $rules = [
            'product_name' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'min:1', 'exists:companies,id'],
            'sort_by' => ['nullable', Rule::in(array_keys(Product::SORT_COLUMNS))],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
        foreach (['price', 'stock'] as $range_column) {
            $rules[$range_column . '_min'] = ['nullable', 'integer', 'min:0', 'max:2147483647'];
            $rules[$range_column . '_max'] = ['nullable', 'integer', 'min:0', 'max:2147483647'];
            if ($this->filled($range_column . '_min')) {
                $rules[$range_column . '_max'][] = 'gte:' . $range_column . '_min';
            }
        }

        return $rules;
    }

    public function attributes(): array {
        return [
            'product_name' => '商品名',
            'company_id' => 'メーカー名',
            'price_min' => '価格の下限',
            'price_max' => '価格の上限',
            'stock_min' => '在庫数の下限',
            'stock_max' => '在庫数の上限',
            'sort_by' => '並べ替え項目',
            'sort_order' => '並べ替え順',
            'page' => 'ページ',
        ];
    }
}
