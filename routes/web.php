<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CampingSpotController as AdminCampingSpotController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ComboController as AdminComboController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeliveryScheduleController as AdminDeliveryScheduleController;
use App\Http\Controllers\Admin\EditorImageController as AdminEditorImageController;
use App\Http\Controllers\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductContentController as AdminProductContentController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\ReferralController as AdminReferralController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ServiceLocationController as AdminServiceLocationController;
use App\Http\Controllers\Admin\SiteSettingController as AdminSiteSettingController;
use App\Http\Controllers\Admin\StaticPageController as AdminStaticPageController;
use App\Http\Controllers\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shipper\AuthController as ShipperAuthController;
use App\Http\Controllers\Shipper\ScheduleController as ShipperScheduleController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\ComboController;
use App\Http\Controllers\Shop\FeedbackController;
use App\Http\Controllers\Shop\GuestAuthController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\OrderLookupController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\ReviewInviteController;
use App\Http\Controllers\Shop\SitemapController;
use App\Http\Controllers\Shop\StaticPageController;
use App\Models\StaticPage;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [ProductController::class, 'home'])->name('home');

// Sitemap động cho bot tìm kiếm (Epic 3) — sinh từ DB, cache 1h
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Mặt tiền khách
Route::get('/thiet-bi', [ProductController::class, 'index'])->name('products');
Route::get('/thiet-bi/{product:slug}', [ProductController::class, 'show'])->name('products.show');
// Tồn kho theo khoảng ngày (bopcamping-1z1) — fetch từ trang chi tiết khi chọn ngày
Route::get('/thiet-bi/{product:slug}/kha-dung', [ProductController::class, 'availability'])->name('products.availability')->middleware('throttle:60,1');
// Tồn kho gợi ý "thường thuê cùng" + combo banner theo khoảng ngày (Combo P3, AC-9)
Route::get('/thiet-bi/{product:slug}/goi-y-kha-dung', [ProductController::class, 'suggestionAvailability'])->name('products.suggestions')->middleware('throttle:60,1');
// Khách vãng lai cũng gửi được — mọi đánh giá vào 'pending' chờ admin duyệt
Route::post('/thiet-bi/{product:slug}/danh-gia', [ReviewController::class, 'store'])->name('reviews.store')
    ->middleware('throttle:10,1');
// Combo thuê trọn bộ (bopcamping-6he)
Route::get('/combos', [ComboController::class, 'index'])->name('combos');
Route::get('/combos/{slug}', [ComboController::class, 'show'])->name('combos.show');
// Check tồn kho realtime theo khoảng ngày (Case 4) — fetch từ trang chi tiết
Route::get('/combos/{slug}/kha-dung', [ComboController::class, 'availability'])->name('combos.availability')->middleware('throttle:60,1');
Route::get('/gio-thue', [CartController::class, 'index'])->name('cart');
// Làm tươi giỏ: trả giá/vị trí mới nhất theo ids (giỏ ở localStorage có thể đã cũ)
Route::get('/gio-thue/lam-tuoi', [CartController::class, 'refresh'])->name('cart.refresh')->middleware('throttle:60,1');
// Cart combo detection (Case 3, P4) — POST vì payload giỏ dạng mảng; chạy lại mỗi khi giỏ đổi
Route::post('/gio-thue/goi-y-combo', [CartController::class, 'suggestion'])->name('cart.suggestion')->middleware('throttle:60,1');
Route::post('/gio-thue/goi-y-combo/da-chuyen', [CartController::class, 'suggestionConverted'])->name('cart.suggestion.converted')->middleware('throttle:30,1');
Route::post('/dat-hang', [OrderController::class, 'store'])->name('order.store')->middleware('throttle:20,1');
Route::get('/tra-cuu', [OrderLookupController::class, 'index'])->name('lookup');
// Trang giới thiệu — nội dung sửa trong admin "Trang nội dung" (Epic 4)
Route::get('/gioi-thieu', [StaticPageController::class, 'about'])->name('about');
// Trang chính sách — DRY: mỗi slug 1 route top-level, cùng controller policy()
foreach (array_keys(StaticPage::POLICIES) as $policySlug) {
    Route::get('/'.$policySlug, [StaticPageController::class, 'policy'])
        ->defaults('slug', $policySlug)
        ->name('policy.'.$policySlug);
}
// Góp ý trải nghiệm website — widget nổi mọi trang (Epic 2), throttle chống spam
Route::post('/gop-y', [FeedbackController::class, 'store'])->name('feedback.store')->middleware('throttle:5,1');
// Đánh giá sau chuyến đi qua link token (không cần đăng nhập)
Route::get('/danh-gia/{token}', [ReviewInviteController::class, 'show'])->name('review.invite');
Route::post('/danh-gia/{token}', [ReviewInviteController::class, 'store'])->name('review.invite.store')->middleware('throttle:10,1');
// Admin — auth
// Không dùng middleware('guest') vì shop user đang login sẽ bị redirect sang /login Breeze
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.store')->middleware('throttle:10,1');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Shipper — auth + khu vực riêng (bopcamping-lsch, adr_shipper_role_and_access)
// Tách hẳn khỏi form login admin: bopcamping-vo4 sẽ đổi admin sang HTTP Basic Auth.
Route::get('/shipper/dang-nhap', [ShipperAuthController::class, 'showLogin'])->name('shipper.login');
Route::post('/shipper/dang-nhap', [ShipperAuthController::class, 'login'])->name('shipper.login.store')->middleware('throttle:10,1');
Route::post('/shipper/dang-xuat', [ShipperAuthController::class, 'logout'])->name('shipper.logout');

