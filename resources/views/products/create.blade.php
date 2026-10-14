@extends('layouts.app')

@section('title', '商品新規登録画面')

@section('content')
<div class="container">
    <div class="product-form">
        <h1 class="product-form__heading">商品新規登録画面</h1>

        @if (session('status'))
            <div class="product-form__status" role="status">{{ session('status') }}</div>
        @endif

        @error('registration')
            <div class="product-form__errors" role="alert">{{ $message }}</div>
        @enderror

        @include('products._form', [
            'product' => null,
            'form_action' => route('products.store'),
            'form_method' => 'POST',
            'submit_label' => '新規登録',
            'back_url' => route('products.index'),
        ])
    </div>
</div>
@endsection
