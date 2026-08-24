<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Product;
use App\Models\Review;
use App\Support\MediaType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * POST /thiet-bi/{product}/danh-gia — gửi đánh giá sản phẩm.
     * Ai cũng gửi được (khách vãng lai nhập tên); mọi đánh giá vào trạng thái pending chờ admin duyệt.
     * Nếu là khách đã thuê & trả món này thì gắn luôn order_item để hiện "X ngày" trong meta.
     */
    public function store(Request $request, string $product): RedirectResponse
    {
        $p = Product::active()->where('slug', $product)->firstOrFail();
        $user = $request->user();

        $data = $this->validated($request, requireName: ! $user);

        $this->create($request, $data, [
            'order_item_id' => $user?->reviewableOrderItemId($p->id),
            'product_id' => $p->id,
            'category' => 'product',
        ]);

        return back()->with('success', 'Cảm ơn bạn đã chia sẻ trải nghiệm!');
    }

    /**
     * POST /combos/{slug}/danh-gia — gửi đánh giá combo (bopcamping-saeb).
     *
     * Cùng luật với đánh giá sản phẩm: ai cũng gửi được (khách vãng lai nhập tên), mọi
     * đánh giá vào pending chờ admin duyệt. Khách đã thuê & trả combo này thì gắn thêm
     * order_item để hiện "X ngày" trong meta.
     */
    public function storeForCombo(Request $request, string $slug): RedirectResponse
    {
        $combo = Combo::active()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $data = $this->validated($request, requireName: ! $user);

        $this->create($request, $data, [
            'order_item_id' => $user?->reviewableComboOrderItemId($combo->id),
            'combo_id' => $combo->id,
            'category' => 'combo',
        ]);

        return back()->with('success', 'Cảm ơn bạn đã chia sẻ trải nghiệm!');
    }

    /**
     * POST /danh-gia-shop — đánh giá tổng thể shop từ trang chủ (bopcamping-saeb).
     *
     * Trước đây chỉ gửi được qua link token trong mail sau chuyến đi, nên khách muốn góp
     * lời khen lúc khác thì không có đường nào. Vẫn CHẶN theo "đã từng thuê và trả đồ":
     * đánh giá tổng thể hiện ngay trang chủ nên là chỗ dễ bị spam nhất, và người chưa
     * thuê thì chưa có trải nghiệm để kể.
     *
     * Chặn ở ĐÂY chứ không bằng middleware `auth`: middleware đó đá về `/login` của Breeze
     * — màn đăng nhập BẰNG MẬT KHẨU dành cho admin, khách không dùng được (khách đăng nhập
     * bằng modal SĐT+OTP). Khách hết phiên giữa chừng sẽ bị ném sang trang lạ và mất luôn
     * đoạn vừa gõ; trả lỗi ở đây thì họ ở lại trang và thấy nhắc ngay dưới form.
     *
     * Không cần combo/sản phẩm nào — đây là đánh giá về shop, không gắn order_item.
     */
    public function storeSystem(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user?->hasReturnedOrder()) {
            return back()->withErrors([
                'review' => 'Chỉ khách đã thuê và trả đồ mới đánh giá được. Hẹn bạn sau chuyến đi đầu tiên!',
            ]);
        }

        // Một đơn đã trả mở khoá VĨNH VIỄN, nên nếu không chặn thì một tài khoản gửi được
        // vô số đánh giá (throttle 10/phút vẫn cho ~600 dòng chờ duyệt mỗi giờ) và chủ shop
        // è cổ duyệt tay. Luồng token trong mail chống bằng `review_submitted_at`; ở đây
        // dùng luật tương đương: đang có một cái chờ duyệt thì chưa gửi tiếp.
        $hasPending = $user->reviews()
            ->where('category', 'system')->where('status', 'pending')->exists();
        if ($hasPending) {
            return back()->withErrors([
                'review' => 'Bạn vừa gửi một đánh giá rồi. Cảm ơn bạn!',
            ]);
        }

        // KHÔNG nhận ảnh/video: carousel đánh giá tổng thể ở trang chủ (SystemReviews) chỉ
        // render chữ + sao, nên nhận file là nhận 4×30MB vào đĩa để không ai nhìn thấy.
        $data = $this->validated($request, requireName: false, allowMedia: false);

        $this->create($request, $data, ['category' => 'system'], withMedia: false);

        return back()->with('success', 'Cảm ơn bạn đã chia sẻ trải nghiệm!');
    }

    /**
     * Luật hợp lệ chung cho mọi loại đánh giá gửi từ trang chi tiết.
     *
     * @param  bool  $allowMedia  false thì bỏ hẳn nhánh file (đánh giá tổng thể không hiện ảnh)
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireName, bool $allowMedia = true): array
    {
        $rules = [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['nullable', 'string', 'max:1500'],
        ];
        if ($allowMedia) {
            $rules['media'] = ['nullable', 'array', 'max:4'];
            $rules['media.*'] = ['file', MediaType::MIMES_RULE, 'max:30720']; // ≤30MB (ảnh thực tế nhỏ hơn nhiều)
        }
        if ($requireName) {
            $rules['reviewer_name'] = ['required', 'string', 'max:60'];
        }

        return $request->validate($rules, [
            'rating.required' => 'Vui lòng chấm sao.',
            'rating.min' => 'Vui lòng chấm sao.',
            'reviewer_name.required' => 'Vui lòng nhập tên của bạn.',
            'media.max' => 'Tối đa 4 ảnh/video.',
            'media.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).',
            'media.*.max' => 'Mỗi tệp tối đa 30MB.',
        ]);
    }

    /**
     * Tạo đánh giá + lưu ảnh/video kèm theo. `$target` mang phần khác nhau giữa sản phẩm
     * và combo (product_id/combo_id, category, vé order_item).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $target
     * @param  bool  $withMedia  PHẢI khớp $allowMedia của validated(): tắt validate mà vẫn
     *                           đọc file ở đây thì file lọt vào đĩa mà không qua luật nào.
     */
    private function create(Request $request, array $data, array $target, bool $withMedia = true): void
    {
        $user = $request->user();

        $review = Review::create($target + [
            'user_id' => $user?->id,
            'reviewer_name' => $user?->name ?? $data['reviewer_name'],
            'rating' => $data['rating'],
            'content' => $data['content'] ?? null,
            'status' => 'pending',
        ]);

        foreach ($withMedia ? (array) $request->file('media', []) : [] as $i => $file) {
            $review->images()->create([
                'type' => MediaType::detect($file),
                'path' => $file->store('user/reviews', 'media'),
                'sort_order' => $i,
            ]);
        }
    }
}
