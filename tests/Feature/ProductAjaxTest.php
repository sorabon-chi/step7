<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\DatabaseTestCase;

class ProductAjaxTest extends DatabaseTestCase
{
    public function testGuestsCannotUseAjaxSearchOrDelete(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '対象商品');

        $this->getJson(route('products.index'))->assertUnauthorized();
        $this->deleteJson(route('products.destroy', $product->id))->assertUnauthorized();
        $this->assertDatabaseHas('products', $product->getAttributes());
    }

    /**
     * @dataProvider rangeConditions
     */
    public function testRangesIncludeBoundariesAndTreatZeroAsACondition(array $conditions, array $expected): void {
        $company = $this->createCompany('メーカーA');
        foreach ([[0, 0], [100, 5], [200, 10], [300, 20], [100, 0]] as $number => [$price, $stock]) {
            $this->createProduct($company, '商品' . $number, ['price' => $price, 'stock' => $stock]);
        }

        $response = $this->actingAs($this->createUser())->getJson(route('products.index', $conditions));
        $response->assertOk()->assertJsonPath('total', count($expected));
        $this->assertSame($expected, $this->productIds($response->json('html')));
        $this->assertStringNotContainsString('<html', $response->json('html'));
    }

    public static function rangeConditions(): array {
        return [
            'price lower' => [['price_min' => 100], [5, 4, 3, 2]],
            'price upper' => [['price_max' => 100], [5, 2, 1]],
            'price both' => [['price_min' => 100, 'price_max' => 200], [5, 3, 2]],
            'stock lower' => [['stock_min' => 5], [4, 3, 2]],
            'stock upper' => [['stock_max' => 5], [5, 2, 1]],
            'stock both' => [['stock_min' => 5, 'stock_max' => 10], [3, 2]],
            'zero stock' => [['stock_min' => 0, 'stock_max' => 0], [5, 1]],
            'zero stock upper' => [['stock_max' => 0], [5, 1]],
            'zero price' => [['price_max' => 0], [1]],
            'zero lower' => [['price_min' => 0, 'stock_min' => 0], [5, 4, 3, 2, 1]],
            'empty' => [['price_min' => '', 'price_max' => '', 'stock_min' => '', 'stock_max' => ''], [5, 4, 3, 2, 1]],
        ];
    }

    public function testAjaxNameManufacturerAndAllRangeConditionsWorkTogether(): void {
        $company = $this->createCompany('メーカーA');
        $other_company = $this->createCompany('メーカーB');
        $target = $this->createProduct($company, '対象コーラ', ['price' => 100, 'stock' => 0]);
        $other = $this->createProduct($other_company, '他社コーラ', ['price' => 100, 'stock' => 0]);
        $tea = $this->createProduct($company, 'お茶', ['price' => 100, 'stock' => 0]);
        $this->createProduct($company, '高いコーラ', ['price' => 101, 'stock' => 0]);
        $this->createProduct($company, '在庫ありコーラ', ['price' => 100, 'stock' => 1]);
        $this->actingAs($this->createUser());

        $base_conditions = ['price_min' => 100, 'price_max' => 100, 'stock_min' => 0, 'stock_max' => 0];
        $cases = [
            [['product_name' => 'コーラ'], [$other->id, $target->id]],
            [['company_id' => $company->id], [$tea->id, $target->id]],
            [['product_name' => 'コーラ', 'company_id' => $company->id], [$target->id]],
        ];
        foreach ($cases as [$conditions, $expected]) {
            $response = $this->getJson(route('products.index', array_merge($base_conditions, $conditions)))->assertOk();
            $this->assertSame($expected, $this->productIds($response->json('html')));
        }
    }

    /**
     * @dataProvider sortConditions
     */
    public function testSortingAppliesBeforePaginationAndPreservesFilters(string $column, string $order): void {
        $first_company = $this->createCompany('Alpha');
        $second_company = $this->createCompany('Zulu');
        $products = [];
        for ($number = 1; $number <= 17; $number++) {
            $company = $number % 2 === 0 ? $first_company : $second_company;
            $product = $this->createProduct($company, sprintf('対象-%02d', 18 - $number), [
                'price' => (18 - $number) * 10,
                'stock' => $number % 5,
            ]);
            $products[] = [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'price' => $product->price,
                'stock' => $product->stock,
                'company_name' => $company->company_name,
            ];
        }
        $this->createProduct($first_company, '除外商品', ['price' => 0, 'stock' => 0]);
        usort($products, function ($first, $second) use ($column, $order) {
            $comparison = $first[$column] <=> $second[$column];

            return ($order === 'asc' ? $comparison : -$comparison) ?: $second['id'] <=> $first['id'];
        });
        $expected_ids = array_column($products, 'id');
        $conditions = [
            'product_name' => '対象',
            'price_min' => 0,
            'price_max' => 200,
            'stock_min' => 0,
            'stock_max' => 4,
            'sort_by' => $column,
            'sort_order' => $order,
        ];
        $this->actingAs($this->createUser());

        $response = $this->getJson(route('products.index', $conditions))->assertOk()->assertJsonPath('total', 17);
        $this->assertSame(array_slice($expected_ids, 0, 10), $this->productIds($response->json('html')));
        preg_match('/href="([^"]*page=2[^"]*)"/', $response->json('html'), $next_link);
        $this->assertNotEmpty($next_link);
        $next_url = html_entity_decode($next_link[1], ENT_QUOTES, 'UTF-8');
        $next_response = $this->getJson($next_url)->assertOk()->assertJsonPath('page', 2);
        $this->assertSame(array_slice($expected_ids, 10), $this->productIds($next_response->json('html')));
        parse_str(parse_url($next_response->json('url'), PHP_URL_QUERY), $query);
        foreach ($conditions as $key => $value) {
            $this->assertSame((string) $value, $query[$key]);
        }
    }

    public static function sortConditions(): array {
        $conditions = [];
        foreach (['id', 'product_name', 'price', 'stock', 'company_name'] as $column) {
            foreach (['asc', 'desc'] as $order) {
                $conditions[$column . ' ' . $order] = [$column, $order];
            }
        }

        return $conditions;
    }

    /**
     * @dataProvider invalidSearches
     */
    public function testInvalidSearchesReturnJsonErrors(array $conditions, string $field): void {
        $this->actingAs($this->createUser())->getJson(route('products.index', $conditions))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidSearches(): array {
        return [
            'negative price' => [['price_min' => -1], 'price_min'],
            'decimal price' => [['price_max' => 1.5], 'price_max'],
            'reversed price' => [['price_min' => 2, 'price_max' => 1], 'price_max'],
            'reversed stock' => [['stock_min' => 1, 'stock_max' => 0], 'stock_max'],
            'negative stock' => [['stock_max' => -1], 'stock_max'],
            'text stock' => [['stock_min' => 'invalid'], 'stock_min'],
            'overflow' => [['price_max' => 2147483648], 'price_max'],
            'unknown manufacturer' => [['company_id' => 999], 'company_id'],
            'unknown sort column' => [['sort_by' => 'id; DROP TABLE products'], 'sort_by'],
            'unknown sort direction' => [['sort_order' => 'sideways'], 'sort_order'],
            'invalid page' => [['page' => 0], 'page'],
        ];
    }

    public function testSortLinksToggleDirectionAndResetPage(): void {
        $company = $this->createCompany('メーカーA');
        $this->createProduct($company, '対象商品');
        $response = $this->actingAs($this->createUser())->getJson(route('products.index', [
            'sort_by' => 'price', 'sort_order' => 'asc', 'stock_max' => 0,
        ]))->assertOk();
        $html = html_entity_decode($response->json('html'), ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('sort_by=price&sort_order=desc&stock_max=0&page=1', $html);
        $this->assertStringContainsString('aria-sort="ascending"', $html);
    }

    public function testSuccessfulAjaxDeletionRefreshesTheLastPageWithoutLosingConditions(): void {
        $company = $this->createCompany('メーカーA');
        for ($number = 1; $number <= 11; $number++) {
            $product = $this->createProduct($company, '対象商品' . $number, ['stock' => 0]);
        }
        $this->actingAs($this->createUser());
        $conditions = [
            'product_name' => '対象', 'stock_max' => 0, 'sort_by' => 'price', 'sort_order' => 'asc', 'page' => 2,
        ];
        $response = $this->getJson(route('products.index', $conditions))->assertOk();
        $product_id = $this->productIds($response->json('html'))[0];

        $this->deleteJson(route('products.destroy', $product_id))->assertOk()
            ->assertJsonPath('product_id', $product_id);
        $next_response = $this->getJson(route('products.index', $conditions))
            ->assertOk()->assertJsonPath('page', 1)->assertJsonPath('total', 10);
        $this->assertCount(10, $this->productIds($next_response->json('html')));
        $this->assertDatabaseMissing('products', ['id' => $product_id]);
        $this->assertStringContainsString('stock_max=0', $next_response->json('url'));
        $this->assertStringContainsString('sort_order=asc', $next_response->json('url'));
    }

    public function testSalesReferenceFailureKeepsTheProductSaleAndImage(): void {
        Storage::fake('public');
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '残す商品', ['img_path' => 'products/keep.png']);
        Storage::disk('public')->put($product->img_path, 'test-only-image');
        $sale = Sale::create(['product_id' => $product->id]);
        $this->actingAs($this->createUser());

        $this->deleteJson(route('products.destroy', $product->id))
            ->assertStatus(409)->assertJsonPath('message', '売上データがある商品は削除できません。');
        $this->assertDatabaseHas('products', $product->getAttributes());
        $this->assertDatabaseHas('sales', $sale->getAttributes());
        Storage::disk('public')->assertExists($product->img_path);
        $response = $this->getJson(route('products.index'))->assertOk();
        $this->assertSame([$product->id], $this->productIds($response->json('html')));
    }

    public function testUnexpectedAjaxDeletionFailureReturnsAnErrorAndKeepsTheRow(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '残す商品');
        $this->actingAs($this->createUser());
        Product::deleted(function () {
            throw new RuntimeException('Test deletion failure.');
        });
        try {
            $this->deleteJson(route('products.destroy', $product->id))->assertStatus(500)
                ->assertExactJson(['message' => '商品を削除できませんでした。もう一度お試しください。']);
        } finally {
            Product::flushEventListeners();
        }

        $this->assertDatabaseHas('products', $product->getAttributes());
    }

    private function productIds(string $html): array {
        preg_match_all('/data-product-id="(\\d+)"/', $html, $matches);

        return array_map('intval', $matches[1]);
    }
}
