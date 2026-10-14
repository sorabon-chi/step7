<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\DatabaseTestCase;

class ProductUpdateTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        Storage::fake('public');
    }

    public function testGuestsCannotOpenOrSubmitTheEditForm(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '登録済み商品');

        $this->get(route('products.edit', $product->id))->assertRedirect(route('login'));
        $this->put(route('products.update', $product->id), $this->validProductData($company))
            ->assertRedirect(route('login'));
        $this->assertDatabaseHas('products', $product->getAttributes());
    }

    public function testEditFormShowsCurrentValuesAndImage(): void {
        $company = $this->createCompany('メーカーA');
        $this->createCompany('メーカーB');
        $product = $this->createProductWithImage($company);

        $response = $this->actingAs($this->createUser())->get(route('products.edit', $product->id));

        $response->assertOk()->assertViewIs('products.edit');
        $response->assertViewHas('product', fn ($selected_product) => $selected_product->id === $product->id);
        $response->assertSee('商品情報編集画面');
        $response->assertSee('name="_method" value="PUT"', false);
        $response->assertSee('class="product-form__id">' . $product->id . '</span>', false);
        $response->assertDontSee('name="id"', false);
        $response->assertSee('value="登録済み商品"', false)->assertSee('value="130"', false);
        $response->assertSee('value="10"', false)->assertSee('更新前のコメント');
        $response->assertSee('メーカーA')->assertSee('メーカーB');
        $this->assertMatchesRegularExpression(
            '/<option\s+value="' . $company->id . '"\s+selected\b/',
            $response->getContent()
        );
        $response->assertSee('src="' . asset('storage/' . $product->img_path) . '"', false);
        $this->assertSame(4, substr_count($response->getContent(), 'class="product-form__required"'));
    }

    public function testUpdateChangesOnlyTheSelectedProductAndReturnsToItsEditPage(): void {
        $company = $this->createCompany('メーカーA');
        $new_company = $this->createCompany('メーカーB');
        $product = $this->createProductWithImage($company);
        $other_product = $this->createProduct($company, '別の商品');
        $product_data = $this->validProductData($new_company);
        $created_at = $product->getRawOriginal('created_at');

        $response = $this->actingAs($this->createUser())
            ->put(route('products.update', $product->id), $product_data);

        $response->assertRedirect(route('products.edit', $product->id))
            ->assertSessionHasNoErrors()->assertSessionHas('status', '商品情報を更新しました。');
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('products', $product_data + [
            'id' => $product->id,
            'img_path' => $product->img_path,
            'created_at' => $created_at,
        ]);
        $this->assertDatabaseHas('products', $other_product->getAttributes());
        Storage::disk('public')->assertExists($product->img_path);
        $this->get(route('products.edit', $product->id))->assertOk()
            ->assertSee('商品情報を更新しました。')->assertSee('value="更新済み商品"', false);
        $this->get(route('products.index'))->assertOk()->assertSee('更新済み商品');
        $this->get(route('products.show', $product->id))->assertOk()->assertSee('更新済み商品');
    }

    public function testBlankCommentIsClearedAndTheExistingImageIsKept(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $product_data = array_replace($this->validProductData($company), ['comment' => '']);

        $this->actingAs($this->createUser())->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'comment' => null,
            'img_path' => $product->img_path,
        ]);
        Storage::disk('public')->assertExists($product->img_path);
        $this->assertSame([$product->img_path], Storage::disk('public')->allFiles());
    }

    public function testProductWithoutImageCanBeUpdatedWithoutAddingAnImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '登録済み商品');
        $product_data = array_replace($this->validProductData($company), ['price' => 0, 'stock' => 0]);

        $this->actingAs($this->createUser())->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', $product_data + ['id' => $product->id, 'img_path' => null]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function testReplacingAnImageStoresTheNewImageAndRemovesTheOldImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $previous_image_path = $product->img_path;
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();

        $this->actingAs($this->createUser())->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNotSame($previous_image_path, $product->img_path);
        $this->assertStringStartsWith('products/', $product->img_path);
        Storage::disk('public')->assertExists($product->img_path);
        Storage::disk('public')->assertMissing($previous_image_path);
        $this->assertSame([$product->img_path], Storage::disk('public')->allFiles());
        $image_url = asset('storage/' . $product->img_path);
        $this->get(route('products.edit', $product->id))->assertOk()
            ->assertSee('src="' . $image_url . '"', false);
        $this->get(route('products.show', $product->id))->assertOk()
            ->assertSee('src="' . $image_url . '"', false);
        $this->get(route('products.index'))->assertOk()->assertSee('src="' . $image_url . '"', false);
    }

    public function testAnImageCanBeAddedToAProductWithoutAnImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '登録済み商品');
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();

        $this->actingAs($this->createUser())->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasNoErrors();
        $product->refresh();
        Storage::disk('public')->assertExists($product->img_path);
        $this->assertDatabaseCount('products', 1);
    }

    /**
     * @dataProvider invalidUpdateDataProvider
     */
    public function testInvalidInputKeepsTheOriginalProductAndImage(array $invalid_data, array $error_fields): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $product_data = array_replace($this->validProductData($company), $invalid_data);

        $this->actingAs($this->createUser())->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasErrors($error_fields);
        $this->assertDatabaseHas('products', $product->getAttributes());
        $this->assertSame([$product->img_path], Storage::disk('public')->allFiles());
    }

    public static function invalidUpdateDataProvider(): array {
        return [
            'required fields' => [
                ['product_name' => '', 'company_id' => '', 'price' => '', 'stock' => ''],
                ['product_name', 'company_id', 'price', 'stock'],
            ],
            'unknown manufacturer' => [['company_id' => 999], ['company_id']],
            'invalid numbers' => [['price' => '１３０', 'stock' => -1], ['price', 'stock']],
            'integer overflow' => [['price' => '2147483648', 'stock' => '2147483648'], ['price', 'stock']],
            'text column byte limit' => [['comment' => str_repeat('あ', 22000)], ['comment']],
        ];
    }

    public function testInvalidUploadKeepsTheOriginalProductAndImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = UploadedFile::fake()->createWithContent('document.txt', 'Not an image.');

        $this->actingAs($this->createUser())->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasErrors('img_path');
        $this->assertDatabaseHas('products', $product->getAttributes());
        $this->assertSame([$product->img_path], Storage::disk('public')->allFiles());
    }

    public function testValidationErrorsKeepEditedTextAndManufacturerInputs(): void {
        $company = $this->createCompany('メーカーA');
        $new_company = $this->createCompany('メーカーB');
        $product = $this->createProductWithImage($company);
        $product_data = array_replace($this->validProductData($new_company), ['price' => 'invalid']);
        $this->actingAs($this->createUser());

        $this->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasErrors('price');
        $response = $this->get(route('products.edit', $product->id));
        $response->assertOk()->assertSee('value="更新済み商品"', false)->assertSee('更新後のコメント');
        $this->assertMatchesRegularExpression(
            '/<option\s+value="' . $new_company->id . '"\s+selected\b/',
            $response->getContent()
        );
        $this->assertDatabaseHas('products', $product->getAttributes());
    }

    public function testMissingOrInvalidProductIdsReturnNotFound(): void {
        $company = $this->createCompany('メーカーA');
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();
        $this->actingAs($this->createUser());

        $this->get(route('products.edit', 999))->assertNotFound();
        $this->put(route('products.update', 999), $product_data)->assertNotFound();
        $this->get('/products/not-a-number/edit')->assertNotFound();
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function testDatabaseFailureRollsBackTheUpdateAndRemovesOnlyTheNewImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();
        $this->actingAs($this->createUser());
        Product::updated(function () {
            throw new RuntimeException('Test database update failure.');
        });

        try {
            $response = $this->put(route('products.update', $product->id), $product_data);
        } finally {
            Product::flushEventListeners();
        }

        $response->assertRedirect(route('products.edit', $product->id))->assertSessionHasErrors('update');
        $response->assertSessionHasInput('product_name', $product_data['product_name']);
        $response->assertSessionMissing('_old_input.img_path');
        $this->assertDatabaseHas('products', $product->getAttributes());
        $this->assertSame([$product->img_path], Storage::disk('public')->allFiles());
    }

    public function testImageWriteFailureKeepsTheOriginalProductAndImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();
        $original_disk = Storage::disk('public');
        $failed_disk = Mockery::mock(FilesystemAdapter::class);
        $failed_disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($failed_disk);

        $this->actingAs($this->createUser())->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasErrors('update');
        $this->assertDatabaseHas('products', $product->getAttributes());
        $original_disk->assertExists($product->img_path);
    }

    public function testOldImageCleanupFailureDoesNotDiscardTheUpdatedImage(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProductWithImage($company);
        $previous_image_path = $product->img_path;
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();
        $original_disk = Storage::disk('public');
        $failed_disk = Mockery::mock($original_disk);
        $failed_disk->shouldReceive('delete')->with($previous_image_path)->once()
            ->andThrow(new RuntimeException('Test old image cleanup failure.'));
        Storage::shouldReceive('disk')->with('public')->andReturn($failed_disk);

        $this->actingAs($this->createUser())->put(route('products.update', $product->id), $product_data)
            ->assertRedirect(route('products.edit', $product->id))->assertSessionHasNoErrors()
            ->assertSessionHas('status', '商品情報を更新しました。');
        $product->refresh();
        $this->assertNotSame($previous_image_path, $product->img_path);
        $original_disk->assertExists($product->img_path);
        $this->assertSame('更新済み商品', $product->product_name);
    }

    public function testDetailsLinkToEditAndEditReturnsToDetails(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '登録済み商品');
        $this->actingAs($this->createUser());

        $this->get(route('products.show', $product->id))->assertOk()
            ->assertSee('href="' . route('products.edit', $product->id) . '"', false)->assertSee('編集');
        $this->get(route('products.edit', $product->id))->assertOk()
            ->assertSee('href="' . route('products.show', $product->id) . '" class="btn product-form__back"', false)
            ->assertSee('戻る');
    }

    public function testEditFormEscapesStoredNamesAndComments(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, '<script>alert("name")</script>', [
            'comment' => '</textarea><script>alert("comment")</script>',
        ]);

        $response = $this->actingAs($this->createUser())->get(route('products.edit', $product->id));

        $response->assertOk()->assertSee($product->product_name)->assertSee($product->comment);
        $response->assertDontSee($product->product_name, false)->assertDontSee($product->comment, false);
    }

    protected function validProductData(Company $company): array {
        return [
            'product_name' => '更新済み商品',
            'company_id' => $company->id,
            'price' => 250,
            'stock' => 8,
            'comment' => '更新後のコメント',
        ];
    }

    protected function createProductWithImage(Company $company): Product {
        $image_path = 'products/original.png';
        Storage::disk('public')->put($image_path, $this->createImage()->getContent());

        return $this->createProduct($company, '登録済み商品', [
            'img_path' => $image_path,
            'comment' => '更新前のコメント',
        ]);
    }

    protected function createImage(): UploadedFile {
        $image_data = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        );

        return UploadedFile::fake()->createWithContent('updated.png', $image_data);
    }
}
