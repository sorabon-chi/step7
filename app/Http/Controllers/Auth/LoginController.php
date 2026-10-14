<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function validateLogin(Request $request): void {
        $request->validate([
            'email' => ['required', 'string', 'email', 'regex:/\A[\x21-\x7E]+\z/'],
            'password' => ['required', 'string', 'regex:/\A[\x21-\x7E]+\z/'],
        ]);
    }

    protected function loggedOut(Request $request): RedirectResponse {
        return redirect()->route('login');
    }

    protected function attemptLogin(Request $request): bool {
        return $this->guard()->attempt($this->credentials($request), false);
    }

    protected function authenticated(Request $request, $user): RedirectResponse {
        $request->session()->forget('url.intended');

        return redirect()->route('products.index');
    }
}
