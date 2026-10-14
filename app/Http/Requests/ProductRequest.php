<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

abstract class ProductRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'product_name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'integer', 'min:1', 'exists:companies,id'],
            'price' => ['required', 'integer', 'min:0', 'max:2147483647', 'regex:/\A[0-9]+\z/'],
            'stock' => ['required', 'integer', 'min:0', 'max:2147483647', 'regex:/\A[0-9]+\z/'],
            'comment' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 65535) {
                    $fail('コメントが長すぎます。内容を短くしてください。');
                }
            }],
            'img_path' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,bmp,webp'],
        ];
    }

    public function attributes(): array {
        return [
            'product_name' => '商品名',
            'company_id' => 'メーカー名',
            'price' => '価格',
            'stock' => '在庫数',
            'comment' => 'コメント',
            'img_path' => '商品画像',
        ];
    }

    public function messages(): array {
        return [
            'required' => ':attributeは必須です。',
            'string' => ':attributeは文字列で入力してください。',
            'integer' => ':attributeは半角数字の整数で入力してください。',
            'regex' => ':attributeは半角数字で入力してください。',
            'min.numeric' => ':attributeは:min以上で入力してください。',
            'max.numeric' => ':attributeは:max以下で入力してください。',
            'max.string' => ':attributeは:max文字以内で入力してください。',
            'exists' => '選択した:attributeは存在しません。',
            'image' => ':attributeには画像ファイルを選択してください。',
            'mimes' => ':attributeにはJPEG、PNG、GIF、BMP、WebP形式の画像を選択してください。',
            'uploaded' => ':attributeのアップロードに失敗しました。',
        ];
    }
}
