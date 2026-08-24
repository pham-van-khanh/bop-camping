<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
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
        $order = Order::with('items.product', 'items.combo')->where('review_token', $token)->first();

        if (! $order) {
            return Inertia::render('ReviewInvite', ['found' => false]);
        }

        return Inertia::render('ReviewInvite', [
            'found' => true,
            'token' => $token,
            'submitted' => $order->review_submitted_at !== null,
            'customer_name' => $order->customer_name,
            'code' => $order->code,
            // Combo khách đã thuê trong đơn này — mỗi lượt combo một mục chấm riêng
            // (bopcamping-vxwx). Trước đây mail mời chỉ hỏi từng món lẻ nên đánh giá
            // combo gần như không bao giờ sinh ra, dù đây mới là kênh khách hay dùng.
            'combos' => $this->comboGroups($order),
            // CHỈ món thuê lẻ. Món nằm trong combo cố ý KHÔNG hỏi riêng: khách thuê trọn
            // bộ thì nhớ cái bộ, bắt chấm thêm từng món làm form dài gấp mấy lần và
            // người ta bỏ ngang — mất luôn cả đánh giá combo lẫn đánh giá món.
            'items' => $order->items
                ->filter(fn (OrderItem $i) => $i->combo_group_uuid === null)
                ->map(fn (OrderItem $i) => [
                    'order_item_id' => $i->id,
                    'name' => $i->product?->name ?? '(đã xoá)',
                ])->values(),
        ]);
    }

    /**
     * Gom order_items thành các "lượt combo": mỗi `combo_group_uuid` là một lần khách đặt
     * trọn bộ (đặt 2 combo giống nhau trong một đơn = 2 nhóm, chấm riêng từng cái).
     *
     * Bỏ qua dòng combo đã bị xoá khỏi hệ thống — không còn gì để gắn đánh giá vào.
     *
     * @return array<int, array<string, mixed>>
     */
    private function comboGroups(Order $order): array
    {
        return $order->items
            ->filter(fn (OrderItem $i) => $i->combo_group_uuid && $i->combo)
            ->groupBy('combo_group_uuid')
            ->map(fn ($rows, string $uuid) => [
                'key' => $uuid,
                'combo_id' => $rows->first()->combo_id,
                'name' => $rows->first()->combo->name,
                // Liệt kê món trong bộ để khách nhớ mình đã thuê gì.
                'items' => $rows->map(fn (OrderItem $i) => trim(
                    ($i->product?->name ?? '(đã xoá)').($i->quantity > 1 ? ' ×'.$i->quantity : '')
                ))->values()->all(),
            ])
            ->values()
            ->all();
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
            // Đánh giá combo trong đơn (bopcamping-vxwx). `key` là combo_group_uuid —
            // định danh LƯỢT đặt, không phải combo_id, để đặt 2 combo giống nhau trong
            // một đơn vẫn chấm được riêng từng cái.
            'combos' => ['array'],
            'combos.*.key' => ['required', 'string'],
            'combos.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'combos.*.content' => ['nullable', 'string', 'max:1500'],
            'combos.*.media' => ['nullable', 'array', 'max:4'],
            'combos.*.media.*' => ['file', MediaType::MIMES_RULE, 'max:30720'],
        ], [
            'items.*.media.max' => 'Mỗi sản phẩm tối đa 4 ảnh/video.',
            'items.*.media.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).',
            'items.*.media.*.max' => 'Mỗi tệp tối đa 30MB.',
            'combos.*.media.max' => 'Mỗi combo tối đa 4 ảnh/video.',
            'combos.*.media.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).',
            'combos.*.media.*.max' => 'Mỗi tệp tối đa 30MB.',
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

            $this->attachMedia($review, $request->file("items.{$idx}.media", []));
            $created++;
        }

        // Đánh giá từng LƯỢT combo trong đơn (bopcamping-vxwx).
        foreach ($data['combos'] ?? [] as $idx => $row) {
            if (empty($row['rating'])) {
                continue;
            }
            // Đối chiếu `key` với chính order_items của ĐƠN NÀY — combo_id không bao giờ
            // lấy từ payload. Tin payload thì ai cầm link đánh giá cũng gắn được đánh giá
            // vào combo bất kỳ trong shop, kể cả combo họ chưa từng thuê.
            $rows = $order->items->where('combo_group_uuid', $row['key']);
            $first = $rows->first();
            if (! $first || ! $first->combo_id) {
                continue;
            }
            $review = Review::create([
                // Vé order_item nuôi dòng meta "X ngày" trên trang combo.
                'order_item_id' => $first->id,
                'combo_id' => $first->combo_id,
                'user_id' => $order->user_id,
                'reviewer_name' => $order->customer_name,
                'rating' => $row['rating'],
                'content' => $row['content'] ?? null,
                'category' => 'combo',
                'status' => 'pending',
            ]);

            $this->attachMedia($review, $request->file("combos.{$idx}.media", []));
            $created++;
        }

        if ($created === 0) {
            return back()->withErrors(['review' => 'Hãy chấm sao cho ít nhất một mục.']);
        }

        $order->forceFill(['review_submitted_at' => now()])->saveQuietly();

        return back()->with('success', 'Cảm ơn bạn đã đánh giá!');
    }

    /**
     * Lưu ảnh/video kèm một đánh giá. Gộp về một chỗ vì hai nhánh (món lẻ và combo) làm
     * y hệt nhau — tách hai bản là mở đường cho chúng lệch nhau về sau.
     *
     * @param  mixed  $files  kết quả $request->file(...): mảng file, một file, hoặc null
     */
    private function attachMedia(Review $review, $files): void
    {
        foreach ((array) $files as $i => $file) {
            $review->images()->create([
                'type' => MediaType::detect($file),
                'path' => $file->store('user/reviews', 'media'),
                'sort_order' => $i,
            ]);
        }
    }
}
