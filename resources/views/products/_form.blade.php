<form
    id="productForm"
    method="POST"
    action="{{ $form_action }}"
    enctype="multipart/form-data"
    class="product-form__panel"
>
    @csrf
    @if ($form_method !== 'POST')
        @method($form_method)
    @endif

    <div class="product-form__fields">
        @if ($product !== null)
            <span class="product-form__label">ID</span>
            <span class="product-form__id">{{ $product->id }}</span>
        @endif
        <label for="productName" class="product-form__label">
            商品名<span class="product-form__required" aria-hidden="true">*</span>
        </label>
        <div>
            <input
                id="productName"
                type="text"
                name="product_name"
                class="form-control @error('product_name') is-invalid @enderror"
                value="{{ old('product_name', $product?->product_name) }}"
                maxlength="255"
                required
            >
            @error('product_name')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <label for="companyId" class="product-form__label">
            メーカー名<span class="product-form__required" aria-hidden="true">*</span>
        </label>
        <div>
            <select
                id="companyId"
                name="company_id"
                class="form-select @error('company_id') is-invalid @enderror"
                required
            >
                <option value="">メーカーを選択してください</option>
                @foreach ($companies as $company)
                    <option
                        value="{{ $company->id }}"
                        @selected((string) old('company_id', $product?->company_id) === (string) $company->id)
                    >
                        {{ $company->company_name }}
                    </option>
                @endforeach
            </select>
            @error('company_id')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <label for="price" class="product-form__label">
            価格<span class="product-form__required" aria-hidden="true">*</span>
        </label>
        <div>
            <input
                id="price"
                type="number"
                name="price"
                class="form-control @error('price') is-invalid @enderror"
                value="{{ old('price', $product?->price) }}"
                min="0"
                max="2147483647"
                step="1"
                required
            >
            @error('price')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <label for="stock" class="product-form__label">
            在庫数<span class="product-form__required" aria-hidden="true">*</span>
        </label>
        <div>
            <input
                id="stock"
                type="number"
                name="stock"
                class="form-control @error('stock') is-invalid @enderror"
                value="{{ old('stock', $product?->stock) }}"
                min="0"
                max="2147483647"
                step="1"
                required
            >
            @error('stock')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <label for="comment" class="product-form__label">コメント</label>
        <div>
            <textarea
                id="comment"
                name="comment"
                class="form-control @error('comment') is-invalid @enderror"
                rows="4"
            >{{ old('comment', $product?->comment) }}</textarea>
            @error('comment')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <label for="imgPath" class="product-form__label">商品画像</label>
        <div>
            @if ($product !== null)
                <div class="product-form__current-image">
                    @if ($product->img_path)
                        <img
                            src="{{ asset('storage/' . $product->img_path) }}"
                            alt="{{ $product->product_name }}"
                            class="product-form__image"
                        >
                    @else
                        <span class="product-form__image-placeholder">画像なし</span>
                    @endif
                </div>
            @endif
            <input
                id="imgPath"
                type="file"
                name="img_path"
                class="form-control @error('img_path') is-invalid @enderror"
                accept="image/jpeg,image/png,image/gif,image/bmp,image/webp"
            >
            @if ($product !== null)
                <p class="product-form__help">画像を選び直さない場合は、現在の画像を使用します。</p>
            @endif
            @error('img_path')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="product-form__actions">
        <button type="submit" class="btn product-form__submit">{{ $submit_label }}</button>
        <a href="{{ $back_url }}" class="btn product-form__back">戻る</a>
    </div>
</form>
