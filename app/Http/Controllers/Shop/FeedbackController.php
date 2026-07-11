<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\FeedbackReceivedMail;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Khách gửi góp ý trải nghiệm website qua widget nổi (Epic 2). */
class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'phone' => 'nullable|string|max:20|required_without:email',
            'email' => 'nullable|email|max:150',
            'content' => 'required|string|min:5|max:3000',
        ], [
            'name.required' => 'Cho tụi mình biết tên bạn nhé.',
            'phone.required_without' => 'Cần ít nhất SĐT hoặc email để tụi mình phản hồi bạn.',
            'email.email' => 'Email chưa đúng định dạng.',
            'content.required' => 'Bạn chưa nhập nội dung góp ý.',
            'content.min' => 'Nội dung góp ý quá ngắn.',
        ]);

        $feedback = Feedback::create($data);

        // Báo QTV: ưu tiên MAIL_ADMIN_ADDRESS trong .env, fallback email các tài khoản admin.
        $recipients = config('mail.admin_address') ?: User::adminNotifyEmails();
        if ($recipients) {
            Mail::to($recipients)->send(new FeedbackReceivedMail($feedback));
        }

        return back()->with('success', 'Cảm ơn bạn đã góp ý cho BỐP CAMPING!');
    }
}
