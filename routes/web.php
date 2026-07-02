<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CampingSpotController as AdminCampingSpotController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ComboController as AdminComboController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\ReferralController as AdminReferralController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ServiceLocationController as AdminServiceLocationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\GuestAuthController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\OrderLookupController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\ReviewInviteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [ProductController::class, 'home'])->name('home');

// Mặt tiền khách
Route::get('/thiet-bi', [ProductController::class, 'index'])->name('products');
Route::get('/thiet-bi/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
// Khách vãng lai cũng gửi được — mọi đánh giá vào 'pending' chờ admin duyệt
Route::post('/thiet-bi/{product}/danh-gia', [ReviewController::class, 'store'])->whereNumber('product')->name('reviews.store')
    ->middleware('throttle:10,1');
Route::get('/gio-thue', [CartController::class, 'index'])->name('cart');
// Làm tươi giỏ: trả giá/vị trí mới nhất theo ids (giỏ ở localStorage có thể đã cũ)
Route::get('/gio-thue/lam-tuoi', [CartController::class, 'refresh'])->name('cart.refresh')->middleware('throttle:60,1');
Route::post('/dat-hang', [OrderController::class, 'store'])->name('order.store')->middleware('throttle:20,1');
Route::get('/tra-cuu', [OrderLookupController::class, 'index'])->name('lookup');
// Đánh giá sau chuyến đi qua link token (không cần đăng nhập)
Route::get('/danh-gia/{token}', [ReviewInviteController::class, 'show'])->name('review.invite');
Route::post('/danh-gia/{token}', [ReviewInviteController::class, 'store'])->name('review.invite.store')->middleware('throttle:10,1');
// Admin — auth
// Không dùng middleware('guest') vì shop user đang login sẽ bị redirect sang /login Breeze
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.store')->middleware('throttle:10,1');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Admin — panel (bảo vệ bằng middleware 'admin')
// Chỉ dùng 'admin' (EnsureAdmin đã check auth bên trong, redirect về /admin/login)
Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
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
    Route::post('/products/{product}/images', [AdminProductController::class, 'storeImage'])->name('products.images.store')->middleware('throttle:60,1');
    Route::delete('/products/{product}/images/{image}', [AdminProductController::class, 'destroyImage'])->name('products.images.destroy');

    // Combo thuê trọn bộ — CRUD + ảnh (bopcamping-s9d)
    Route::get('/combos', [AdminComboController::class, 'index'])->name('combos');
    Route::post('/combos', [AdminComboController::class, 'store'])->name('combos.store')->middleware('throttle:30,1');
    Route::put('/combos/{combo}', [AdminComboController::class, 'update'])->name('combos.update')->middleware('throttle:30,1');
    Route::delete('/combos/{combo}', [AdminComboController::class, 'destroy'])->name('combos.destroy');
    Route::post('/combos/{combo}/images', [AdminComboController::class, 'storeImage'])->name('combos.images.store')->middleware('throttle:60,1');
    Route::delete('/combos/{combo}/images/{image}', [AdminComboController::class, 'destroyImage'])->name('combos.images.destroy');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store')->middleware('throttle:30,1');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update')->middleware('throttle:30,1');
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role')->middleware('throttle:30,1');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy')->middleware('throttle:30,1');

    // Khuyến mãi: cấu hình + voucher + giới thiệu
    Route::get('/promotion', [AdminPromotionController::class, 'index'])->name('promotion');
    Route::put('/promotion', [AdminPromotionController::class, 'update'])->name('promotion.update');

    Route::get('/vouchers', [AdminVoucherController::class, 'index'])->name('vouchers');
    Route::post('/vouchers', [AdminVoucherController::class, 'store'])->name('vouchers.store')->middleware('throttle:30,1');
    Route::patch('/vouchers/{voucher}/revoke', [AdminVoucherController::class, 'revoke'])->name('vouchers.revoke');

    Route::get('/referrals', [AdminReferralController::class, 'index'])->name('referrals');

    // Duyệt đánh giá
    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews');
    Route::patch('/reviews/{review}', [AdminReviewController::class, 'update'])->name('reviews.update')->middleware('throttle:60,1');

    // Vị trí phục vụ (Vinh, Hà Nội...) — quản lý ngay trong màn Điểm cắm trại (không có trang riêng)
    Route::post('/service-locations', [AdminServiceLocationController::class, 'store'])->name('service-locations.store')->middleware('throttle:30,1');
    Route::put('/service-locations/{serviceLocation}', [AdminServiceLocationController::class, 'update'])->name('service-locations.update')->middleware('throttle:30,1');
    Route::delete('/service-locations/{serviceLocation}', [AdminServiceLocationController::class, 'destroy'])->name('service-locations.destroy')->middleware('throttle:30,1');

    // Banner (hero + promo) — CRUD
    Route::get('/banners', [AdminBannerController::class, 'index'])->name('banners');
    Route::post('/banners', [AdminBannerController::class, 'store'])->name('banners.store')->middleware('throttle:30,1');
    Route::put('/banners/{banner}', [AdminBannerController::class, 'update'])->name('banners.update')->middleware('throttle:30,1');
    Route::delete('/banners/{banner}', [AdminBannerController::class, 'destroy'])->name('banners.destroy')->middleware('throttle:30,1');

    // Điểm cắm trại (Cẩm nang) — CRUD + media
    Route::get('/camping-spots', [AdminCampingSpotController::class, 'index'])->name('camping-spots');
    Route::post('/camping-spots', [AdminCampingSpotController::class, 'store'])->name('camping-spots.store')->middleware('throttle:30,1');
    Route::put('/camping-spots/{campingSpot}', [AdminCampingSpotController::class, 'update'])->name('camping-spots.update')->middleware('throttle:30,1');
    Route::delete('/camping-spots/{campingSpot}', [AdminCampingSpotController::class, 'destroy'])->name('camping-spots.destroy')->middleware('throttle:30,1');
    Route::post('/camping-spots/{campingSpot}/media', [AdminCampingSpotController::class, 'storeMedia'])->name('camping-spots.media.store')->middleware('throttle:60,1');
    Route::delete('/camping-spots/{campingSpot}/media/{media}', [AdminCampingSpotController::class, 'destroyMedia'])->name('camping-spots.media.destroy')->middleware('throttle:60,1');
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

// Auth khách: SĐT + email (+ tên tuỳ ý), xác thực OTP qua email (chỉ lần đầu)
// Tra SĐT → tự điền email + tên hiện tại (throttle chống dò số hàng loạt)
Route::get('/dang-nhap/tra-thong-tin', [GuestAuthController::class, 'lookup'])
    ->name('guest.lookup')
    ->middleware('throttle:30,1');
Route::post('/dang-nhap', [GuestAuthController::class, 'store'])
    ->name('guest.login')
    ->middleware('throttle:10,1');
Route::post('/dang-nhap/xac-thuc', [GuestAuthController::class, 'verifyOtp'])
    ->name('guest.login.verify')
    ->middleware('throttle:10,1');
Route::post('/dang-xuat', [GuestAuthController::class, 'destroy'])
    ->name('guest.logout')
    ->middleware('auth');

require __DIR__.'/auth.php';
