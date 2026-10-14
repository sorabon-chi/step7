<?php

namespace Tests\Feature;

use Tests\DatabaseTestCase;

class ProductIndexTest extends DatabaseTestCase
{
    public function testGuestsCannotReadTheProductList(): void {
        $this->get('/products')->assertRedirect(route('login'));
    }

    public function testProductListShowsRequiredColumnsAndManufacturer(): void {
        $company = $this->createCompany('メーカーA');
        $this->createProduct($company, 'コーラ');

        $response = $this->actingAs($this->createUser())->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('商品一覧画面');
        $response->assertSee('商品画像');
        $response->assertSee('在庫数');
        $response->assertSee('メーカー名');
        $response->assertSee('コーラ');
        $response->assertSee('メーカーA');
        $response->assertSee('&yen;130', false);
        $response->assertSee('画像なし');
        $response->assertViewHas('products', fn ($products) => $products->total() === 1);
    }

    public function testProductNameSearchMatchesPartOfTheName(): void {
        $company = $this->createCompany('メーカーA');
        $this->createProduct($company, 'ゼロコーラ');
        $this->createProduct($company, 'お茶');

        $response = $this->actingAs($this->createUser())->get(route('products.index', [
            'product_name' => 'コーラ',
        ]));

        $response->assertOk();
        $response->assertSee('ゼロコーラ');
        $response->assertDontSee('お茶');
        $response->assertSee('value="コーラ"', false);
    }

    public function testManufacturerSearchFiltersProducts(): void {
        $first_company = $this->createCompany('メーカーA');
        $second_company = $this->createCompany('メーカーB');
        $this->createProduct($first_company, 'メーカーAの商品');
        $this->createProduct($second_company, 'メーカーBの商品');

        $response = $this->actingAs($this->createUser())->get(route('products.index', [
            'company_id' => $second_company->id,
        ]));

        $response->assertOk();
        $response->assertSee('メーカーBの商品');
        $response->assertDontSee('メーカーAの商品');
        $response->assertSee('value="' . $second_company->id . '" selected', false);
    }

    public function testNameAndManufacturerConditionsAreCombined(): void {
        $first_company = $this->createCompany('メーカーA');
        $second_company = $this->createCompany('メーカーB');
        $this->createProduct($first_company, '対象コーラ');
        $this->createProduct($first_company, '別のお茶');
        $this->createProduct($second_company, '他社コーラ');

        $response = $this->actingAs($this->createUser())->get(route('products.index', [
            'product_name' => 'コーラ',
            'company_id' => $first_company->id,
        ]));

        $response->assertOk();
        $response->assertSee('対象コーラ');
        $response->assertDontSee('別のお茶');
        $response->assertDontSee('他社コーラ');
        $response->assertViewHas('products', fn ($products) => $products->total() === 1);
    }

    public function testEmptyFiltersShowAllProducts(): void {
        $company = $this->createCompany('メーカーA');
        $this->createProduct($company, 'コーラ');
        $this->createProduct($company, 'お茶');

        $this->actingAs($this->createUser())->get(route('products.index', [
            'product_name' => '',
            'company_id' => '',
        ]))->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 2);
    }

    public function testPaginationKeepsBothSearchConditions(): void {
        $company = $this->createCompany('メーカーA');
        for ($product_number = 1; $product_number <= 12; $product_number++) {
            $this->createProduct($company, 'コーラ' . $product_number);
        }
        $this->createProduct($company, 'お茶');

        $response = $this->actingAs($this->createUser())->get(route('products.index', [
            'product_name' => 'コーラ',
            'company_id' => $company->id,
        ]));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertSame(12, $products->total());
        $this->assertSame(10, $products->count());
        parse_str(parse_url($products->nextPageUrl(), PHP_URL_QUERY), $page_query);
        $this->assertSame('コーラ', $page_query['product_name']);
        $this->assertSame((string) $company->id, $page_query['company_id']);
        $this->assertSame('2', $page_query['page']);
        $this->get($products->nextPageUrl())->assertOk()
            ->assertViewHas('products', fn ($items) => $items->count() === 2);
    }

    public function testSearchTreatsWildcardCharactersAsLiteralText(): void {
        $company = $this->createCompany('メーカーA');
        foreach (['100%果汁', '1000果汁', 'A_B', 'ACB', 'すごい!商品'] as $product_name) {
            $this->createProduct($company, $product_name);
        }
        $this->actingAs($this->createUser());

        foreach (['%' => '100%果汁', '_' => 'A_B', '!' => 'すごい!商品'] as $keyword => $expected_name) {
            $response = $this->get(route('products.index', ['product_name' => $keyword]));
            $response->assertOk();
            $this->assertSame([$expected_name], $response->viewData('products')->pluck('product_name')->all());
        }
    }

    public function testUnknownManufacturerReturnsValidationError(): void {
        $response = $this->actingAs($this->createUser())->get(route('products.index', ['company_id' => 999]));

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHasErrors('company_id');
    }

    public function testEmptyListShowsAnEmptyState(): void {
        $this->actingAs($this->createUser())->get(route('products.index'))
            ->assertOk()->assertSee('商品が見つかりません。');
    }
}
