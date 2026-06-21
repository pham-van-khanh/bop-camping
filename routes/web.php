<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

// Mặt tiền khách — hiện dùng dữ liệu mẫu phía client (chờ Models, bopcamping-iov).
Route::get('/thiet-bi', fn () => Inertia::render('Products'))->name('products');
Route::get('/thiet-bi/{id}', fn (int $id) => Inertia::render('ProductDetail', ['id' => $id]))
    ->whereNumber('id')->name('products.show');
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

require __DIR__.'/auth.php';
