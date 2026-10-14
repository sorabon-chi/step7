<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\DatabaseTestCase;

class ProductDeleteTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        Storage::fake('public');
    }

    public function testGuestsCannotDeleteProducts(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);

        $this->delete(route('products.destroy', $product->id))->assertRedirect(route('login'));
        $this->assertDatabaseHas('products', $product->getAttributes());
        Storage::disk('public')->assertExists($product->img_path);
    }

    public function testDeletionRemovesOnlyTheSelectedProductAndItsImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $other_product = $this->createProductWithImage($company, '残す商品');
        $user = $this->createUser();

        $this->actingAs($user)->delete(route('products.destroy', $product->id))
            ->assertRedirect(route('products.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('status', '商品を削除しました。');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseHas('products', $other_product->getAttributes());
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('products', 1);
        Storage::disk('public')->assertMissing($product->img_path);
        Storage::disk('public')->assertExists($other_product->img_path);
        $this->get(route('products.index'))->assertOk()->assertSee('商品を削除しました。')
            ->assertSee('残す商品')->assertDontSee('削除対象商品');
        $this->get(route('products.show', $product->id))->assertNotFound();
        $this->get(route('products.edit', $product->id))->assertNotFound();
    }

    public function testAProductWithoutAnImageCanBeDeleted(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '画像なし商品');

        $this->actingAs($this->createUser())->delete(route('products.destroy', $product->id))
            ->assertRedirect(route('products.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get(route('products.index'))->assertOk()->assertSee('商品が見つかりません。');
    }

    public function testSalesReferencesPreventDeletionAndKeepTheProductAndImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $sale = Sale::create(['product_id' => $product->id]);

        $this->actingAs($this->createUser())->delete(route('products.destroy', $product->id))
            ->assertRedirect(route('products.index'))->assertSessionHasErrors('deletion');
        $this->assertDatabaseHas('products', $product->getAttributes());
        $this->assertDatabaseHas('sales', $sale->getAttributes());
        Storage::disk('public')->assertExists($product->img_path);
        $this->get(route('products.index'))->assertOk()->assertSee('売上データがある商品は削除できません。');
    }

    public function testDatabaseFailureRollsBackDeletionAndKeepsTheImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $this->actingAs($this->createUser());
        Product::deleted(function () {
            throw new RuntimeException('Test database deletion failure.');
        });

        try {
            $response = $this->delete(route('products.destroy', $product->id));
        } finally {
            Product::flushEventListeners();
        }

        $response->assertRedirect(route('products.index'))->assertSessionHasErrors('deletion');
        $this->assertDatabaseHas('products', $product->getAttributes());
        Storage::disk('public')->assertExists($product->img_path);
        $this->get(route('products.index'))->assertOk()->assertSee('商品を削除できませんでした。');
    }

    public function testAnEventCancellingDeletionDoesNotRemoveTheImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $this->actingAs($this->createUser());
        Product::deleting(fn () => false);

        try {
            $response = $this->delete(route('products.destroy', $product->id));
        } finally {
            Product::flushEventListeners();
        }

        $response->assertRedirect(route('products.index'))->assertSessionHasErrors('deletion');
        $this->assertDatabaseHas('products', $product->getAttributes());
        Storage::disk('public')->assertExists($product->img_path);
    }

    public function testImageCleanupFailureDoesNotUndoACommittedDeletion(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $original_disk = Storage::disk('public');
        $failed_disk = Mockery::mock($original_disk);
        $failed_disk->shouldReceive('delete')->with($product->img_path)->once()
            ->andThrow(new RuntimeException('Test image cleanup failure.'));
        Storage::shouldReceive('disk')->with('public')->andReturn($failed_disk);

        $this->actingAs($this->createUser())->delete(route('products.destroy', $product->id))
            ->assertRedirect(route('products.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('status', '商品を削除しました。');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $original_disk->assertExists($product->img_path);
    }

    public function testMissingAndInvalidProductIdsReturnNotFound(): void {
        $this->actingAs($this->createUser());

        $this->delete(route('products.destroy', 999))->assertNotFound();
        $this->delete('/products/not-a-number')->assertNotFound();
        $this->assertDatabaseCount('products', 0);
    }

    public function testRepeatedDeletionDoesNotAffectAnotherProduct(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $other_product = $this->createProductWithImage($company, '残す商品');
        $this->actingAs($this->createUser());

        $this->delete(route('products.destroy', $product->id))->assertRedirect(route('products.index'));
        $this->delete(route('products.destroy', $product->id))->assertNotFound();
        $this->assertDatabaseHas('products', $other_product->getAttributes());
        Storage::disk('public')->assertExists($other_product->img_path);
    }

    public function testEachRowHasItsOwnDeleteFormAndSafeConfirmationData(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, 'コーラ "テスト"');
        $other_product = $this->createProduct($company, 'お茶');
        $response = $this->actingAs($this->createUser())->get(route('products.index'));

        $response->assertOk();
        preg_match_all(
            '/<form\b[^>]*class="product-list__delete-form"[^>]*>.*?<\/form>/s',
            $response->getContent(),
            $delete_forms
        );
        $this->assertCount(2, $delete_forms[0]);
        foreach ([$product, $other_product] as $row_number => $row_product) {
            $delete_form = $delete_forms[0][$row_number];
            $this->assertStringContainsString(
                'action="' . route('products.destroy', $row_product->id) . '"',
                $delete_form
            );
            $this->assertStringContainsString('name="_method" value="DELETE"', $delete_form);
            $this->assertStringContainsString('name="_token"', $delete_form);
            $this->assertStringContainsString(
                'data-product-name="' . e($row_product->product_name) . '"',
                $delete_form
            );
            $this->assertStringContainsString('class="btn product-list__delete" disabled', $delete_form);
            $this->assertStringNotContainsString('onclick=', $delete_form);
            $this->assertStringNotContainsString('onsubmit=', $delete_form);
        }
    }

    public function testAGetRequestCannotDeleteAProduct(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);

        $this->actingAs($this->createUser())->get(route('products.destroy', $product->id))
            ->assertOk()->assertViewIs('products.show');
        $this->assertDatabaseHas('products', $product->getAttributes());
        Storage::disk('public')->assertExists($product->img_path);
    }

    protected function createProductWithImage(Company $company, string $product_name = '削除対象商品'): Product {
        $product = $this->createProduct($company, $product_name);
        $image_path = 'products/product-' . $product->id . '.png';
        $image_data = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        );
        Storage::disk('public')->put($image_path, $image_data);
        $product->update(['img_path' => $image_path]);

        return $product;
    }
}
