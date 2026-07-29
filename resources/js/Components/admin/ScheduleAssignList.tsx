// Danh sách đơn 1 lượt (giao hoặc thu) trong trang Lịch giao của admin, kèm gán shipper
// và kéo-thả thứ tự đi (bopcamping-yc7d). Kéo-thả dùng Reorder của framer-motion — cùng
// pattern với MediaGallery, nhưng có TAY CẦM riêng (dragListener={false} + dragControls)
// để bấm select/nút trong card không bị hiểu là bắt đầu kéo.
import { router } from '@inertiajs/react';
import { Reorder, useDragControls } from 'framer-motion';
import { ReactNode, useEffect, useState } from 'react';

export type AssignableOrder = { id: number; shipper_id: number | null };

export type ShipperOption = { id: number; name: string };

export default function ScheduleAssignList<T extends AssignableOrder>({
    leg,
    date,
    orders,
    shippers,
    emptyText,
    renderCard,
}: {
    leg: 'pickup' | 'return';
    date: string;
    orders: T[];
    shippers: ShipperOption[];
    emptyText: string;
    renderCard: (order: T, index: number) => ReactNode;
}) {
    // Thứ tự cục bộ để kéo-thả mượt; đồng bộ lại khi server trả danh sách mới.
    const [items, setItems] = useState(orders);
    useEffect(() => setItems(orders), [orders]);

    const persistOrder = () => {
        router.post(
            route('admin.schedule.reorder'),
            { leg, date, order_ids: items.map((o) => o.id) },
            { preserveScroll: true, preserveState: true },
        );
    };

    if (items.length === 0) {
        return (
            <div className="rounded-[16px] border border-cardBorder bg-white py-10 text-center text-[13px] text-moss">
                {emptyText}
            </div>
        );
    }

    return (
        <Reorder.Group axis="y" values={items} onReorder={setItems} className="flex flex-col gap-3">
            {items.map((order, i) => (
                <Row
                    key={order.id}
                    order={order}
                    leg={leg}
                    shippers={shippers}
                    onDragEnd={persistOrder}
                >
                    {renderCard(order, i)}
                </Row>
            ))}
        </Reorder.Group>
    );
}

function Row<T extends AssignableOrder>({
    order,
    leg,
    shippers,
    onDragEnd,
    children,
}: {
    order: T;
    leg: 'pickup' | 'return';
    shippers: ShipperOption[];
    onDragEnd: () => void;
    children: ReactNode;
}) {
    const controls = useDragControls();
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
        <Reorder.Item value={order} dragListener={false} dragControls={controls} onDragEnd={onDragEnd}>
            <div className="flex items-stretch gap-2">
                {/* Tay cầm kéo — chỉ chỗ này mới kéo được */}
                <button
                    type="button"
                    aria-label="Kéo để đổi thứ tự"
                    onPointerDown={(e) => controls.start(e)}
                    className="w-8 flex-none cursor-grab rounded-[10px] border border-cardBorder bg-white text-[15px] text-[#a3ad92] active:cursor-grabbing"
                >
                    ⋮⋮
                </button>
                <div className="min-w-0 flex-1">
                    {children}
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
                </div>
            </div>
        </Reorder.Item>
    );
}
