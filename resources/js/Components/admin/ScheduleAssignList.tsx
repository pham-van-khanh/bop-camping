// Danh sách đơn 1 lượt (giao hoặc thu) trong trang Lịch giao của admin, kèm ô chọn shipper
// (bopcamping-yc7d). Thứ tự do giờ đã chốt quyết định — chủ shop đã bỏ chức năng kéo-thả
// sắp thứ tự vì thừa (feedback 29/07/2026).
import { router } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

export type AssignableOrder = { id: number; shipper_id: number | null };

export type ShipperOption = { id: number; name: string };

export default function ScheduleAssignList<T extends AssignableOrder>({
    leg,
    orders,
    shippers,
    emptyText,
    renderCard,
}: {
    leg: 'pickup' | 'return';
    orders: T[];
    shippers: ShipperOption[];
    emptyText: string;
    renderCard: (order: T, index: number) => ReactNode;
}) {
    if (orders.length === 0) {
        return (
            <div className="rounded-[16px] border border-cardBorder bg-white py-10 text-center text-[13px] text-moss">
                {emptyText}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            {orders.map((order, i) => (
                <div key={order.id}>
                    {renderCard(order, i)}
                    <ShipperPicker order={order} leg={leg} shippers={shippers} />
                </div>
            ))}
        </div>
    );
}

function ShipperPicker<T extends AssignableOrder>({
    order,
    leg,
    shippers,
}: {
    order: T;
    leg: 'pickup' | 'return';
    shippers: ShipperOption[];
}) {
    const [saving, setSaving] = useState(false);

    const assign = (value: string) => {
        setSaving(true);
        router.patch(
            route('admin.schedule.assign', order.id),
            { leg, shipper_id: value === '' ? null : Number(value) },
            { preserveScroll: true, preserveState: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <div className="mt-2 flex flex-wrap items-center gap-2 rounded-[10px] border border-[#eef2e3] bg-white px-3 py-2">
            <span className="text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Shipper</span>
            <select
                value={order.shipper_id ?? ''}
                disabled={saving}
                onChange={(e) => assign(e.target.value)}
                className="min-h-[36px] flex-1 rounded-[9px] border border-cardBorder bg-white px-2 text-[13px] text-ink outline-none focus:border-grass disabled:opacity-60"
            >
                <option value="">— chưa gán —</option>
                {shippers.map((s) => (
                    <option key={s.id} value={s.id}>
                        {s.name}
                    </option>
                ))}
            </select>
        </div>
    );
}
