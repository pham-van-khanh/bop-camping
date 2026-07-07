<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
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

        $rules = [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['nullable', 'string', 'max:1500'],
            'media' => ['nullable', 'array', 'max:4'],
            'media.*' => ['file', MediaType::MIMES_RULE, 'max:30720'], // ≤30MB (ảnh thực tế nhỏ hơn nhiều)
        ];
        if (! $user) {
            $rules['reviewer_name'] = ['required', 'string', 'max:60'];
        }

        $data = $request->validate($rules, [
            'rating.required' => 'Vui lòng chấm sao.',
            'rating.min' => 'Vui lòng chấm sao.',
            'reviewer_name.required' => 'Vui lòng nhập tên của bạn.',
            'media.max' => 'Tối đa 4 ảnh/video.',
            'media.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).',
            'media.*.max' => 'Mỗi tệp tối đa 30MB.',
        ]);

        $review = Review::create([
            'order_item_id' => $user?->reviewableOrderItemId($p->id),
            'product_id' => $p->id,
            'user_id' => $user?->id,
            'reviewer_name' => $user?->name ?? $data['reviewer_name'],
            'rating' => $data['rating'],
            'content' => $data['content'] ?? null,
            'category' => 'product',
            'status' => 'pending',
        ]);

        foreach ((array) $request->file('media', []) as $i => $file) {
            $review->images()->create([
                'type' => MediaType::detect($file),
                'path' => $file->store('user/reviews', 'media'),
                'sort_order' => $i,
            ]);
        }

        return back()->with('success', 'Cảm ơn bạn! Đánh giá sẽ hiển thị sau khi được duyệt.');
    }
}
