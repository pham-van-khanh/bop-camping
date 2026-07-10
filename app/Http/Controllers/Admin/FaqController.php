<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(): Response
    {
        $faqs = Faq::ordered()->get()->map(fn (Faq $f) => [
            'id' => $f->id,
            'question' => $f->question,
            'answer' => $f->answer,
            'sort_order' => $f->sort_order,
            'is_active' => $f->is_active,
        ]);

        return Inertia::render('Admin/Faqs', ['faqs' => $faqs]);
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::create($this->validated($request, true));

        return back()->with('success', 'Đã thêm câu hỏi.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        // Giữ nguyên trạng thái hiện tại nếu request không gửi is_active (tránh
        // vô tình bật lại FAQ đang ẩn khi client bỏ qua field).
        $faq->update($this->validated($request, $faq->is_active));

        return back()->with('success', 'Đã cập nhật câu hỏi.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'Đã xoá câu hỏi.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $activeDefault): array
    {
        $data = $request->validate([
            'question' => 'required|string|min:3|max:255',
            'answer' => 'required|string|max:5000',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            'is_active' => 'sometimes|boolean',
        ], [
            'question.required' => 'Câu hỏi không được bỏ trống.',
            'answer.required' => 'Nội dung trả lời không được bỏ trống.',
        ]);

        return [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $activeDefault,
        ];
    }
}
