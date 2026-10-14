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

class ProductStoreTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        Storage::fake('public');
    }

    public function testGuestsCannotOpenOrSubmitTheRegistrationForm(): void {
        $company = $this->createCompany('メーカーA');

        $this->get(route('products.create'))->assertRedirect(route('login'));
        $this->post(route('products.store'), $this->validProductData($company))
            ->assertRedirect(route('login'));
        $this->assertDatabaseCount('products', 0);
    }

    public function testCreateFormShowsDatabaseCompaniesAndRequiredFields(): void {
        $this->createCompany('メーカーA');
        $this->createCompany('メーカーB');

        $response = $this->actingAs($this->createUser())->get(route('products.create'));

        $response->assertOk()->assertViewIs('products.create');
        $response->assertViewHas('companies', fn ($companies) => $companies->count() === 2);
        $response->assertSee('商品新規登録画面')->assertSee('メーカーA')->assertSee('メーカーB');
        $response->assertSee('enctype="multipart/form-data"', false);
        foreach (['product_name', 'company_id', 'price', 'stock', 'comment', 'img_path'] as $field_name) {
            $response->assertSee('name="' . $field_name . '"', false);
        }
        $this->assertSame(4, substr_count($response->getContent(), 'class="product-form__required"'));
    }

    public function testProductRegistrationReturnsToTheCreatePage(): void {
        $company = $this->createCompany('メーカーA');
        $product_data = $this->validProductData($company);

        $response = $this->actingAs($this->createUser())->post(route('products.store'), $product_data);

        $response->assertRedirect(route('products.create'))->assertSessionHas('status', '商品を登録しました。');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', $product_data + ['img_path' => null]);
        $product = Product::query()->firstOrFail();
        $this->assertNotNull($product->created_at);
        $this->assertNotNull($product->updated_at);
        $this->get(route('products.create'))->assertOk()->assertSee('商品を登録しました。');
    }

    public function testImageAndCommentCanBeOmitted(): void {
        $company = $this->createCompany('メーカーA');
        $product_data = $this->validProductData($company);
        unset($product_data['comment']);

        $this->actingAs($this->createUser())->post(route('products.store'), $product_data)
            ->assertRedirect(route('products.create'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', $product_data + ['comment' => null, 'img_path' => null]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function testUploadedImageIsStoredAndDisplayedOnTheListAndDetails(): void {
        $company = $this->createCompany('メーカーA');
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();

        $this->actingAs($this->createUser())->post(route('products.store'), $product_data)
            ->assertRedirect(route('products.create'))->assertSessionHasNoErrors();

        $product = Product::query()->firstOrFail();
        $this->assertStringStartsWith('products/', $product->img_path);
        $this->assertStringEndsWith('.png', $product->img_path);
        $this->assertNotSame('products/cola.png', $product->img_path);
        Storage::disk('public')->assertExists($product->img_path);
        $image_url = asset('storage/' . $product->img_path);
        $this->get(route('products.index'))->assertOk()->assertSee('src="' . $image_url . '"', false);
        $this->get(route('products.show', $product->id))->assertOk()
            ->assertSee('src="' . $image_url . '"', false)->assertSee($product->comment);
    }

    public function testZeroPriceAndStockAreAllowed(): void {
        $company = $this->createCompany('メーカーA');
        $product_data = array_replace($this->validProductData($company), ['price' => 0, 'stock' => 0]);

        $this->actingAs($this->createUser())->post(route('products.store'), $product_data)
            ->assertRedirect(route('products.create'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', $product_data);
    }

    /**
     * @dataProvider invalidProductDataProvider
     */
    public function testInvalidInputDoesNotCreateProducts(array $invalid_data, array $error_fields): void {
        $company = $this->createCompany('メーカーA');
        $product_data = array_replace($this->validProductData($company), $invalid_data);

        $this->actingAs($this->createUser())->post(route('products.store'), $product_data)
            ->assertRedirect(route('products.create'))->assertSessionHasErrors($error_fields);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function invalidProductDataProvider(): array {
        return [
            'required fields' => [
                ['product_name' => '', 'company_id' => '', 'price' => '', 'stock' => ''],
                ['product_name', 'company_id', 'price', 'stock'],
            ],
            'unknown manufacturer' => [['company_id' => 999], ['company_id']],
            'long product name' => [['product_name' => str_repeat('あ', 256)], ['product_name']],
            'full-width numbers' => [['price' => '１３０', 'stock' => '１０'], ['price', 'stock']],
            'decimal numbers' => [['price' => '130.5', 'stock' => '1.5'], ['price', 'stock']],
            'negative numbers' => [['price' => -1, 'stock' => -1], ['price', 'stock']],
            'integer overflow' => [['price' => '2147483648', 'stock' => '2147483648'], ['price', 'stock']],
            'invalid comment type' => [['comment' => ['invalid']], ['comment']],
            'text column byte limit' => [['comment' => str_repeat('あ', 22000)], ['comment']],
        ];
    }

    /**
     * @dataProvider invalidImageProvider
     */
    public function testNonImageUploadsAreRejected(string $file_name, string $file_content): void {
        $company = $this->createCompany('メーカーA');
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = UploadedFile::fake()->createWithContent($file_name, $file_content);

        $this->actingAs($this->createUser())->post(route('products.store'), $product_data)
            ->assertRedirect(route('products.create'))->assertSessionHasErrors('img_path');
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function invalidImageProvider(): array {
        return [
            'text file' => ['document.txt', 'This is not an image.'],
            'svg file' => ['image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            'php file' => ['script.php', '<?php echo "invalid upload";'],
        ];
    }

    public function testValidationErrorsKeepTextAndManufacturerInputs(): void {
        $company = $this->createCompany('メーカーA');
        $product_data = array_replace($this->validProductData($company), ['price' => 'invalid']);

        $this->actingAs($this->createUser())->post(route('products.store'), $product_data)
            ->assertRedirect(route('products.create'))->assertSessionHasErrors('price');
        $response = $this->get(route('products.create'));
        $response->assertOk()
            ->assertSee('value="' . $product_data['product_name'] . '"', false)
            ->assertSee($product_data['comment']);
        $this->assertMatchesRegularExpression(
            '/<option\s+value="' . $company->id . '"\s+selected\b/',
            $response->getContent()
        );
    }

    public function testDatabaseFailureRollsBackTheProductAndRemovesTheUploadedImage(): void {
        $company = $this->createCompany('メーカーA');
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();
        $this->actingAs($this->createUser());
        Product::created(function () {
            throw new RuntimeException('Test database write failure.');
        });

        try {
            $response = $this->post(route('products.store'), $product_data);
        } finally {
            Product::flushEventListeners();
        }

        $response->assertRedirect(route('products.create'))->assertSessionHasErrors('registration');
        $response->assertSessionHasInput('product_name', $product_data['product_name']);
        $response->assertSessionMissing('_old_input.img_path');
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function testImageWriteFailureDoesNotCreateAProduct(): void {
        $company = $this->createCompany('メーカーA');
        $product_data = $this->validProductData($company);
        $product_data['img_path'] = $this->createImage();
        $public_disk = Mockery::mock(FilesystemAdapter::class);
        $public_disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($public_disk);

        $this->actingAs($this->createUser())->post(route('products.store'), $product_data)
            ->assertRedirect(route('products.create'))->assertSessionHasErrors('registration');
        $this->assertDatabaseCount('products', 0);
    }

    public function testListAndRegistrationFormHaveNavigationLinks(): void {
        $this->actingAs($this->createUser());

        $this->get(route('products.index'))->assertOk()
            ->assertSee('href="' . route('products.create') . '"', false)->assertSee('新規登録');
        $this->get(route('products.create'))->assertOk()
            ->assertSee('href="' . route('products.index') . '" class="btn product-form__back"', false)
            ->assertSee('戻る');
    }

    protected function validProductData(Company $company): array {
        return [
            'product_name' => 'コーラ',
            'company_id' => $company->id,
            'price' => 130,
            'stock' => 6,
            'comment' => '冷やしてお飲みください。',
        ];
    }

    protected function createImage(): UploadedFile {
        $image_data = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        );

        return UploadedFile::fake()->createWithContent('cola.png', $image_data);
    }
}
