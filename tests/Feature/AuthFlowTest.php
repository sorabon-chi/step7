<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\DatabaseTestCase;

class AuthFlowTest extends DatabaseTestCase
{
    public function testRegistrationReturnsToLoginWithoutAutomaticLogin(): void {
        $response = $this->post(route('register'), [
            'name' => '登録ユーザー',
            'email' => 'registered@example.test',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $user = User::query()->firstOrFail();
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $user->password));

        $this->post(route('login'), [
            'email' => 'registered@example.test',
            'password' => self::TEST_PASSWORD,
        ])->assertRedirect(route('products.index'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('products.index'))->assertOk();
    }

    public function testPasswordConfirmationMismatchIsRejected(): void {
        $this->from(route('register'))->post(route('register'), [
            'name' => '登録ユーザー',
            'email' => 'registered@example.test',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => 'DifferentPassword123!',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function testRegistrationRejectsFullWidthPasswords(): void {
        $this->from(route('register'))->post(route('register'), [
            'name' => '登録ユーザー',
            'email' => 'registered@example.test',
            'password' => '全角パスワード123',
            'password_confirmation' => '全角パスワード123',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function testDuplicateEmailIsRejected(): void {
        $this->createUser();
        $this->from(route('register'))->post(route('register'), [
            'name' => '別ユーザー',
            'email' => 'student@example.test',
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function testIncorrectPasswordDoesNotAuthenticate(): void {
        $this->createUser();
        $this->from(route('login'))->post(route('login'), [
            'email' => 'student@example.test',
            'password' => 'WrongPassword123!',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function testAuthenticatedUsersAreRedirectedFromAuthenticationPages(): void {
        $this->actingAs($this->createUser());
        $this->get(route('login'))->assertRedirect(route('products.index'));
        $this->get(route('register'))->assertRedirect(route('products.index'));
        $this->get('/')->assertRedirect(route('products.index'));
        $this->get('/home')->assertRedirect(route('products.index'));
    }

    public function testLogoutReturnsToLoginAndProtectsProductList(): void {
        $this->actingAs($this->createUser())->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('products.index'))->assertRedirect(route('login'));
    }

    public function testLoginReturnsToProductListAfterOpeningAProtectedEditUrl(): void {
        $company = $this->createCompany('メーカーA');
        $product = $this->createProduct($company, 'コーラ');
        $this->createUser();
        $this->get(route('products.edit', $product->id))->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => 'student@example.test',
            'password' => self::TEST_PASSWORD,
        ])->assertRedirect(route('products.index'))->assertSessionMissing('url.intended');
        $this->assertAuthenticated();
    }

    public function testOnlyAssignmentAuthenticationFeaturesAreExposed(): void {
        foreach (['password.request', 'password.email', 'password.reset', 'password.confirm',
            'verification.notice'] as $route_name) {
            $this->assertFalse(Route::has($route_name));
        }
        $this->get('/password/reset')->assertNotFound();
        $this->get('/password/confirm')->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee('name="remember"', false)
            ->assertDontSee('Forgot Your Password?');
    }

    public function testLoginDoesNotEnableRememberLoginFromAnExtraRequestField(): void {
        $user = $this->createUser();
        $this->post(route('login'), [
            'email' => 'student@example.test',
            'password' => self::TEST_PASSWORD,
            'remember' => true,
        ])->assertRedirect(route('products.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->remember_token);
    }

    public function testRegistrationPageHasALoginBackLinkAndCamelCaseConfirmationId(): void {
        $this->get(route('register'))->assertOk()
            ->assertSee('href="' . route('login') . '" class="btn btn-info"', false)->assertSee('戻る')
            ->assertSee('id="passwordConfirm"', false)->assertDontSee('id="password-confirm"', false);
    }
}
