<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Sale extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'product_id' => 'integer',
    ];

    public static function purchaseProduct(int $product_id): self {
        return DB::transaction(function () use ($product_id) {
            $product = Product::lockForPurchase($product_id);
            $product->decreaseStock();
            $sale = new static(['product_id' => $product_id]);
            if (!$sale->save()) {
                throw new RuntimeException('購入を記録できませんでした。');
            }

            return $sale->setRelation('product', $product);
        }, 3);
    }

    public function product(): BelongsTo {
        return $this->belongsTo(Product::class);
    }
}
