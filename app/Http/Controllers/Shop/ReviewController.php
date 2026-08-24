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

        return back()->with('success', 'Cảm ơn bạn! Đánh giá sẽ hiển thị sau khi được duyệt.');
    }

    /**
     * POST /combos/{slug}/danh-gia — gửi đánh giá combo (bopcamping-saeb).
     *
     * KHÁC đánh giá sản phẩm ở một điểm quan trọng: đây là CỔNG CHẶN, chỉ khách đã thuê
     * đúng combo này và đơn đã trả đồ mới gửi được. Chủ shop chốt vậy vì đánh giá combo
     * là đánh giá "trọn bộ dùng có hợp nhau không" — người chưa đặt trọn bộ không có gì
     * để nói về nó, và combo là mặt hàng dễ bị đánh giá mồi nhất (giá cao, ít lượt).
     *
     * Vì đã bắt buộc đăng nhập + phải có đơn, không có nhánh khách vãng lai nhập tên.
     */
    public function storeForCombo(Request $request, string $slug): RedirectResponse
    {
        $combo = Combo::active()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $orderItemId = $user?->reviewableComboOrderItemId($combo->id);
        if (! $orderItemId) {
            // Trả về lỗi ở khoá 'review' — cùng khoá form đánh giá đang đọc, và cùng cách
            // ReviewInviteController báo "đơn này đã đánh giá rồi".
            return back()->withErrors([
                'review' => 'Chỉ khách đã thuê và trả combo này mới đánh giá được.',
            ]);
        }

        $data = $this->validated($request, requireName: false);

        $this->create($request, $data, [
            'order_item_id' => $orderItemId,
            'combo_id' => $combo->id,
            'category' => 'combo',
        ]);

        return back()->with('success', 'Cảm ơn bạn! Đánh giá sẽ hiển thị sau khi được duyệt.');
    }

    /**
     * POST /danh-gia-shop — đánh giá tổng thể shop từ trang chủ (bopcamping-saeb).
     *
     * Trước đây chỉ gửi được qua link token trong mail sau chuyến đi, nên khách muốn góp
     * lời khen lúc khác thì không có đường nào. Vẫn CHẶN theo "đã từng thuê và trả đồ":
     * đánh giá tổng thể hiện ngay trang chủ nên là chỗ dễ bị spam nhất, và người chưa
     * thuê thì chưa có trải nghiệm để kể.
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

        $data = $this->validated($request, requireName: false);

        $this->create($request, $data, ['category' => 'system']);

        return back()->with('success', 'Cảm ơn bạn! Đánh giá sẽ hiển thị sau khi được duyệt.');
    }

    /**
     * Luật hợp lệ chung cho mọi loại đánh giá gửi từ trang chi tiết.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireName): array
    {
        $rules = [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['nullable', 'string', 'max:1500'],
            'media' => ['nullable', 'array', 'max:4'],
            'media.*' => ['file', MediaType::MIMES_RULE, 'max:30720'], // ≤30MB (ảnh thực tế nhỏ hơn nhiều)
        ];
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
     */
    private function create(Request $request, array $data, array $target): void
    {
        $user = $request->user();

        $review = Review::create($target + [
            'user_id' => $user?->id,
            'reviewer_name' => $user?->name ?? $data['reviewer_name'],
            'rating' => $data['rating'],
            'content' => $data['content'] ?? null,
            'status' => 'pending',
        ]);

        foreach ((array) $request->file('media', []) as $i => $file) {
            $review->images()->create([
                'type' => MediaType::detect($file),
                'path' => $file->store('user/reviews', 'media'),
                'sort_order' => $i,
            ]);
        }
    }
}
