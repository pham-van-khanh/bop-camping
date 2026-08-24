<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $category = $request->string('category')->toString();

        $query = Review::with(['images', 'product:id,name', 'combo:id,name'])->latest();
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }
        // Review::CATEGORIES là nguồn duy nhất — thêm loại đánh giá mới thì bộ lọc tự có,
        // không phải sửa lại danh sách gõ tay ở đây (bopcamping-saeb).
        if (in_array($category, Review::CATEGORIES, true)) {
            $query->where('category', $category);
        }

        $reviews = $query->paginate(20)->withQueryString()->through(fn (Review $r) => [
            'id' => $r->id,
            'reviewer_name' => $r->reviewer_name,
            'rating' => $r->rating,
            'content' => $r->content,
            'category' => $r->category,
            'status' => $r->status,
            'admin_note' => $r->admin_note,
            'product' => $r->product ? ['id' => $r->product->id, 'name' => $r->product->name] : null,
            'combo' => $r->combo ? ['id' => $r->combo->id, 'name' => $r->combo->name] : null,
            'created_at' => $r->created_at->format('d/m/Y H:i'),
            'media' => $r->images->map(fn ($m) => ['type' => $m->type, 'url' => Storage::disk('media')->url($m->path)])->values(),
        ]);

        $counts = Review::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Admin/Reviews', [
            'reviews' => $reviews,
            'filters' => ['status' => $status, 'category' => $category ?: 'all'],
            'counts' => [
                'pending' => $counts['pending'] ?? 0,
                'approved' => $counts['approved'] ?? 0,
                'rejected' => $counts['rejected'] ?? 0,
            ],
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_note' => ['nullable', 'string', 'max:255'],
        ]);

        $review->update([
            'status' => $data['status'],
            'admin_note' => $data['status'] === 'rejected' ? ($data['admin_note'] ?? null) : null,
        ]);

        return back()->with('success', $data['status'] === 'approved' ? 'Đã duyệt đánh giá.' : 'Đã từ chối đánh giá.');
    }
}
