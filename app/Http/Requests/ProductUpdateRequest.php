<?php

namespace App\Http\Requests;

class ProductUpdateRequest extends ProductRequest
{
    protected function getRedirectUrl(): string {
        return route('products.edit', $this->route('product_id'));
    }
}
