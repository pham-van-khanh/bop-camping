<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\GuestAuthController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\OrderLookupController;
use App\Http\Controllers\Shop\ProductController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [ProductController::class, 'home'])->name('home');

// Mặt tiền khách
Route::get('/thiet-bi', [ProductController::class, 'index'])->name('products');
Route::get('/thiet-bi/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
Route::get('/gio-thue', fn () => Inertia::render('Cart'))->name('cart');
Route::post('/dat-hang', [OrderController::class, 'store'])->name('order.store')->middleware('throttle:20,1');
Route::get('/tra-cuu', [OrderLookupController::class, 'index'])->name('lookup');
// Admin — auth
// Không dùng middleware('guest') vì shop user đang login sẽ bị redirect sang /login Breeze
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.store')->middleware('throttle:10,1');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Admin — panel (bảo vệ bằng middleware 'admin')
// Chỉ dùng 'admin' (EnsureAdmin đã check auth bên trong, redirect về /admin/login)
Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.orders'));
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::patch('/orders/{order}', [AdminOrderController::class, 'updateStatus'])->name('orders.update');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{product}/images', [AdminProductController::class, 'storeImage'])->name('products.images.store');
    Route::delete('/products/{product}/images/{image}', [AdminProductController::class, 'destroyImage'])->name('products.images.destroy');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store')->middleware('throttle:30,1');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update')->middleware('throttle:30,1');
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role')->middleware('throttle:30,1');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy')->middleware('throttle:30,1');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Tài khoản của tôi (khách) — thống kê đơn + mã giới thiệu
    Route::get('/tai-khoan', [AccountController::class, 'index'])->name('account');

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
