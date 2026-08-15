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
    public function urlFor(Order $order, bool $download = false, bool $forAdmin = false): ?string
    {
        $bank = $this->config('bank');
        $account = $this->config('account');

        // Thiếu tài khoản nhận tiền thì URL vô nghĩa — thà không có QR còn hơn có QR chết.
        if (! $bank || ! $account || ! $this->shouldShowFor($order, $forAdmin)) {
            return null;
        }

        $params = [
            'acc' => $account,
            'bank' => $bank,
            // CÒN phải thu, không phải tổng đơn: khách đã trả tiền thuê mà QR vẫn ghi
            // tổng thì họ chuyển thừa đúng bằng khoản đã trả (bopcamping-pew1).
            //
            // transfer_due chứ không phải outstanding_due (bopcamping-urqo): đã trả tiền
            // thuê rồi thì phụ phí không đòi qua chuyển khoản nữa, nó trừ vào cọc lúc hoàn.
            'amount' => $order->transfer_due,
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
     * bịa thêm field.
     *
     * $forAdmin quyết định HAI thứ cùng lúc, và đó là chủ ý: chỉ admin mới thấy QR sau
     * khi đơn đã xác nhận, và cũng chỉ admin mới cần nút tải ảnh để gửi khách qua Zalo.
     * Gộp làm một cờ vì hai điều đó luôn đi cùng nhau — tách ra chỉ đẻ thêm tổ hợp vô
     * nghĩa (khách mà có nút tải, hoặc admin thấy QR nhưng không tải được).
     *
     * @return array{url: string, amount: int, content: string, download_url?: string}|null
     */
    public function payloadFor(Order $order, bool $forAdmin = false): ?array
    {
        $url = $this->urlFor($order, forAdmin: $forAdmin);

        if ($url === null) {
            return null;
        }

        $payload = [
            'url' => $url,
            'amount' => $order->transfer_due,
            'content' => $this->transferContentFor($order),
            // Phụ phí không nằm trong QR thì phải nói cho khách biết nó đi đâu, không thì
            // con số trên QR lệch với "còn phải trả" mà không ai giải thích (bopcamping-urqo).
            'fee_from_deposit' => $order->rentalPaid() ? $order->feeOutstanding() : 0,
            // Thông tin người nhận dạng CHỮ (bopcamping-r3fy): trước đây chúng chỉ nằm
            // trong pixel của ảnh và trong query string, nên SePay sập hoặc mạng lỗi là
            // khách không còn biết chuyển cho ai. Có bản chữ thì vẫn chuyển tay được.
            'bank' => $this->config('bank'),
            'account' => $this->config('account'),
            'holder' => $this->config('holder'),
        ];

        if ($forAdmin) {
            $payload['download_url'] = $this->urlFor($order, download: true, forAdmin: true);
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

    /**
     * Đã khai báo đủ tài khoản nhận tiền chưa (bopcamping-r3fy).
     *
     * Thiếu config thì QR ẩn LẶNG LẼ ở mọi trang — không lỗi, không log, không phân biệt
     * được với "ẩn vì luật đơn". Đã dính đúng bẫy này một lần trên staging. Admin cần
     * thấy được sự khác nhau đó, nên trạng thái cấu hình phải đẩy ra tới màn admin.
     */
    public function isConfigured(): bool
    {
        return $this->config('bank') !== null && $this->config('account') !== null;
    }

    /** Đơn này có đáng hiện QR không — luật khác nhau tuỳ người xem (bopcamping-pew1). */
    private function shouldShowFor(Order $order, bool $forAdmin): bool
    {
        // Đơn CHA là vỏ chứa (tổng = Σ đơn con), tiền thu theo từng con. Sinh QR cho cha
        // là đếm đôi chính số tiền của những đơn con đã có QR riêng.
        if ($order->is_parent) {
            return false;
        }

        // Đơn huỷ thì không đòi tiền nữa — kể cả admin.
        if ($order->status === 'cancelled') {
            return false;
        }

        // Không còn đồng nào để ĐÒI QUA CHUYỂN KHOẢN. Dùng transfer_due chứ không phải
        // outstanding_due: đơn chỉ còn nợ mỗi phụ phí (sẽ trừ vào cọc) thì không cần QR.
        if ($order->transfer_due <= 0) {
            return false;
        }

        // Admin thấy QR ở mọi trạng thái còn lại: admin là người GỬI QR đi đòi tiền, mà
        // ẩn theo luật khách bên dưới thì admin chỉ thấy đúng lúc khách đã hết cần. Đây
        // cũng là đường thoát cho đơn lỡ xác nhận khi khách chưa trả — admin vẫn tải
        // được ảnh gửi tay.
        if ($forAdmin) {
            return true;
        }

        // LUẬT KHÁCH: chỉ đơn 'pending'. Shop chỉ xác nhận đơn SAU khi tiền đã về, nên
        // đơn đã xác nhận nghĩa là khách trả xong — còn chìa QR là mời chuyển lần hai.
        return $order->status === 'pending';
    }

    private function config(string $key): ?string
    {
        $value = config("services.sepay.$key");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
