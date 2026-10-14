<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\OrderLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trang /tra-cuu đứng riêng — GIỮ CÔNG KHAI cho khách vãng lai (link "Theo dõi đơn này"
 * sau checkout; khách OTP không đăng nhập được vào /tai-khoan bằng Breeze login).
 * Khách đã đăng nhập có section tra cứu ngay trong /tai-khoan (bopcamping-7w8).
 */
class OrderLookupController extends Controller
{
    public function __construct(private OrderLookupService $lookup) {}

    public function index(Request $request): Response|RedirectResponse
    {
        // SĐT không được nằm trên URL (đo lường gửi nguyên URL đi) — xem redirectWithoutPhone.
        if ($redirect = $this->lookup->redirectWithoutPhone($request)) {
            return $redirect;
        }
        $phone = $this->lookup->rememberedPhone($request);

        $order = null;
        $notFound = false;

        if ($request->filled('code') && $phone !== '') {
            $order = $this->lookup->find((string) $request->input('code'), $phone);
            $notFound = $order === null;
        }

        return Inertia::render('OrderLookup', [
            'order' => $order,
            'not_found' => $notFound,
            // Trả lại giá trị form để giữ lại sau redirect
            // SĐT mặc định lấy từ tài khoản đang đăng nhập (auto-fill — KE_HOACH 8.3).
            'query' => [
                'code' => $request->input('code', ''),
                'phone' => $phone !== '' ? $phone : ($request->user()?->phone ?? ''),
            ],
        ]);
    }
}
