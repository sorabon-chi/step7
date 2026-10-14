@extends('layouts.app')

@section('title', '商品一覧画面')

@section('content')
<div class="container">
    <div class="product-list">
        <h1 class="product-list__heading">商品一覧画面</h1>

        @if (session('status'))
            <div class="product-list__status" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="product-list__errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error_message)
                        <li>{{ $error_message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('products.index') }}" class="product-list__filters">
            <div class="product-list__filter">
                <label for="productName" class="visually-hidden">商品名</label>
                <input
                    id="productName"
                    type="text"
                    name="product_name"
                    class="form-control product-list__keyword"
                    value="{{ $product_name }}"
                    placeholder="検索キーワード"
                    maxlength="255"
                >
            </div>

            <div class="product-list__filter">
                <label for="companyId" class="visually-hidden">メーカー名</label>
                <select id="companyId" name="company_id" class="form-select product-list__manufacturer">
                    <option value="">メーカー名</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected($company_id === $company->id)>
                            {{ $company->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn product-list__search">検索</button>
        </form>

        <div class="product-list__table-wrapper">
            <table class="product-list__table">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">商品画像</th>
                        <th scope="col">商品名</th>
                        <th scope="col">価格</th>
                        <th scope="col">在庫数</th>
                        <th scope="col">メーカー名</th>
                        <th scope="col">
                            <a href="{{ route('products.create') }}" class="btn product-list__create">新規登録</a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->id }}</td>
                            <td>
                                @if ($product->img_path)
                                    <img
                                        src="{{ asset('storage/' . $product->img_path) }}"
                                        alt="{{ $product->product_name }}"
                                        class="product-list__image"
                                    >
                                @else
                                    <span class="product-list__image-placeholder">画像なし</span>
                                @endif
                            </td>
                            <td>{{ $product->product_name }}</td>
                            <td class="product-list__price">&yen;{{ number_format($product->price) }}</td>
                            <td>{{ $product->stock }}</td>
                            <td>{{ $product->company?->company_name }}</td>
                            <td>
                                <div class="product-list__actions">
                                    <a
                                        href="{{ route('products.show', $product->id) }}"
                                        class="btn product-list__details"
                                    >
                                        詳細
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('products.destroy', $product->id) }}"
                                        class="product-list__delete-form"
                                        data-product-name="{{ $product->product_name }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn product-list__delete" disabled>削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="product-list__empty">商品が見つかりません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="product-list__pagination">
            {{ $products->links() }}
        </div>
    </div>
</div>
@endsection
