<?php

namespace Tests\Feature;

use Tests\DatabaseTestCase;

class ProductShowTest extends DatabaseTestCase
{
    public function testGuestsCannotReadProductDetails(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, 'コーラ');

        $this->get(route('products.show', $product->id))->assertRedirect(route('login'));
    }

    public function testDetailsShowTheSelectedProductAndAllRequiredFields(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, 'コーラ', [
            'price' => 1230,
            'stock' => 6,
            'comment' => "冷やしてお飲みください。\n開封後はお早めに。",
        ]);
        $this->createProduct($company, '別のお茶');

        $response = $this->actingAs($this->createUser())->get(route('products.show', $product->id));

        $response->assertOk()->assertViewIs('products.show');
        $response->assertViewHas('product', fn ($selected_product) => $selected_product->id === $product->id);
        $response->assertSeeInOrder([
            '商品情報詳細画面', 'ID', '商品画像', '商品名', 'メーカー', '価格', '在庫数', 'コメント',
        ]);
        $response->assertSee('コーラ')->assertSee('メーカーA');
        $response->assertSee('&yen;1,230', false)->assertSee('<dd>6</dd>', false);
        $response->assertSee($product->comment)->assertDontSee('別のお茶');
    }

    public function testDetailsShowAnImageWhenAnImagePathIsStored(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, 'コーラ', ['img_path' => 'products/sample.png']);

        $this->actingAs($this->createUser())->get(route('products.show', $product->id))
            ->assertOk()->assertSee('src="' . asset('storage/products/sample.png') . '"', false)
            ->assertDontSee('画像なし');
    }

    public function testDetailsAllowMissingOptionalImageAndComment(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, 'コーラ');

        $this->actingAs($this->createUser())->get(route('products.show', $product->id))
            ->assertOk()->assertSee('画像なし')
            ->assertSee('<dd class="product-detail__comment"></dd>', false);
    }

    public function testDetailsEscapeProductNamesAndComments(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '<script>alert("name")</script>', [
            'comment' => '<script>alert("comment")</script>',
        ]);

        $response = $this->actingAs($this->createUser())->get(route('products.show', $product->id));

        $response->assertOk()->assertSee($product->product_name)->assertSee($product->comment);
        $response->assertDontSee($product->product_name, false)->assertDontSee($product->comment, false);
    }

    public function testMissingOrInvalidProductIdsReturnNotFound(): void {
        $this->actingAs($this->createUser());
        $this->get(route('products.show', 999))->assertNotFound();
        $this->get('/products/not-a-number')->assertNotFound();
    }

    public function testProductListLinksToDetailsAndDetailsReturnToList(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, 'コーラ');
        $this->actingAs($this->createUser());

        $this->get(route('products.index'))->assertOk()
            ->assertSee('href="' . route('products.show', $product->id) . '"', false)->assertSee('詳細');
        $this->get(route('products.show', $product->id))->assertOk()
            ->assertSee('href="' . route('products.index') . '" class="btn product-detail__back"', false)
            ->assertSee('戻る');
    }
}
