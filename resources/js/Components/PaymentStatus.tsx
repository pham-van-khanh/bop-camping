import { money } from '@/lib/format';

/**
 * Khách xem shop đã nhận khoản nào (bopcamping-pew1) — dùng chung /tra-cuu và /tai-khoan.
 *
 * Trước đây rental_paid/deposit_paid không hề ra tới khách: khách chuyển khoản xong
 * không có cách nào biết shop đã ghi nhận chưa, chỉ còn nước nhắn hỏi.
 *
 * In SỐ TIỀN THẬT ĐÃ NHẬN, không phải số tiền phải trả hiện tại (bopcamping-r3fy). Giá đơn
 * còn đổi sau lúc thu (admin nhập phụ phí, đổi lịch), nên ghép cờ "đã nhận" với con số mới
 * là khẳng định shop đã nhận một khoản chưa từng nhận. Thu thiếu thì nói rõ còn bao nhiêu.
 *
 * Chữ dùng cố ý TRUNG TÍNH với hình thức trả ("Shop đã nhận", không phải "đã chuyển
 * khoản"): đơn COD trả tiền mặt lúc nhận đồ cũng đi qua đúng hai cột này.
 */
type Row = {
    label: string;
    due: number;
    received: number;
};

function StatusPill({ due, received }: { due: number; received: number }) {
    if (received <= 0) {
        // Tương phản 4.5:1 theo WCAG AA cho chữ nhỏ — bản đầu dùng #8b957a chỉ được
        // 2.83:1, tức trạng thái quan trọng nhất lại là cái khó đọc nhất.
        return (
            <span className="shrink-0 whitespace-nowrap rounded-pill bg-[#f1f4ea] px-2.5 py-1 text-[11.5px] font-bold text-[#5f6650]">
                Chưa nhận
            </span>
        );
    }

    if (received < due) {
        return (
            <span className="shrink-0 whitespace-nowrap rounded-pill bg-[#fdf0d9] px-2.5 py-1 text-[11.5px] font-bold text-[#8a5a1a]">
                Đã nhận {money(received)} · còn {money(due - received)}
            </span>
        );
    }

    return (
        <span className="shrink-0 whitespace-nowrap rounded-pill bg-[#dcebc4] px-2.5 py-1 text-[11.5px] font-bold text-[#3a5a1f]">
            ✓ Shop đã nhận
        </span>
    );
}

export default function PaymentStatus({
    rentalDue,
    depositTotal,
    rentalReceived,
    depositReceived,
}: {
    rentalDue: number;
    depositTotal: number;
    rentalReceived: number;
    depositReceived: number;
}) {
    // Đơn không cọc thì đừng bịa ra một dòng luôn "chưa nhận" không bao giờ xong.
    const rows: Row[] = [
        { label: 'Tiền thuê', due: rentalDue, received: rentalReceived },
        ...(depositTotal > 0
            ? [
                  {
                      label: 'Tiền cọc',
                      due: depositTotal,
                      received: depositReceived,
                  },
              ]
            : []),
    ];

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
                                {money(r.due)}
                            </span>
                        </span>
                        <StatusPill due={r.due} received={r.received} />
                    </div>
                ))}
            </div>

            {rows.some((r) => r.received < r.due) && (
                <p className="mt-2 border-t border-[#f1f4ea] pt-2 text-[11.5px] text-[#5f6650]">
                    Shop cập nhật mục này sau khi nhận được tiền.
                </p>
            )}
        </div>
    );
}
