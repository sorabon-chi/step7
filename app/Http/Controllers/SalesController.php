<?php

namespace App\Http\Controllers;

use App\Exceptions\OutOfStockException;
use App\Http\Requests\PurchaseRequest;
use App\Models\Sale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Throwable;

class SalesController extends Controller
{
    public function store(PurchaseRequest $request): JsonResponse {
        try {
            $sale = Sale::purchaseProduct((int) $request->validated('product_id'));
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => '商品が見つかりません。'], 404);
        } catch (OutOfStockException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => '購入できませんでした。もう一度お試しください。'], 500);
        }

        return response()->json([
            'message' => '購入しました。',
            'sale_id' => $sale->id,
            'product_id' => $sale->product_id,
            'stock' => $sale->product->stock,
        ], 201);
    }
}
