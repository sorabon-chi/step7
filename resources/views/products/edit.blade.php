@extends('layouts.app')

@section('title', '商品情報編集画面')

@section('content')
<div class="container">
    <div class="product-form">
        <h1 class="product-form__heading">商品情報編集画面</h1>

        @if (session('status'))
            <div class="product-form__status" role="status">{{ session('status') }}</div>
        @endif

        @error('update')
            <div class="product-form__errors" role="alert">{{ $message }}</div>
        @enderror

        @include('products._form', [
            'product' => $product,
            'form_action' => route('products.update', $product->id),
            'form_method' => 'PUT',
            'submit_label' => '更新',
            'back_url' => route('products.show', $product->id),
        ])
    </div>
</div>
@endsection
