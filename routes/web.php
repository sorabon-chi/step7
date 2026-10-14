<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes(['reset' => false, 'confirm' => false, 'verify' => false]);

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('products.index'));
    Route::get('/home', fn () => redirect()->route('products.index'))->name('home');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product_id}/edit', [ProductController::class, 'edit'])
        ->whereNumber('product_id')->name('products.edit');
    Route::put('/products/{product_id}', [ProductController::class, 'update'])
        ->whereNumber('product_id')->name('products.update');
    Route::delete('/products/{product_id}', [ProductController::class, 'destroy'])
        ->whereNumber('product_id')->name('products.destroy');
    Route::get('/products/{product_id}', [ProductController::class, 'show'])
        ->whereNumber('product_id')->name('products.show');
});
