<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Support\MediaType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Đánh giá sau chuyến đi qua link token trong mail (không cần đăng nhập).
 */
class ReviewInviteController extends Controller
{
    public function show(string $token): Response
    {
        $order = Order::with('items.product')->where('review_token', $token)->first();

        if (! $order) {
            return Inertia::render('ReviewInvite', ['found' => false]);
        }

        return Inertia::render('ReviewInvite', [
            'found' => true,
            'token' => $token,
            'submitted' => $order->review_submitted_at !== null,
            'customer_name' => $order->customer_name,
            'code' => $order->code,
            'items' => $order->items->map(fn ($i) => [
                'order_item_id' => $i->id,
                'name' => $i->product?->name ?? '(đã xoá)',
            ])->values(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $order = Order::with('items')->where('review_token', $token)->firstOrFail();

        if ($order->review_submitted_at) {
            return back()->withErrors(['review' => 'Đơn này đã được đánh giá rồi. Cảm ơn bạn!']);
        }

        $data = $request->validate([
            'system_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'system_content' => ['nullable', 'string', 'max:1500'],
            'items' => ['array'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'items.*.content' => ['nullable', 'string', 'max:1500'],
            'items.*.media' => ['nullable', 'array', 'max:4'],
            'items.*.media.*' => ['file', MediaType::MIMES_RULE, 'max:30720'], // ≤30MB
        ], [
            'items.*.media.max' => 'Mỗi sản phẩm tối đa 4 ảnh/video.',
            'items.*.media.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).',
            'items.*.media.*.max' => 'Mỗi tệp tối đa 30MB.',
        ]);

        $created = 0;

        // Đánh giá tổng thể shop (system).
        if (! empty($data['system_rating'])) {
            Review::create([
                'user_id' => $order->user_id,
                'reviewer_name' => $order->customer_name,
                'rating' => $data['system_rating'],
                'content' => $data['system_content'] ?? null,
                'category' => 'system',
                'status' => 'pending',
            ]);
            $created++;
        }

        // Đánh giá từng sản phẩm trong đơn (kèm ảnh/video nếu có).
        foreach ($data['items'] ?? [] as $idx => $row) {
            if (empty($row['rating'])) {
                continue;
            }
            $item = $order->items->firstWhere('id', $row['order_item_id']);
            if (! $item) {
                continue;
            }
            $review = Review::create([
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'user_id' => $order->user_id,
                'reviewer_name' => $order->customer_name,
                'rating' => $row['rating'],
                'content' => $row['content'] ?? null,
                'category' => 'product',
                'status' => 'pending',
            ]);

            foreach ((array) $request->file("items.{$idx}.media", []) as $j => $file) {
                $review->images()->create([
                    'type' => MediaType::detect($file),
                    'path' => $file->store('user/reviews', 'media'),
                    'sort_order' => $j,
                ]);
            }
            $created++;
        }

        if ($created === 0) {
            return back()->withErrors(['review' => 'Hãy chấm sao cho ít nhất một mục.']);
        }

        $order->forceFill(['review_submitted_at' => now()])->saveQuietly();

        return back()->with('success', 'Cảm ơn bạn đã đánh giá!');
    }
}
