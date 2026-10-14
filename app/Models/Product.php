<?php

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'product_name',
        'price',
        'stock',
        'comment',
        'img_path',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'company_id' => 'integer',
        'price' => 'integer',
        'stock' => 'integer',
    ];

    public function company(): BelongsTo {
        return $this->belongsTo(Company::class);
    }

    public function sales(): HasMany {
        return $this->hasMany(Sale::class);
    }

    public static function getProductList(
        ?string $product_name = null,
        ?int $company_id = null
    ): LengthAwarePaginator {
        $products_query = static::with('company');

        if ($product_name !== null && $product_name !== '') {
            $search_keyword = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $product_name);
            $products_query->whereRaw("product_name LIKE ? ESCAPE '!'", ['%' . $search_keyword . '%']);
        }

        if ($company_id !== null) {
            $products_query->where('company_id', $company_id);
        }

        return $products_query->orderBy('id')->paginate(10)->withQueryString();
    }

    public static function getProductDetail(int $product_id): self {
        return static::with('company')->findOrFail($product_id);
    }

    public static function registerProduct(array $product_data, ?UploadedFile $product_image = null): self {
        $image_path = null;

        try {
            $image_path = static::storeProductImage($product_image);

            return DB::transaction(function () use ($product_data, $image_path) {
                return static::create([
                    'company_id' => $product_data['company_id'],
                    'product_name' => $product_data['product_name'],
                    'price' => $product_data['price'],
                    'stock' => $product_data['stock'],
                    'comment' => $product_data['comment'] ?? null,
                    'img_path' => $image_path,
                ]);
            });
        } catch (Throwable $exception) {
            static::removeProductImage($image_path);

            throw $exception;
        }
    }

    public function updateProduct(array $product_data, ?UploadedFile $product_image = null): self {
        $previous_image_path = $this->img_path;
        $image_path = null;

        try {
            $image_path = static::storeProductImage($product_image);
            DB::transaction(function () use ($product_data, $image_path, $previous_image_path) {
                $this->fill([
                    'company_id' => $product_data['company_id'],
                    'product_name' => $product_data['product_name'],
                    'price' => $product_data['price'],
                    'stock' => $product_data['stock'],
                    'comment' => $product_data['comment'] ?? null,
                    'img_path' => $image_path ?? $previous_image_path,
                ]);
                if (!$this->save()) {
                    throw new RuntimeException('商品情報を更新できませんでした。');
                }
            });
        } catch (Throwable $exception) {
            static::removeProductImage($image_path);

            throw $exception;
        }

        if ($image_path !== null && $previous_image_path !== $image_path) {
            static::removeProductImage($previous_image_path);
        }

        return $this;
    }

    public function deleteProduct(): void {
        $image_path = $this->img_path;
        DB::transaction(function () {
            if (!$this->delete()) {
                throw new RuntimeException('商品を削除できませんでした。');
            }
        });

        static::removeProductImage($image_path);
    }

    private static function storeProductImage(?UploadedFile $product_image): ?string {
        if ($product_image === null) {
            return null;
        }

        $image_path = $product_image->store('products', 'public');
        if ($image_path === false) {
            throw new RuntimeException('商品画像を保存できませんでした。');
        }

        return $image_path;
    }

    private static function removeProductImage(?string $image_path): void {
        if (!$image_path) {
            return;
        }

        try {
            if (!Storage::disk('public')->delete($image_path)) {
                throw new RuntimeException('商品画像を削除できませんでした。');
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
