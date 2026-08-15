import { money } from '@/lib/format';

/**
 * Khách xem shop đã nhận khoản nào (bopcamping-pew1) — dùng chung /tra-cuu và /tai-khoan.
 *
 * Trước đây rental_paid/deposit_paid không hề ra tới khách: khách chuyển khoản xong
 * không có cách nào biết shop đã ghi nhận chưa, chỉ còn nước nhắn hỏi.
 *
 * Chữ dùng cố ý TRUNG TÍNH với hình thức trả ("Shop đã nhận", không phải "đã chuyển
 * khoản"): đơn COD trả tiền mặt lúc nhận đồ cũng đi qua đúng hai cột này.
 */
export default function PaymentStatus({
    rentalDue,
    depositTotal,
    rentalPaid,
    depositPaid,
}: {
    rentalDue: number;
    depositTotal: number;
    rentalPaid: boolean;
    depositPaid: boolean;
}) {
    // Đơn không cọc thì đừng bịa ra một dòng luôn "chưa nhận" không bao giờ xong.
    const rows = [
        { label: 'Tiền thuê', amount: rentalDue, paid: rentalPaid, show: true },
        {
            label: 'Tiền cọc',
            amount: depositTotal,
            paid: depositPaid,
            show: depositTotal > 0,
        },
    ].filter((r) => r.show);

    return (
        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
            <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                Tình trạng thanh toán
            </div>

            <div className="flex flex-col gap-1.5">
                {rows.map((r) => (
                    <div
                        key={r.label}
                        className="flex items-center justify-between gap-3 text-[13px]"
                    >
                        <span className="text-moss">
                            {r.label}{' '}
                            <span className="font-mono text-ink">
                                {money(r.amount)}
                            </span>
                        </span>
                        {/* shrink-0 + nowrap: trên mobile 375px nhãn "✓ Shop đã nhận" bị
                            ngắt giữa chừng thành hai dòng, nhìn như vỡ giao diện (đo được
                            trên trình duyệt — jsdom không bắt được lỗi kiểu này). */}
                        {r.paid ? (
                            <span className="shrink-0 whitespace-nowrap rounded-pill bg-[#dcebc4] px-2.5 py-1 text-[11.5px] font-bold text-[#3a5a1f]">
                                ✓ Shop đã nhận
                            </span>
                        ) : (
                            <span className="shrink-0 whitespace-nowrap rounded-pill bg-[#f1f4ea] px-2.5 py-1 text-[11.5px] font-bold text-[#8b957a]">
                                Chưa nhận
                            </span>
                        )}
                    </div>
                ))}
            </div>

            {rows.some((r) => !r.paid) && (
                <p className="mt-2 border-t border-[#f1f4ea] pt-2 text-[11.5px] text-[#a3ad92]">
                    Shop cập nhật mục này sau khi nhận được tiền.
                </p>
            )}
        </div>
    );
}
