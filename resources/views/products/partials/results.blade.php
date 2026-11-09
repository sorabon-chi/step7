<div class="product-list__table-wrapper">
    <table class="product-list__table">
        <thead>
            <tr>
                @foreach ([
                    'id' => 'ID',
                    'product_name' => '商品名',
                    'price' => '価格',
                    'stock' => '在庫数',
                    'company_name' => 'メーカー名',
                ] as $sort_column => $sort_label)
                    <th
                        scope="col"
                        @if ($search_conditions['sort_by'] === $sort_column)
                            aria-sort="{{ $search_conditions['sort_order'] === 'asc' ? 'ascending' : 'descending' }}"
                        @endif
                    >
                        <a
                            href="{{ route('products.index', array_merge($search_conditions, [
                                'sort_by' => $sort_column,
                                'sort_order' => $search_conditions['sort_by'] === $sort_column
                                    && $search_conditions['sort_order'] === 'asc' ? 'desc' : 'asc',
                                'page' => 1,
                            ])) }}"
                            class="product-list__sort"
                        >
                            {{ $sort_label }}
                            @if ($search_conditions['sort_by'] === $sort_column)
                                <span aria-hidden="true">
                                    {{ $search_conditions['sort_order'] === 'asc' ? '▲' : '▼' }}
                                </span>
                            @endif
                        </a>
                    </th>
                    @if ($sort_column === 'id')
                        <th scope="col">商品画像</th>
                    @endif
                @endforeach
                <th scope="col">
                    <a href="{{ route('products.create') }}" class="btn product-list__create">新規登録</a>
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr data-product-id="{{ $product->id }}">
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
