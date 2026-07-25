import { usePage } from '@inertiajs/react';

/**
 * Dòng nhắc khung giờ nhận/trả (bopcamping-n6mr). Ưu tiên giờ RIÊNG của sản phẩm;
 * trống thì fallback khung giờ mặc định toàn shop (shared prop `site`).
 * Chỉ hiển thị kỳ vọng cho khách — ngoài khung giờ khách liên hệ để sắp xếp.
 */
export default function PickupReturnNote({
    pickupHour,
    returnHour,
    className = '',
}: {
    pickupHour?: number | null;
    returnHour?: number | null;
    className?: string;
}) {
    const site = (usePage().props as { site?: { pickup_hour?: number; return_hour?: number } }).site;
    const pickup = pickupHour ?? site?.pickup_hour ?? 8;
    const ret = returnHour ?? site?.return_hour ?? 20;

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
