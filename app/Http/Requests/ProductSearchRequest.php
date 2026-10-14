<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductSearchRequest extends FormRequest
{
    protected $redirectRoute = 'products.index';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array {
        return [
            'product_name' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'min:1', 'exists:companies,id'],
        ];
    }

    public function attributes(): array {
        return [
            'product_name' => '商品名',
            'company_id' => 'メーカー名',
        ];
    }
}
