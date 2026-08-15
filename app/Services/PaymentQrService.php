<?php

namespace App\Services;

use App\Models\Order;

/**
 * QR chuyển khoản cho một đơn thuê (bopcamping-55rh) — NGUỒN DUY NHẤT dựng URL ảnh QR.
 *
 * Không nơi nào khác được tự ráp URL này: số tiền và nội dung CK phải đi ra từ đúng một
 * chỗ, không thì sớm muộn cũng có trang in số tiền lệch với trang khác.
 *
 * Chỉ SINH ẢNH. Không webhook, không tự đối soát, không viết gì vào trạng thái tiền của
 * đơn — admin tự kiểm sao kê rồi bấm Order::markPaid() như trước nay.
 *
 * Ảnh do SePay render (stateless, không cần API key). Cố ý KHÔNG lưu ảnh về: đơn sửa giá
 * là URL đổi theo, nên không tồn tại bản cũ nào để mà lệch số tiền.
 */
class PaymentQrService
{
    private const ENDPOINT = 'https://qr.sepay.vn/img';

    /**
     * URL ảnh QR, hoặc null nếu đơn này không nên có QR (xem shouldShowFor()).
     *
     * Trả null thay vì ném lỗi để giao diện không phải suy luận lại điều kiện: có thì vẽ,
     * null thì thôi.
     */
    public function urlFor(Order $order, bool $download = false): ?string
    {
        $bank = $this->config('bank');
        $account = $this->config('account');

        // Thiếu tài khoản nhận tiền thì URL vô nghĩa — thà không có QR còn hơn có QR chết.
        if (! $bank || ! $account || ! $this->shouldShowFor($order)) {
            return null;
        }

        $params = [
            'acc' => $account,
            'bank' => $bank,
            'amount' => $order->amount_due,
            'des' => $this->transferContentFor($order),
            'template' => 'compact',
            'showinfo' => 'true',
            'fullacc' => 'true',
        ];

        if ($holder = $this->config('holder')) {
            $params['holder'] = $holder;
        }

        // SePay trả kèm Content-Disposition khi có cờ này — dùng cho nút tải của admin.
        if ($download) {
            $params['download'] = 'true';
        }

        return self::ENDPOINT.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Khối dữ liệu QR cho FE — null nếu đơn không có QR.
     *
     * Ba trang (admin, tra cứu, tài khoản) dùng chung shape này để không trang nào tự
     * bịa thêm field. Nút tải chỉ dành cho admin, nên $withDownload mặc định tắt: khách
     * không cần tải ảnh về, đó là công cụ để admin gửi qua Zalo.
     *
     * @return array{url: string, amount: int, content: string, download_url?: string}|null
     */
    public function payloadFor(Order $order, bool $withDownload = false): ?array
    {
        $url = $this->urlFor($order);

        if ($url === null) {
            return null;
        }

        $payload = [
            'url' => $url,
            'amount' => (int) $order->amount_due,
            'content' => $this->transferContentFor($order),
        ];

        if ($withDownload) {
            $payload['download_url'] = $this->urlFor($order, download: true);
        }

        return $payload;
    }

    /**
     * Nội dung chuyển khoản: mã đơn lược hết ký tự không phải chữ/số
     * (BOP-1485E3 → BOP1485E3, đơn con BOP-1485E3-2 → BOP1485E32).
     *
     * VietQR chỉ nhận chữ và số ở tham số des. Giữ dấu gạch thì tuỳ ngân hàng mà bị cắt
     * hoặc thay ký tự, nội dung về sao kê không còn dò được — mà đối soát ở đây làm TAY,
     * nên chuỗi này chính là đầu mối duy nhất để admin tìm ra tiền của đơn nào.
     */
    public function transferContentFor(Order $order): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $order->code));
    }

    /** Đơn này có đáng hiện QR không — bốn điều kiện, thiếu một là thôi. */
    private function shouldShowFor(Order $order): bool
    {
        // Đơn CHA là vỏ chứa (tổng = Σ đơn con), tiền thu theo từng con. Sinh QR cho cha
        // là đếm đôi chính số tiền của những đơn con đã có QR riêng.
        if ($order->is_parent) {
            return false;
        }

        // Đơn còn 'pending' thì giá chưa chắc — shop vẫn còn sửa lịch/phụ phí. QR in số
        // tiền sai còn tệ hơn không có QR: khách chuyển xong mới biết thiếu.
        if (! $order->isConfirmed()) {
            return false;
        }

        // Thu đủ rồi mà còn chìa QR là mời khách trả lần hai.
        return $order->amount_due > 0 && $order->payment_status !== 'full';
    }

    private function config(string $key): ?string
    {
        $value = config("services.sepay.$key");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
