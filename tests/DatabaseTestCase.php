<?php

namespace Tests;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;

abstract class DatabaseTestCase extends TestCase
{
    protected const TEST_PASSWORD = 'SamplePassword123!';

    protected function setUp(): void {
        parent::setUp();

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'url' => null,
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $this->app['db']->purge('sqlite');
        $this->withoutVite();

        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    protected function createUser(): User {
        return User::registerUser([
            'name' => 'テストユーザー',
            'email' => 'student@example.test',
            'password' => self::TEST_PASSWORD,
        ]);
    }

    protected function createCompany(string $company_name): Company {
        return Company::create(['company_name' => $company_name]);
    }

    protected function createProduct(
        Company $company,
        string $product_name,
        array $product_attributes = []
    ): Product {
        return Product::create(array_merge([
            'company_id' => $company->id,
            'product_name' => $product_name,
            'price' => 130,
            'stock' => 10,
            'comment' => null,
            'img_path' => null,
        ], $product_attributes));
    }
}
