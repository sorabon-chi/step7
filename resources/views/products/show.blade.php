@extends('layouts.app')

@section('title', '商品情報詳細画面')

@section('content')
<div class="container">
    <div class="product-detail">
        <h1 class="product-detail__heading">商品情報詳細画面</h1>

        <div class="product-detail__panel">
            <dl class="product-detail__fields">
                <dt>ID</dt>
                <dd>{{ $product->id }}</dd>

                <dt>商品画像</dt>
                <dd>
                    @if ($product->img_path)
                        <img
                            src="{{ asset('storage/' . $product->img_path) }}"
                            alt="{{ $product->product_name }}"
                            class="product-detail__image"
                        >
                    @else
                        <span class="product-detail__image-placeholder">画像なし</span>
                    @endif
                </dd>

                <dt>商品名</dt>
                <dd>{{ $product->product_name }}</dd>

                <dt>メーカー</dt>
                <dd>{{ $product->company?->company_name }}</dd>

                <dt>価格</dt>
                <dd>&yen;{{ number_format($product->price) }}</dd>

                <dt>在庫数</dt>
                <dd>{{ $product->stock }}</dd>

                <dt>コメント</dt>
                <dd class="product-detail__comment">{{ $product->comment }}</dd>
            </dl>

            <div class="product-detail__actions">
                <a href="{{ route('products.edit', $product->id) }}" class="btn product-detail__edit">編集</a>
                <a href="{{ route('products.index') }}" class="btn product-detail__back">戻る</a>
            </div>
        </div>
    </div>
</div>
@endsection
