@extends('layouts.app')

@section('title', '商品一覧画面')

@section('content')
<div class="container">
    <div id="productList" class="product-list">
        <h1 class="product-list__heading">商品一覧画面</h1>

        <div
            id="productStatus"
            class="product-list__status"
            role="status"
            @if (!session('status')) hidden @endif
        >{{ session('status') }}</div>

        <div
            id="productErrors"
            class="product-list__errors"
            role="alert"
            @if (!$errors->any()) hidden @endif
        >
            <ul>
                @foreach ($errors->all() as $error_message)
                    <li>{{ $error_message }}</li>
                @endforeach
            </ul>
        </div>

        <form method="GET" action="{{ route('products.index') }}" class="product-list__filters">
            <input type="hidden" name="sort_by" value="{{ $search_conditions['sort_by'] }}">
            <input type="hidden" name="sort_order" value="{{ $search_conditions['sort_order'] }}">
            <div class="product-list__filter">
                <label for="productName" class="visually-hidden">商品名</label>
                <input
                    id="productName"
                    type="text"
                    name="product_name"
                    class="form-control product-list__keyword"
                    value="{{ $search_conditions['product_name'] ?? '' }}"
                    placeholder="検索キーワード"
                    maxlength="255"
                >
            </div>

            <div class="product-list__filter">
                <label for="companyId" class="visually-hidden">メーカー名</label>
                <select id="companyId" name="company_id" class="form-select product-list__manufacturer">
                    <option value="">メーカー名</option>
                    @foreach ($companies as $company)
                        <option
                            value="{{ $company->id }}"
                            @selected((int) ($search_conditions['company_id'] ?? 0) === $company->id)
                        >{{ $company->company_name }}</option>
                    @endforeach
                </select>
            </div>

            @foreach (['price' => '価格', 'stock' => '在庫数'] as $range_name => $range_label)
                <div class="product-list__range">
                    <label for="{{ $range_name }}Min">{{ $range_label }}（下限）</label>
                    <input
                        id="{{ $range_name }}Min"
                        type="number"
                        name="{{ $range_name }}_min"
                        class="form-control"
                        value="{{ $search_conditions[$range_name . '_min'] ?? '' }}"
                        min="0"
                        max="2147483647"
                        step="1"
                    >
                </div>
                <div class="product-list__range">
                    <label for="{{ $range_name }}Max">{{ $range_label }}（上限）</label>
                    <input
                        id="{{ $range_name }}Max"
                        type="number"
                        name="{{ $range_name }}_max"
                        class="form-control"
                        value="{{ $search_conditions[$range_name . '_max'] ?? '' }}"
                        min="0"
                        max="2147483647"
                        step="1"
                    >
                </div>
            @endforeach

            <button type="submit" class="btn product-list__search">検索</button>
        </form>

        <div id="productResults" aria-live="polite" aria-busy="false">
            @include('products.partials.results')
        </div>
    </div>
</div>
@endsection
