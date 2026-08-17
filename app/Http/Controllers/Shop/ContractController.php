<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\ContractService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trang ký hợp đồng của khách — không cần đăng nhập, mở bằng link token (bopcamping-4jao).
 *
 * MỘT link cho cả ba giai đoạn ký; trang tự hiện đúng giai đoạn đang tới lượt. Ký trước ở nhà
 * hay ký lúc nhận đồ đều là cùng cái link, nên không tồn tại hai phiên bản hợp đồng cho một đơn.
 *
 * Cửa 4 số cuối SĐT: link gửi qua Zalo bị chuyển tiếp được, mà hợp đồng chứa tên, địa chỉ và
 * số CCCD. Token 64 ký tự chặn dò mù; 4 số cuối chặn người vô tình nhặt được link.
 */
class ContractController extends Controller
{
    public function __construct(private ContractService $contracts) {}

    public function show(Request $request, string $token): Response
    {
        $contract = $this->find($token);

        if (! $this->unlocked($request, $contract)) {
            return Inertia::render('Contract', [
                'unlocked' => false,
                'token' => $token,
                'customer_name' => $contract->order->customer_name,
            ]);
        }

        $stage = $contract->nextStage();
        // Ký xong cả ba thì vẫn cho xem lại bản hợp đồng chính.
        $viewStage = $stage ?? 'main';
        $html = $this->contracts->render($contract, $viewStage);

        return Inertia::render('Contract', [
            'unlocked' => true,
            'token' => $token,
            'code' => $contract->code,
            'customer_name' => $contract->order->customer_name,
            'stage' => $stage,
            'stage_label' => Contract::STAGE_LABELS[$viewStage],
            'content_html' => $html,
            'content_hash' => hash('sha256', $html),
            'signed_stages' => $contract->signatures->pluck('stage')->values(),
            'stage_labels' => Contract::STAGE_LABELS,
            'has_pdf' => $contract->pdf_path !== null,
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $contract = $this->find($token);

        $data = $request->validate(['last4' => ['required', 'string', 'size:4']]);

        if (! hash_equals($contract->phoneLast4(), $data['last4'])) {
            return back()->withErrors(['last4' => 'Bốn số cuối chưa đúng. Nhập 4 số cuối của số điện thoại đã đặt đơn.']);
        }

        $request->session()->put($this->sessionKey($contract), true);

        if (! $contract->first_viewed_at) {
            $contract->forceFill(['first_viewed_at' => now()])->save();
        }

        return back();
    }

    public function sign(Request $request, string $token, string $stage): RedirectResponse
    {
        $contract = $this->find($token);

        abort_unless($this->unlocked($request, $contract), 403);

        $data = $request->validate([
            // ~2MB: chữ ký PNG thật chỉ vài chục KB, nhưng canvas màn hình lớn thì nặng hơn.
            'signature' => ['required', 'string', 'max:2000000'],
            'content_hash' => ['required', 'string', 'size:64'],
        ]);

        try {
            $this->contracts->sign($contract, $stage, $data['signature'], $data['content_hash'], $request);
        } catch (DomainException $e) {
            return back()->withErrors(['content_hash' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã ký xong. Bản PDF sẽ được gửi vào email của bạn.');
    }

    /**
     * Tải bản PDF đã ký.
     *
     * PHẢI qua cửa 4 số cuối như trang xem: file chứa tên, địa chỉ và số CCCD, để ai có link
     * cũng tải được thì cửa kia thành vô nghĩa.
     */
    public function pdf(Request $request, string $token): StreamedResponse
    {
        $contract = $this->find($token);

        abort_unless($this->unlocked($request, $contract), 403);
        abort_if($contract->pdf_path === null, 404);

        return Storage::disk('media')->download(
            $contract->pdf_path,
            "hop-dong-{$contract->order->code}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function find(string $token): Contract
    {
        return Contract::with('order', 'items', 'signatures')
            ->where('token', $token)
            ->firstOrFail();
    }

    private function unlocked(Request $request, Contract $contract): bool
    {
        return $request->session()->get($this->sessionKey($contract)) === true;
    }

    /** Khoá theo TỪNG hợp đồng — mở được đơn này không có nghĩa là mở được đơn khác. */
    private function sessionKey(Contract $contract): string
    {
        return "contract_unlocked_{$contract->id}";
    }
}
