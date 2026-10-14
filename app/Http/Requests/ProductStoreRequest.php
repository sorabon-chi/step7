<?php

namespace App\Http\Requests;

class ProductStoreRequest extends ProductRequest
{
    protected $redirectRoute = 'products.create';
}
