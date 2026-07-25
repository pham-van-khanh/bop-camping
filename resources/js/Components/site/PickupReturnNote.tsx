import { usePage } from '@inertiajs/react';

/**
 * Dòng nhắc khung giờ nhận/trả MẶC ĐỊNH của hệ thống (bopcamping-n6mr) — đọc từ shared
 * prop `site`. Dùng cho thuê nhiều ngày (khách không tự chọn giờ). Chỉ hiển thị kỳ vọng.
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