Route::middleware(['shipper'])->prefix('shipper')->name('shipper.')->group(function () {
    Route::get('/', fn () => redirect()->route('shipper.schedule'));
    // Chỉ đơn được gán cho chính shipper đang đăng nhập (kẹp trong controller).
    Route::get('/lich-giao', [ShipperScheduleController::class, 'index'])->name('schedule');
    // Shipper tự đánh dấu — chỉ trên đơn được gán cho mình (kiểm trong controller).
    Route::patch('/don/{order}/da-giao', [ShipperScheduleController::class, 'markDelivered'])->name('orders.delivered')->middleware('throttle:60,1');
    Route::patch('/don/{order}/da-thu', [ShipperScheduleController::class, 'markCollected'])->name('orders.collected')->middleware('throttle:60,1');
    // Shipper thu hộ tiền thuê / cọc, và trả cọc lại cho khách (bopcamping-lvw3)
    Route::patch('/don/{order}/thu/{kind}', [ShipperScheduleController::class, 'collect'])->name('orders.collect')->middleware('throttle:60,1');
    Route::patch('/don/{order}/hoan-coc', [ShipperScheduleController::class, 'refundDeposit'])->name('orders.refund')->middleware('throttle:60,1');
});

// Admin — panel (bảo vệ bằng middleware 'admin')
// Chỉ dùng 'admin' (EnsureAdmin đã check auth bên trong, redirect về /admin/login)
Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Thống kê đơn + doanh thu theo ngày (bopcamping-h1s)
    Route::get('/stats', [AdminStatsController::class, 'index'])->name('stats');

    // Tài chính: vốn, thu-chi, lợi nhuận, hoàn vốn + quản lý khoản chi (bopcamping-n4qy).
    // Route chi phí chuyển từ StatsController sang đây cùng với form nhập — giữ nguyên
    // tên route admin.expenses.* nên FE và test cũ không phải sửa đường dẫn.
    // Mọi admin đều xem VÀ sửa được số liệu thu chi (bopcamping-xlmy) — phân quyền
    // super admin đã bỏ theo yêu cầu chủ shop.
    Route::get('/tai-chinh', [AdminFinanceController::class, 'index'])->name('finance');
    Route::post('/expenses', [AdminFinanceController::class, 'storeExpense'])->name('expenses.store')->middleware('throttle:60,1');
    Route::put('/expenses/{expense}', [AdminFinanceController::class, 'updateExpense'])->name('expenses.update')->middleware('throttle:60,1');
    Route::delete('/expenses/{expense}', [AdminFinanceController::class, 'destroyExpense'])->name('expenses.destroy');

    Route::post('/von-gop', [AdminFinanceController::class, 'storeCapital'])->name('capital.store')->middleware('throttle:60,1');
    Route::put('/von-gop/{capital}', [AdminFinanceController::class, 'updateCapital'])->name('capital.update')->middleware('throttle:60,1');
    Route::delete('/von-gop/{capital}', [AdminFinanceController::class, 'destroyCapital'])->name('capital.destroy');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrderController::class, 'updateStatus'])->name('orders.update');
    // Per-store: đổi cửa hàng của đơn (kiểm tồn store đích)
    Route::patch('/orders/{order}/location', [AdminOrderController::class, 'changeLocation'])->name('orders.location')->middleware('throttle:30,1');
    // Đổi lịch thuê (kiểm tồn kho khoảng mới + tính lại tiền + mail báo khách) — bopcamping-5hjm
    Route::patch('/orders/{order}/dates', [AdminOrderController::class, 'changeDates'])->name('orders.dates')->middleware('throttle:30,1');
    // Đánh dấu tình trạng chuyển tiền (đã chuyển cọc / chuyển hết / chưa chuyển) — bopcamping-7be
    Route::patch('/orders/{order}/payment', [AdminOrderController::class, 'updatePayment'])->name('orders.payment');
    // Phụ phí giao/trả ngoài khung giờ — admin nhập tay (Phase 2 turnaround, bopcamping-h4to)
    Route::patch('/orders/{order}/extra-fee', [AdminOrderController::class, 'updateExtraFee'])->name('orders.fee')->middleware('throttle:30,1');
    // Hoàn cọc khi đơn đã trả (đã hoàn / chưa hoàn + lý do) — bopcamping-7be
    Route::patch('/orders/{order}/refund', [AdminOrderController::class, 'updateRefund'])->name('orders.refund');
    // Chốt giờ giao/thu + ghi chú nội bộ cho shipper (bopcamping-5xir, prd_delivery_schedule)
    Route::patch('/orders/{order}/schedule', [AdminOrderController::class, 'updateSchedule'])->name('orders.schedule')->middleware('throttle:30,1');

    // Lịch giao/thu theo ngày cho shipper (bopcamping-rtkh, prd_delivery_schedule)
    Route::get('/lich-giao', [AdminDeliveryScheduleController::class, 'index'])->name('schedule');
    // Gán shipper cho từng lượt + gán cả ngày (bopcamping-yc7d)
    Route::patch('/lich-giao/don/{order}/shipper', [AdminDeliveryScheduleController::class, 'assign'])->name('schedule.assign')->middleware('throttle:60,1');
    Route::post('/lich-giao/gan-tat-ca', [AdminDeliveryScheduleController::class, 'assignAll'])->name('schedule.assignAll')->middleware('throttle:30,1');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    // Upload ảnh chèn vào nội dung rich text (TipTap) — dùng chung cho mọi màn soạn thảo
    Route::post('/editor/images', [AdminEditorImageController::class, 'store'])->name('editor.images.store')->middleware('throttle:60,1');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products');
    // Thêm/sửa sản phẩm — màn hình riêng (thay popup cũ). 'create' đặt trước {product} để không nuốt route.
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/sua', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
    // Nội dung chi tiết (setup/mô tả lớn) — màn soạn thảo riêng, editor full-width (Epic 1)
    Route::get('/products/{product}/noi-dung', [AdminProductContentController::class, 'edit'])->name('products.content.edit');
    Route::put('/products/{product}/noi-dung', [AdminProductContentController::class, 'update'])->name('products.content.update')->middleware('throttle:30,1');
    Route::post('/products/{product}/images', [AdminProductController::class, 'storeImage'])->name('products.images.store')->middleware('throttle:60,1');
    Route::post('/products/{product}/images/attach', [AdminProductController::class, 'attachImages'])->name('products.images.attach')->middleware('throttle:60,1');
    Route::post('/products/{product}/images/reorder', [AdminProductController::class, 'reorderImages'])->name('products.images.reorder')->middleware('throttle:120,1');
    Route::delete('/products/{product}/images/{image}', [AdminProductController::class, 'destroyImage'])->name('products.images.destroy');

    // Combo thuê trọn bộ — CRUD + ảnh (bopcamping-s9d)
    Route::get('/combos', [AdminComboController::class, 'index'])->name('combos');
    Route::post('/combos', [AdminComboController::class, 'store'])->name('combos.store')->middleware('throttle:30,1');
    Route::put('/combos/{combo}', [AdminComboController::class, 'update'])->name('combos.update')->middleware('throttle:30,1');
    Route::delete('/combos/{combo}', [AdminComboController::class, 'destroy'])->name('combos.destroy');
    Route::post('/combos/{combo}/images', [AdminComboController::class, 'storeImage'])->name('combos.images.store')->middleware('throttle:60,1');
    Route::post('/combos/{combo}/images/attach', [AdminComboController::class, 'attachImages'])->name('combos.images.attach')->middleware('throttle:60,1');
    Route::post('/combos/{combo}/images/reorder', [AdminComboController::class, 'reorderImages'])->name('combos.images.reorder')->middleware('throttle:120,1');
    Route::delete('/combos/{combo}/images/{image}', [AdminComboController::class, 'destroyImage'])->name('combos.images.destroy');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store')->middleware('throttle:30,1');
    // Tạo nhanh KHÁCH HÀNG (chỉ tên + SĐT + email, không mật khẩu) — bopcamping-kw6q.
    Route::post('/users/customers', [AdminUserController::class, 'storeCustomer'])->name('users.customers.store')->middleware('throttle:30,1');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update')->middleware('throttle:30,1');
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role')->middleware('throttle:30,1');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy')->middleware('throttle:30,1');

    // Khuyến mãi: cấu hình + voucher + giới thiệu
    Route::get('/promotion', [AdminPromotionController::class, 'index'])->name('promotion');
    Route::put('/promotion', [AdminPromotionController::class, 'update'])->name('promotion.update');
    // Bậc giảm giá thuê dài ngày — sync toàn bảng (bopcamping-e36e).
    Route::put('/promotion/duration-tiers', [AdminPromotionController::class, 'updateDurationTiers'])->name('promotion.duration-tiers');

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
    // Góp ý của khách: đọc + phản hồi qua email (Epic 2)
    Route::get('/gop-y', [AdminFeedbackController::class, 'index'])->name('feedbacks');
    Route::patch('/gop-y/{feedback}', [AdminFeedbackController::class, 'reply'])->name('feedbacks.reply')->middleware('throttle:30,1');

    // Trang nội dung (giới thiệu...) — title + ảnh bìa + nội dung TipTap (Epic 4)
    Route::get('/pages', [AdminStaticPageController::class, 'index'])->name('pages');
    Route::get('/pages/{staticPage}', [AdminStaticPageController::class, 'edit'])->name('pages.edit');
    Route::put('/pages/{staticPage}', [AdminStaticPageController::class, 'update'])->name('pages.update')->middleware('throttle:30,1');

    // Cài đặt shop — thông tin liên hệ/mạng xã hội (ADR home_faq_contact)
    Route::get('/settings', [AdminSiteSettingController::class, 'edit'])->name('settings');
    Route::put('/settings', [AdminSiteSettingController::class, 'update'])->name('settings.update')->middleware('throttle:30,1');

    // FAQ trang chủ — CRUD (ADR home_faq_contact)
    Route::get('/faqs', [AdminFaqController::class, 'index'])->name('faqs');
    Route::post('/faqs', [AdminFaqController::class, 'store'])->name('faqs.store')->middleware('throttle:30,1');
    Route::put('/faqs/{faq}', [AdminFaqController::class, 'update'])->name('faqs.update')->middleware('throttle:30,1');
    Route::delete('/faqs/{faq}', [AdminFaqController::class, 'destroy'])->name('faqs.destroy');

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
    Route::get('/tai-khoan/dat-lai/{order}/kha-dung', [AccountController::class, 'reorderAvailability'])->name('account.reorder.availability')->middleware('throttle:60,1');

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
