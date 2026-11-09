<?php

namespace Tests\Feature;

use App\Models\Sale;
use RuntimeException;
use Tests\DatabaseTestCase;

class SalesApiTest extends DatabaseTestCase
{
    public function testOnePurchaseRecordsOneSaleAndDecreasesOnlyThatStock(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '対象商品', ['stock' => 2]);
        $other_product = $this->createProduct($company, '別の商品', ['stock' => 3]);

        $response = $this->postJson('/api/sales', ['product_id' => $product->id]);

        $response->assertCreated()->assertJsonPath('product_id', $product->id)
            ->assertJsonPath('stock', 1)->assertJsonPath('message', '購入しました。');
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseHas('sales', ['id' => $response->json('sale_id'), 'product_id' => $product->id]);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame(3, $other_product->fresh()->stock);
        $this->assertGuest();
    }

    public function testTheLastItemCanBePurchasedOnlyOnce(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '最後の商品', ['stock' => 1]);

        $this->postJson(route('sales.store'), ['product_id' => $product->id])
            ->assertCreated()->assertJsonPath('stock', 0);
        $this->postJson(route('sales.store'), ['product_id' => $product->id])
            ->assertStatus(409)->assertExactJson(['message' => '在庫がありません。']);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 1);
    }

    public function testAnOutOfStockProductDoesNotCreateASale(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '在庫なし', ['stock' => 0]);

        $this->postJson(route('sales.store'), ['product_id' => $product->id])->assertStatus(409);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseHas('products', $product->getAttributes());
    }

    public function testAnUnknownProductReturnsJsonNotFound(): void {
        $this->postJson(route('sales.store'), ['product_id' => 999])
            ->assertNotFound()->assertExactJson(['message' => '商品が見つかりません。']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('sales', 0);
    }

    /**
     * @dataProvider invalidPurchases
     */
    public function testInvalidInputDoesNotChangeData(array $input): void {
        $this->postJson(route('sales.store'), $input)
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->assertDatabaseCount('sales', 0);
    }

    public static function invalidPurchases(): array {
        return [
            'missing' => [[]],
            'null' => [['product_id' => null]],
            'zero' => [['product_id' => 0]],
            'negative' => [['product_id' => -1]],
            'decimal' => [['product_id' => 1.5]],
            'text' => [['product_id' => 'invalid']],
            'array' => [['product_id' => [1]]],
            'overflow' => [['product_id' => 2147483648]],
        ];
    }

    public function testValidationAlwaysReturnsJsonWithoutAnAcceptHeader(): void {
        $this->post('/api/sales', [])->assertUnprocessable()->assertJsonValidationErrors('product_id');
    }

    public function testAFailureAfterSaleInsertionRollsBackBothChanges(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '対象商品', ['stock' => 1]);
        Sale::created(function () {
            throw new RuntimeException('Test sale insertion failure.');
        });

        try {
            $this->postJson(route('sales.store'), ['product_id' => $product->id])
                ->assertStatus(500)->assertExactJson(['message' => '購入できませんでした。もう一度お試しください。']);
        } finally {
            Sale::flushEventListeners();
        }

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function testACancelledSaleInsertionRestoresStock(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '対象商品', ['stock' => 1]);
        Sale::creating(fn () => false);

        try {
            $this->postJson(route('sales.store'), ['product_id' => $product->id])->assertStatus(500);
        } finally {
            Sale::flushEventListeners();
        }

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function testPurchaseOnlyAcceptsPost(): void {
        $this->getJson('/api/sales')->assertStatus(405);
        $this->assertDatabaseCount('sales', 0);
    }
}
