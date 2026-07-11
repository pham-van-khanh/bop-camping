<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\FeedbackReplyMail;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/** Admin đọc + phản hồi góp ý (Epic 2) — /admin/gop-y. */
class FeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', '');

        return Inertia::render('Admin/Feedbacks', [
            'feedbacks' => Feedback::query()
                ->when(in_array($status, ['new', 'replied'], true), fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Feedback $f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'phone' => $f->phone,
                    'email' => $f->email,
                    'content' => $f->content,
                    'status' => $f->status,
                    'reply_content' => $f->reply_content,
                    'replied_at' => $f->replied_at?->format('d/m/Y H:i'),
                    'created_at' => $f->created_at->format('d/m/Y H:i'),
                ]),
            'filters' => ['status' => $status],
        ]);
    }

    /**
     * Phản hồi góp ý. Khách có email → gửi FeedbackReplyMail bằng mailer phản hồi
     * cấu hình .env (template cố định chào theo tên + nội dung admin soạn).
     * Khách chỉ để SĐT → chỉ lưu ghi chú + đánh dấu đã phản hồi (admin gọi/Zalo).
     */
    public function reply(Request $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validate([
            'reply_content' => 'required|string|min:5|max:5000',
        ], [
            'reply_content.required' => 'Chưa nhập nội dung phản hồi.',
            'reply_content.min' => 'Nội dung phản hồi quá ngắn.',
        ]);

        $feedback->update([
            'reply_content' => $data['reply_content'],
            'status' => 'replied',
            'replied_at' => now(),
        ]);

        if ($feedback->email) {
            $mailer = config('mail.reply_mailer') ?: config('mail.default');
            Mail::mailer($mailer)->to($feedback->email)->send(new FeedbackReplyMail($feedback));

            return back()->with('success', 'Đã gửi email phản hồi tới khách.');
        }

        return back()->with('success', 'Đã lưu phản hồi (khách không để email — liên hệ qua SĐT/Zalo nhé).');
    }
}
