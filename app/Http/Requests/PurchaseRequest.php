<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'product_id' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json([
            'message' => '入力内容を確認してください。',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function attributes(): array {
        return ['product_id' => '商品ID'];
    }
}
