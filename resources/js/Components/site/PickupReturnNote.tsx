import { usePage } from '@inertiajs/react';

/**
 * Dòng nhắc khung giờ giao/trả (adr_turnaround_buffer). Ưu tiên giờ RIÊNG của sản phẩm
 * (bopcamping-fica); trống thì fallback khung giờ chung của shop (shared prop `site`).
 * Chỉ hiển thị kỳ vọng cho khách, KHÔNG ảnh hưởng tồn kho.
 */
export default function PickupReturnNote({ className = '' }: { className?: string }) {
    const site = (usePage().props as { site?: { pickup_hour?: number; return_hour?: number } }).site;
    const pickup = site?.pickup_hour ?? 8;
    const ret = site?.return_hour ?? 20;

    return (
        <p className={`flex items-start gap-1.5 text-[12px] text-moss ${className}`}>
            <span aria-hidden>🕗</span>
            <span>
                Nhận từ <span className="font-semibold text-ink">{pickup}h</span>, trả trước{' '}
                <span className="font-semibold text-ink">{ret}h</span>. Ngoài khung giờ vui lòng liên hệ để sắp xếp.
            </span>
        </p>
    );
}
