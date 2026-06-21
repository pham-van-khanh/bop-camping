<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\GuestAuthController;
use App\Http\Controllers\Shop\ProductController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [ProductController::class, 'home'])->name('home');

// Mặt tiền khách
Route::get('/thiet-bi', [ProductController::class, 'index'])->name('products');
Route::get('/thiet-bi/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
Route::get('/gio-thue', fn () => Inertia::render('Cart'))->name('cart');
Route::get('/tra-cuu', fn () => Inertia::render('OrderLookup'))->name('lookup');
Route::get('/admin', fn () => Inertia::render('Admin'))->name('admin');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Auth khách: SĐT + tên (passwordless)
Route::post('/dang-nhap', [GuestAuthController::class, 'store'])
    ->name('guest.login')
    ->middleware('throttle:10,1');
Route::post('/dang-xuat', [GuestAuthController::class, 'destroy'])
    ->name('guest.logout')
    ->middleware('auth');

require __DIR__.'/auth.php';
