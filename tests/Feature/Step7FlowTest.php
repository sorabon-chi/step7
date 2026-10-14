<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\DatabaseTestCase;

class Step7FlowTest extends DatabaseTestCase
{
    public function testRegistrationLoginProductManagementAndLogoutAsOneFlow(): void {
        Storage::fake('public');
        $company = $this->createCompany('Coca-Cola');
        $other_company = $this->createCompany('サントリー');

        $this->post(route('register'), [
            'name' => '確認ユーザー',
            'email' => 'workflow@example.test',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login'), [
            'email' => 'workflow@example.test',
            'password' => self::TEST_PASSWORD,
        ])->assertRedirect(route('products.index'));
        $this->assertAuthenticated();

        $image_data = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        );
        $this->post(route('products.store'), [
            'product_name' => 'テストコーラ',
            'company_id' => $company->id,
            'price' => 130,
            'stock' => 6,
            'comment' => '確認用の商品です。',
            'img_path' => UploadedFile::fake()->createWithContent('cola.png', $image_data),
        ])->assertRedirect(route('products.create'))->assertSessionHasNoErrors();
        $product = Product::query()->firstOrFail();
        $image_path = $product->img_path;
        Storage::disk('public')->assertExists($image_path);
        $this->post(route('products.store'), [
            'product_name' => '他社コーラ',
            'company_id' => $other_company->id,
            'price' => 150,
            'stock' => 10,
        ])->assertRedirect(route('products.create'))->assertSessionHasNoErrors();

        $this->get(route('products.index'))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 2);
        $this->get(route('products.index', [
            'product_name' => 'コーラ',
            'company_id' => $company->id,
        ]))->assertOk()->assertSee('テストコーラ')->assertDontSee('他社コーラ')
            ->assertViewHas('products', fn ($products) => $products->total() === 1);
        $this->get(route('products.show', $product->id))->assertOk()
            ->assertSee('確認用の商品です。')->assertSee('src="' . asset('storage/' . $image_path) . '"', false);
        $this->get(route('products.edit', $product->id))->assertOk()
            ->assertSee('value="テストコーラ"', false);
        $this->put(route('products.update', $product->id), [
            'product_name' => '更新後コーラ',
            'company_id' => $other_company->id,
            'price' => 200,
            'stock' => 9,
            'comment' => '更新後のコメントです。',
        ])->assertRedirect(route('products.edit', $product->id))->assertSessionHasNoErrors();
        $this->get(route('products.edit', $product->id))->assertOk()
            ->assertSee('value="更新後コーラ"', false);
        $this->get(route('products.index', [
            'product_name' => '更新後',
            'company_id' => $other_company->id,
        ]))->assertOk()->assertSee('更新後コーラ')
            ->assertViewHas('products', fn ($products) => $products->total() === 1);
        Storage::disk('public')->assertExists($image_path);

        $this->delete(route('products.destroy', $product->id))
            ->assertRedirect(route('products.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseHas('products', ['product_name' => '他社コーラ']);
        Storage::disk('public')->assertMissing($image_path);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('products.index'))->assertRedirect(route('login'));
    }
}
