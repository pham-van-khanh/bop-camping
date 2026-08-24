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
