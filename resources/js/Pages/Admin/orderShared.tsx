// Dùng chung giữa danh sách đơn (Orders.tsx) và màn hình đơn riêng (Orders/Show.tsx) —
// spec 2026-07-26. Chứa type, hằng, helper + các control admin (đổi lịch/vị trí/phụ phí/hoàn)
// và OrderDetailPanel (khối chi tiết đầy đủ 1 đơn). Hành vi backend không đổi (route như cũ).
import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import { money } from '@/lib/format';
import { sessionLabel, shopHours, type Session } from '@/lib/session';
import {
    VOUCHER_SOURCE_FALLBACK,
    VOUCHER_SOURCE_LABEL,
    voucherValueText,
    type VoucherType,
} from '@/lib/voucher';
import { router, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';

export type OrderItem = {
    name: string;
    quantity: number;
    price_per_day: number;
    days: number;
    subtotal: number;
    // % giảm thuê dài ngày đã áp cho dòng (bopcamping-e36e); 0 = không giảm.
    duration_discount_percent: number;
    // bopcamping-d7l: items thuộc combo mang uuid nhóm + giá phân bổ (AC-3)
    combo_group_uuid: string | null;
    combo_name: string | null;
    allocated_price: number | null;
};

// Nhóm items chi tiết đơn: mỗi combo_group_uuid = 1 khối combo, còn lại là dòng lẻ.
export type ItemGroup = {
    key: string;
    combo: string | null;
    items: OrderItem[];
};

// bopcamping-3ag: nguồn giảm giá từng dòng, lưu lúc checkout (đơn cũ = null).
export type DiscountLine = {
    source: string;
    amount: number;
    code?: string;
    percent?: boolean;
};

/** 1 mốc việc trên đơn: đã làm chưa, ai làm, lúc nào. */
export type OrderAction = {
    key: string;
    label: string;
    done: boolean;
    at: string | null;
    by: string | null;
};

export type UsedVoucher = {
    code: string;
    type: VoucherType;
    value: number;
    source: string;
};

export type Order = {
    id: number;
    code: string;
    customer_name: string;
    customer_phone: string;
    customer_email: string | null;
    customer_address: string | null;
    start_date: string;
    end_date: string;
    days: number;
    // Nửa ngày (adr_pricing_models) — đơn cùng ngày trả sớm.
    is_half_day: boolean;
    // Buổi khách chọn (spec 2026-07-26): morning|afternoon|full|null.
    session: Session | null;
    // Giờ nhận/trả mong muốn + phụ phí ngoài khung giờ (Phase 2 turnaround, bopcamping-h4to)
    requested_pickup_time: string | null;
    requested_return_time: string | null;
    // Giờ giao/thu admin ĐÃ CHỐT (bopcamping-641t) — null = chưa chốt. schedule_note chỉ nội bộ.
    confirmed_pickup_time: string | null;
    confirmed_return_time: string | null;
    schedule_note: string | null;
    schedule_confirmed_at: string | null;
    extra_fee: number;
    extra_fee_note: string | null;
    // Danh sách phụ phí [{name,value}] — nguồn hiển thị; extra_fee chỉ là tổng (bopcamping-f1yj).
    // Optional CÓ CHỦ Ý: nếu server bị rollback về bản cũ mà client đã tải JS mới thì
    // trường này vắng — mọi chỗ đọc đều phải ?. để không trắng trang.
    extra_fees?: { name: string; value: number }[];
    // ISO (Y-m-d) cho form đổi lịch (bopcamping-5hjm)
    start_date_iso: string;
    end_date_iso: string;
    total_price: number;
    deposit_total: number;
    discount_total: number;
    amount_due: number;
    discount_breakdown: DiscountLine[] | null;
    status: string;
    payment_status: string;
    // Thu tiền theo 2 khoản ĐỘC LẬP (bopcamping-q7i0) — payment_status chỉ là tóm tắt suy ra.
    rental_due: number;
    rental_paid: boolean;
    rental_paid_at: string | null;
    rental_paid_by: string | null;
    deposit_paid: boolean;
    deposit_paid_at: string | null;
    deposit_paid_by: string | null;
    // Ai đã làm gì: 5 mốc kèm người + vai + giờ (bopcamping-3wfk)
    actions: OrderAction[];
    deposit_refund_status: string;
    deposit_refund_note: string | null;
    note: string | null;
    created_at: string;
    items: OrderItem[];
    vouchers: UsedVoucher[];
    referral: { referrer_name: string | null; status: string } | null;
    // Per-store: cửa hàng thuê + đơn hệ thống tự gán (admin review theo địa chỉ)
    service_location: { id: number; name: string } | null;
    delivery_method: string;
    delivery_method_label: string;
    location_auto_assigned: boolean;
    // Đơn cha/con (bopcamping-wtuv): cha gom N đợt giao; con nằm trong children của cha.
    is_parent: boolean;
    children?: Order[];
    // Chỉ ở màn chi tiết (show): đơn con biết cha để link về.
    parent?: { id: number; code: string } | null;
};

export type StoreOption = { id: number; name: string };

const DISCOUNT_SOURCE_LABEL: Record<string, string> = {
    voucher: 'Voucher',
    referral: 'Mã giới thiệu (đơn đầu)',
    email_bonus: 'Ưu đãi thêm email (đơn đầu)',
    cap: 'Điều chỉnh trần giảm giá',
    // bopcamping-wtuv: phần giảm phân bổ từ voucher tính trên TỔNG đơn gộp (cha)
    parent_alloc: 'Giảm phân bổ từ đơn gộp',
};

// Hoàn cọc — chỉ dùng khi đơn ĐÃ TRẢ (bopcamping-7be).
const REFUND_OPTIONS: {
    key: string;
    label: string;
    active: { bg: string; color: string };
}[] = [
    {
        key: 'pending',
        label: 'Chưa hoàn cọc',
        active: { bg: '#f6ddd6', color: '#b3493a' },
    },
    {
        key: 'refunded',
        label: 'Đã hoàn cọc',
        active: { bg: '#dcebc4', color: '#3a5a1f' },
    },
];

// Trạng thái kế tiếp hợp lệ cho 1 đơn (dùng ở danh sách + màn chi tiết).
export const NEXT_STATUSES: Record<string, string[]> = {
    pending: ['confirmed', 'cancelled'],
    confirmed: ['renting', 'cancelled'],
    renting: ['returned'],
    returned: [],
    cancelled: [],
};

export function groupItems(items: OrderItem[]): ItemGroup[] {
    const groups: ItemGroup[] = [];
    const byUuid = new Map<string, ItemGroup>();
    items.forEach((it, i) => {
        if (it.combo_group_uuid) {
            let g = byUuid.get(it.combo_group_uuid);
            if (!g) {
                g = {
                    key: it.combo_group_uuid,
                    combo: it.combo_name ?? 'Combo',
                    items: [],
                };
                byUuid.set(it.combo_group_uuid, g);
                groups.push(g);
            }
            g.items.push(it);
        } else {
            groups.push({ key: `single-${i}`, combo: null, items: [it] });
        }
    });
    return groups;
}

/** Nhãn buổi kèm giờ theo setting shop; null nếu đơn không có buổi (nhiều ngày). */
export function useSessionLabel(session: Session | null): string | null {
    const site = (usePage().props as { site?: Parameters<typeof shopHours>[0] })
        .site;
    return sessionLabel(session, shopHours(site));
}

/** Không có dữ liệu ngày bận cho admin (server kiểm tồn khi lưu) — Set rỗng dùng chung. */
const NO_UNAVAILABLE = new Set<string>();

/**
 * Thời gian MẶC ĐỊNH của đơn khi admin chưa chốt giờ (feedback 2026-07-28):
 * nửa ngày → nhãn ca sáng/ca chiều kèm khung giờ shop; đơn CẢ NGÀY hoặc nhiều ngày
 * → null (không hiện gì, vì giao lúc nào trong ngày cũng được). Đơn cũ không có buổi
 * nhưng có giờ khách xin thì hiện giờ đó. Khi đã chốt giờ, UI hiện "Thời gian thay đổi".
 */
export function defaultTimeLabel(
    order: Order,
    sessLabel: string | null,
): string | null {
    if (order.session === 'morning' || order.session === 'afternoon')
        return sessLabel;
    if (order.session === 'full') return null;
    if (order.requested_pickup_time || order.requested_return_time) {
        return `giao ${order.requested_pickup_time ?? '—'} · thu ${order.requested_return_time ?? '—'}`;
    }

    return null;
}

function DetailRow({
    label,
    value,
    mono,
    accent,
    bold,
}: {
    label: string;
    value: string;
    mono?: boolean;
    accent?: string;
    bold?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-3 py-0.5">
            <span className="shrink-0 text-moss">{label}</span>
            <span
                className={`text-right text-ink ${mono ? 'font-mono' : ''} ${bold ? 'font-bold' : ''}`}
                style={accent ? { color: accent } : undefined}
            >
                {value}
            </span>
        </div>
    );
}

/**
 * "Ai đã làm gì" trên đơn (bopcamping-3wfk): 5 mốc theo thứ tự việc diễn ra, kèm người +
 * vai + giờ. Mốc đã xảy ra nhưng không có dấu (đơn cũ trước khi có tính năng) ghi rõ
 * "không rõ ai" — thà nói không biết còn hơn đoán sai khi đối soát tiền.
 */
function ActionLog({ actions }: { actions: OrderAction[] }) {
    return (
        <>
            <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                Ai đã làm gì
            </div>
            <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                {actions.map((a) => (
                    <div
                        key={a.key}
                        className="flex items-start justify-between gap-3 border-b border-[#f1f4ea] py-1.5 last:border-0"
                    >
                        <span
                            className={
                                a.done
                                    ? 'font-semibold text-ink'
                                    : 'text-[#a3ad92]'
                            }
                        >
                            {a.done ? '✓ ' : '○ '}
                            {a.label}
                        </span>
                        <span className="text-right text-moss">
                            {!a.done ? (
                                <span className="text-[#c4cca8]">chưa làm</span>
                            ) : a.by ? (
                                <>
                                    <span className="font-semibold text-ink">
                                        {a.by}
                                    </span>
                                    {a.at && (
                                        <span className="ml-1 font-mono text-[11.5px]">
                                            {a.at}
                                        </span>
                                    )}
                                </>
                            ) : (
                                <span style={{ color: '#9a5a1f' }}>
                                    không rõ ai
                                </span>
                            )}
                        </span>
                    </div>
                ))}
            </div>
        </>
    );
}

/**
 * Một dòng "đã thu / chưa thu" cho 1 khoản tiền (bopcamping-q7i0). Bấm để đảo trạng thái;
 * khi đã thu thì hiện AI thu và LÚC NÀO — cần cho đối soát khi shipper thu hộ.
 */
function PaidToggle({
    label,
    amount,
    paid,
    at,
    by,
    disabled,
    onToggle,
}: {
    label: string;
    amount: number;
    paid: boolean;
    at: string | null;
    by: string | null;
    disabled?: boolean;
    onToggle: (paid: boolean) => void;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-[#f1f4ea] py-2 last:border-0">
            <div className="min-w-0">
                <span className="text-[12.5px] font-semibold text-ink">
                    {label}
                </span>
                <span className="ml-1.5 font-mono text-[12.5px] text-moss">
                    {money(amount)}
                </span>
                {paid && (
                    <div className="text-[11px] text-[#a3ad92]">
                        {by ? `${by} nhận` : 'đã nhận (không rõ ai)'}
                        {at ? ` · ${at}` : ''}
                    </div>
                )}
            </div>
            <button
                type="button"
                disabled={disabled}
                aria-pressed={paid}
                onClick={(e) => {
                    e.stopPropagation();
                    onToggle(!paid);
                }}
                className="shrink-0 rounded-[9px] border px-2.5 py-1.5 text-[12px] font-bold transition disabled:cursor-not-allowed disabled:opacity-60"
                style={
                    paid
                        ? {
                              background: '#dcebc4',
                              color: '#3a5a1f',
                              borderColor: '#3a5a1f',
                          }
                        : {
                              background: '#fff',
                              color: '#b3493a',
                              borderColor: '#f0cfc6',
                          }
                }
            >
                {paid ? '✓ Đã thu' : 'Chưa thu'}
            </button>
        </div>
    );
}

/**
 * Hoàn cọc cho đơn ĐÃ TRẢ (bopcamping-7be): chọn Đã/Chưa hoàn + ghi lý do trừ cọc
 * (rách lều, hư hại…). Lưu cả trạng thái lẫn ghi chú trong 1 lần bấm "Lưu".
 */
function RefundControl({ order }: { order: Order }) {
    const [status, setStatus] = useState(
        order.deposit_refund_status ?? 'pending',
    );
    const [note, setNote] = useState(order.deposit_refund_note ?? '');
    const [saving, setSaving] = useState(false);

    const dirty =
        status !== (order.deposit_refund_status ?? 'pending') ||
        note !== (order.deposit_refund_note ?? '');

    const save = () => {
        router.patch(
            route('admin.orders.refund', order.id),
            { deposit_refund_status: status, deposit_refund_note: note },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                Hoàn cọc
            </div>
            <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
                <div className="grid grid-cols-2 gap-2">
                    {REFUND_OPTIONS.map((opt) => {
                        const active = status === opt.key;
                        return (
                            <button
                                key={opt.key}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setStatus(opt.key);
                                }}
                                aria-pressed={active}
                                className="rounded-[9px] border px-2 py-2 text-[12px] font-bold transition"
                                style={
                                    active
                                        ? {
                                              background: opt.active.bg,
                                              color: opt.active.color,
                                              borderColor: opt.active.color,
                                          }
                                        : {
                                              background: '#fff',
                                              color: '#8a967a',
                                              borderColor: '#e3e8d6',
                                          }
                                }
                            >
                                {active && '✓ '}
                                {opt.label}
                            </button>
                        );
                    })}
                </div>
                <textarea
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    onClick={(e) => e.stopPropagation()}
                    rows={2}
                    maxLength={500}
                    placeholder="Lý do trừ cọc nếu có: rách lều, hư hại thiết bị…"
                    className="mt-2 w-full resize-y rounded-[9px] border border-[#e3e8d6] bg-[#fafcf7] px-2.5 py-2 text-[12.5px] text-ink outline-none focus:border-grass"
                />
                <div className="mt-2 flex items-center justify-between gap-2">
                    <span className="text-[11.5px] text-[#a3ad92]">
                        Cọc {money(order.deposit_total)} — trả lại khách sau khi
                        kiểm thiết bị.
                    </span>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            if (dirty && !saving) save();
                        }}
                        disabled={!dirty || saving}
                        className="shrink-0 rounded-[9px] px-3.5 py-1.5 text-[12px] font-bold text-white transition disabled:cursor-not-allowed"
                        style={{
                            background:
                                dirty && !saving ? '#557A2B' : '#c4cfae',
                        }}
                    >
                        {saving ? 'Đang lưu…' : 'Lưu'}
                    </button>
                </div>
            </div>
        </>
    );
}

/**
 * Nhập phụ phí — admin nhập tay sau khi liên hệ khách; cộng vào tiền thuê phải trả.
 * Chỉ đơn thường/đơn con.
 *
 * Ô này gánh HAI loại phí, không chỉ ngoài khung giờ như tên gốc (bopcamping-h4to):
 *   1. Giao/trả ngoài khung giờ (nhận sớm 6h, trả muộn 22h...) — mục đích ban đầu.
 *   2. Phí giao tận nơi, từ khi có delivery_method (bopcamping-z3ug): chủ shop chốt
 *      không tính phí ship ở checkout mà thoả thuận rồi nhập vào đây.
 * Checkout hứa với khách "Phí giao tụi mình báo khi gọi xác nhận đơn" nhưng nhãn cũ chỉ
 * ghi "ngoài khung giờ" nên người nhập dễ tưởng sai ô mà bỏ sót (bopcamping-marf).
 *
 * Hai loại dồn chung một con số nên KHÔNG tách ra đối soát được — muốn tách phải thêm
 * cột riêng. Trước mắt bắt buộc ghi chú để biết khoản đó là phí gì.
 */
function ExtraFeeEditor({ order }: { order: Order }) {
    // Danh sách sửa được tại chỗ. Luôn để sẵn MỘT dòng trống khi đơn chưa có phụ phí,
    // để admin gõ được ngay chứ không phải bấm "+" rồi mới thấy ô nhập.
    const [rows, setRows] = useState<{ name: string; value: string }[]>(() =>
        order.extra_fees?.length
            ? order.extra_fees.map((f) => ({
                  name: f.name,
                  value: String(f.value),
              }))
            : [{ name: '', value: '' }],
    );
    const [saving, setSaving] = useState(false);

    const setRow = (
        i: number,
        patch: Partial<{ name: string; value: string }>,
    ) => setRows((rs) => rs.map((r, j) => (j === i ? { ...r, ...patch } : r)));

    const addRow = () => setRows((rs) => [...rs, { name: '', value: '' }]);

    // Xoá hết thì để lại một dòng trống — lưu lúc đó = gỡ sạch phụ phí (server lọc dòng 0đ).
    const removeRow = (i: number) =>
        setRows((rs) => {
            const next = rs.filter((_, j) => j !== i);
            return next.length ? next : [{ name: '', value: '' }];
        });

    const total = rows.reduce((sum, r) => sum + (Number(r.value) || 0), 0);

    const save = () => {
        setSaving(true);
        router.patch(
            route('admin.orders.fee', order.id),
            {
                fees: rows
                    .filter((r) => Number(r.value) > 0 && r.name.trim() !== '')
                    .map((r) => ({
                        name: r.name.trim(),
                        value: Number(r.value),
                    })),
            },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <div className="mt-3">
            <div className="mb-1 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                Phụ phí (giao tận nơi / ngoài khung giờ)
            </div>
            <p className="mb-2 text-[11.5px] leading-snug text-moss">
                Cộng vào tiền thuê khách trả khi nhận đồ. Mỗi khoản một dòng —
                tên khoản hiện cho khách trong email xác nhận.
            </p>
            <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
                <div className="flex flex-col gap-2">
                    {rows.map((r, i) => (
                        <div key={i} className="flex flex-wrap items-end gap-2">
                            <label className="min-w-[150px] flex-[2]">
                                {i === 0 && (
                                    <span className="mb-1 block text-[11.5px] text-moss">
                                        Tên khoản (khách sẽ thấy)
                                    </span>
                                )}
                                <input
                                    type="text"
                                    value={r.name}
                                    onChange={(e) =>
                                        setRow(i, { name: e.target.value })
                                    }
                                    placeholder="VD: phí giao tận nơi"
                                    className="w-full rounded-[9px] border border-cardBorder px-2.5 py-1.5 text-[13px] outline-none focus:border-grass"
                                />
                            </label>
                            <label className="min-w-[110px] flex-1">
                                {i === 0 && (
                                    <span className="mb-1 block text-[11.5px] text-moss">
                                        Số tiền (₫)
                                    </span>
                                )}
                                <input
                                    type="number"
                                    min="0"
                                    value={r.value}
                                    onChange={(e) =>
                                        setRow(i, { value: e.target.value })
                                    }
                                    placeholder="0"
                                    className="w-full rounded-[9px] border border-cardBorder px-2.5 py-1.5 text-[13px] outline-none focus:border-grass"
                                />
                            </label>
                            <button
                                type="button"
                                onClick={() => removeRow(i)}
                                aria-label={`Xoá khoản ${i + 1}`}
                                title="Xoá khoản này"
                                className="grid h-[34px] w-[34px] place-items-center rounded-[9px] border border-cardBorder text-[16px] leading-none text-moss transition hover:border-[#d8a3a3] hover:text-[#b3493a]"
                            >
                                ×
                            </button>
                        </div>
                    ))}
                </div>

                <div className="mt-2.5 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={addRow}
                        className="rounded-[9px] border border-dashed border-[#cdd6b6] px-3 py-1.5 text-[12.5px] font-semibold text-grass transition hover:border-grass hover:bg-[#f4f7ec]"
                    >
                        + Thêm phụ phí
                    </button>
                    {total > 0 && (
                        <span className="font-mono text-[12.5px] text-moss">
                            Tổng {money(total)}
                        </span>
                    )}
                    <button
                        onClick={save}
                        disabled={saving}
                        className="ml-auto rounded-[9px] bg-grass px-4 py-1.5 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                    >
                        {saving ? 'Đang lưu…' : 'Lưu phụ phí'}
                    </button>
                </div>
            </div>
        </div>
    );
}

/**
 * Chốt giờ giao/thu cho đơn (bopcamping-641t) — theo khuôn ExtraFeeEditor (inline card,
 * không modal). 2 × input[type=time] (giao/thu) + ghi chú nội bộ shipper + nút Lưu.
 * Xoá trắng 1 ô giờ = gửi null = huỷ chốt ô đó. Không ghi đè requested_* (giờ khách xin).
 */
export function ScheduleEditor({ order }: { order: Order }) {
    const errors = usePage().props.errors as Record<string, string>;
    const [pickup, setPickup] = useState<string>(
        order.confirmed_pickup_time ?? '',
    );
    const [returnTime, setReturnTime] = useState<string>(
        order.confirmed_return_time ?? '',
    );
    const [note, setNote] = useState<string>(order.schedule_note ?? '');
    const [saving, setSaving] = useState(false);

    const save = (e: React.MouseEvent) => {
        e.stopPropagation();
        setSaving(true);
        router.patch(
            route('admin.orders.schedule', order.id),
            {
                confirmed_pickup_time: pickup || null,
                confirmed_return_time: returnTime || null,
                schedule_note: note || null,
            },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const scheduleError =
        errors.confirmed_pickup_time ??
        errors.confirmed_return_time ??
        errors.schedule_note;

    return (
        <div className="mt-3">
            <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                Giờ giao/thu đã chốt
            </div>
            <div className="flex flex-wrap items-end gap-2 rounded-[10px] border border-[#eef2e3] bg-white p-3">
                <label className="min-w-[110px] flex-1">
                    <span className="mb-1 block text-[11.5px] text-moss">
                        Giờ giao
                    </span>
                    <input
                        type="time"
                        value={pickup}
                        onClick={(e) => e.stopPropagation()}
                        onChange={(e) => setPickup(e.target.value)}
                        className="w-full rounded-[9px] border border-cardBorder px-2.5 py-1.5 text-[13px] outline-none focus:border-grass"
                    />
                </label>
                <label className="min-w-[110px] flex-1">
                    <span className="mb-1 block text-[11.5px] text-moss">
                        Giờ thu
                    </span>
                    <input
                        type="time"
                        value={returnTime}
                        onClick={(e) => e.stopPropagation()}
                        onChange={(e) => setReturnTime(e.target.value)}
                        className="w-full rounded-[9px] border border-cardBorder px-2.5 py-1.5 text-[13px] outline-none focus:border-grass"
                    />
                </label>
                <label className="min-w-[150px] flex-[2]">
                    <span className="mb-1 block text-[11.5px] text-moss">
                        Ghi chú cho shipper
                    </span>
                    <input
                        type="text"
                        value={note}
                        maxLength={255}
                        onClick={(e) => e.stopPropagation()}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="VD: gọi trước 15 phút, nhà cuối hẻm"
                        className="w-full rounded-[9px] border border-cardBorder px-2.5 py-1.5 text-[13px] outline-none focus:border-grass"
                    />
                </label>
                <button
                    onClick={save}
                    disabled={saving}
                    className="rounded-[9px] bg-grass px-4 py-1.5 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                >
                    {saving ? 'Đang lưu…' : 'Lưu giờ'}
                </button>
            </div>
            {scheduleError && (
                <p className="mt-1.5 text-[12px] text-[#b3493a]">
                    {scheduleError}
                </p>
            )}
        </div>
    );
}

/**
 * Đổi lịch thuê (bopcamping-5hjm) — chỉ đơn pending/confirmed. Nút mở modal lịch
 * 2 tháng (DateRangeCalendar). Preview số ngày + tiền thuê mới tính client-side
 * (scale tuyến tính subtotal); server là source of truth (kiểm tồn + tính lại + mail).
 */
function DatesChanger({
    order,
    maxDiscountPercent,
}: {
    order: Order;
    maxDiscountPercent: number;
}) {
    const errors = usePage().props.errors as Record<string, string>;
    const [open, setOpen] = useState(false);
    const [start, setStart] = useState<string | null>(order.start_date_iso);
    const [end, setEnd] = useState<string | null>(order.end_date_iso);
    const [saving, setSaving] = useState(false);

    const msPerDay = 86_400_000;
    const newDays =
        start && end
            ? Math.round((Date.parse(end) - Date.parse(start)) / msPerDay) + 1
            : 0;
    const oldDays = Math.max(1, order.days);
    const newTotal =
        newDays > 0
            ? order.items.reduce(
                  (sum, it) =>
                      sum + Math.round((it.subtotal * newDays) / oldDays),
                  0,
              )
            : 0;
    let newDiscount = order.discount_total;
    if (newDays > 0) {
        if (order.discount_breakdown?.length) {
            const preCap = order.discount_breakdown
                .filter((d) => d.source !== 'cap')
                .reduce(
                    (sum, d) =>
                        sum +
                        (d.percent
                            ? Math.round((d.amount * newDays) / oldDays)
                            : d.amount),
                    0,
                );
            newDiscount = Math.max(
                0,
                Math.min(
                    preCap,
                    Math.floor((newTotal * maxDiscountPercent) / 100),
                    newTotal,
                ),
            );
        } else {
            newDiscount = Math.max(
                0,
                Math.min(
                    order.discount_total,
                    Math.floor((newTotal * maxDiscountPercent) / 100),
                    newTotal,
                ),
            );
        }
    }
    const newAmountDue = newTotal + order.deposit_total - newDiscount;
    const dirty = start !== order.start_date_iso || end !== order.end_date_iso;
    const canSave = !!start && !!end && dirty;
    const fmt = (iso: string) => iso.split('-').reverse().join('/');

    const openModal = () => {
        setStart(order.start_date_iso);
        setEnd(order.end_date_iso);
        setOpen(true);
    };

    const save = () => {
        setSaving(true);
        router.patch(
            route('admin.orders.dates', order.id),
            { start_date: start, end_date: end },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-[12.5px] text-ink">
                    <span className="font-mono font-semibold">
                        {order.start_date} → {order.end_date}
                    </span>
                    <span className="ml-1 text-moss">({order.days} ngày)</span>
                </span>
                <button
                    onClick={openModal}
                    className="flex items-center gap-1.5 rounded-[9px] border border-cardBorder bg-white px-3 py-1.5 text-[12.5px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                >
                    <svg
                        width="13"
                        height="13"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                    >
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <path d="M16 2v4M8 2v4M3 10h18" />
                    </svg>
                    Đổi lịch
                </button>
            </div>

            {open && (
                <div
                    className="fixed inset-0 z-[200] grid place-items-center overflow-y-auto p-4"
                    style={{ background: 'rgba(24,35,15,.45)' }}
                >
                    <div className="my-auto w-full max-w-[680px] rounded-[18px] border border-cardBorder bg-white p-5">
                        <div className="mb-1 flex items-start justify-between gap-3">
                            <div className="text-[17px] font-bold text-ink">
                                Đổi lịch đơn{' '}
                                <span className="font-mono text-grass">
                                    {order.code}
                                </span>
                            </div>
                            <button
                                onClick={() => setOpen(false)}
                                aria-label="Đóng"
                                className="shrink-0 rounded-full p-1 text-[#8a967a] hover:bg-[#f1f4ea]"
                            >
                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                >
                                    <path d="m6 6 12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                        <p className="mb-3 text-[13px] text-moss">
                            Lịch hiện tại:{' '}
                            <strong>
                                {order.start_date} → {order.end_date}
                            </strong>{' '}
                            ({order.days} ngày). Tồn kho khoảng mới được kiểm
                            tra khi lưu.
                        </p>

                        <DateRangeCalendar
                            start={start}
                            end={end}
                            unavailable={NO_UNAVAILABLE}
                            onChange={(s, e) => {
                                setStart(s);
                                setEnd(e);
                            }}
                        />

                        <div className="mt-2 text-center text-[13px] text-moss">
                            {start && end ? (
                                <>
                                    Thuê{' '}
                                    <strong className="text-ink">
                                        {fmt(start)} → {fmt(end)}
                                    </strong>{' '}
                                    · {newDays} ngày · tiền thuê mới{' '}
                                    <strong className="font-mono text-ink">
                                        {money(newTotal)}
                                    </strong>
                                    {newDiscount > 0 && (
                                        <>
                                            {' '}
                                            · giảm{' '}
                                            <strong className="font-mono text-grass">
                                                −{money(newDiscount)}
                                            </strong>
                                        </>
                                    )}
                                    <span className="ml-1 text-[#8a6d3a]">
                                        (cọc giữ nguyên · phải trả{' '}
                                        <strong className="font-mono text-ink">
                                            {money(newAmountDue)}
                                        </strong>
                                        )
                                    </span>
                                </>
                            ) : (
                                'Chạm chọn ngày nhận và ngày trả.'
                            )}
                        </div>

                        {errors.dates && (
                            <p
                                className="mt-2 rounded-[8px] px-3 py-2 text-[12.5px]"
                                style={{
                                    background: '#fdf3f1',
                                    color: '#b3493a',
                                }}
                            >
                                {errors.dates}
                            </p>
                        )}
                        {(errors.start_date || errors.end_date) && (
                            <p
                                className="mt-2 rounded-[8px] px-3 py-2 text-[12.5px]"
                                style={{
                                    background: '#fdf3f1',
                                    color: '#b3493a',
                                }}
                            >
                                {errors.start_date ?? errors.end_date}
                            </p>
                        )}

                        <div className="mt-4 flex flex-col gap-2.5 sm:flex-row-reverse">
                            <button
                                onClick={save}
                                disabled={!canSave || saving}
                                className="flex h-[48px] flex-1 items-center justify-center rounded-control text-[15px] font-bold text-white transition disabled:cursor-not-allowed"
                                style={{
                                    background:
                                        canSave && !saving
                                            ? '#557A2B'
                                            : '#c4cfae',
                                }}
                            >
                                {saving ? 'Đang lưu…' : 'Lưu lịch mới'}
                            </button>
                            <button
                                onClick={() => setOpen(false)}
                                className="h-[48px] rounded-control border border-[#cdd6b6] bg-white px-6 text-[14px] font-semibold text-pine sm:flex-none"
                            >
                                Huỷ
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

/** Per-store: hiện cửa hàng đơn + cho admin đổi store (kiểm tồn ở backend). */
function StoreChanger({
    order,
    locations,
}: {
    order: Order;
    locations: StoreOption[];
}) {
    const errors = usePage().props.errors as Record<string, string>;
    const change = (id: number) => {
        if (id === order.service_location?.id) return;
        router.patch(
            route('admin.orders.location', order.id),
            { service_location_id: id },
            { preserveScroll: true },
        );
    };
    return (
        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
            <div className="flex flex-wrap gap-2">
                {locations.map((l) => {
                    const on = order.service_location?.id === l.id;
                    return (
                        <button
                            key={l.id}
                            onClick={() => change(l.id)}
                            className={`rounded-[9px] border px-3 py-1.5 text-[12.5px] font-semibold transition ${
                                on
                                    ? 'border-grass bg-grass text-white'
                                    : 'border-cardBorder text-pine hover:border-grass'
                            }`}
                        >
                            {l.name}
                        </button>
                    );
                })}
            </div>
            {errors.location && (
                <p className="mt-1.5 text-[12px] text-[#b3493a]">
                    {errors.location}
                </p>
            )}
        </div>
    );
}

/**
 * Khối chi tiết đầy đủ 1 đơn (khách + thiết bị + thanh toán + ưu đãi + action). Dùng ở
 * màn hình đơn riêng (Orders/Show.tsx). Đơn con/thường mới có action; đơn cha bọc envelope.
 */
export function OrderDetailPanel({
    order,
    locations,
    maxDiscountPercent,
}: {
    order: Order;
    locations: StoreOption[];
    maxDiscountPercent: number;
}) {
    const sessLabel = useSessionLabel(order.session);
    const defaultTimeText = defaultTimeLabel(order, sessLabel);
    const togglePaid = (kind: 'rental' | 'deposit', paid: boolean) => {
        router.patch(
            route('admin.orders.payment', order.id),
            { kind, paid },
            { preserveScroll: true },
        );
    };

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {/* Cột trái: khách + thiết bị */}
            <div>
                <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Khách hàng
                </div>
                <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                    <DetailRow label="Họ tên" value={order.customer_name} />
                    <DetailRow label="SĐT" value={order.customer_phone} mono />
                    <DetailRow
                        label="Email"
                        value={order.customer_email ?? '—'}
                        mono
                    />
                    <DetailRow
                        label="Địa chỉ"
                        value={order.customer_address ?? '—'}
                    />
                    <DetailRow
                        label="Khoảng thuê"
                        value={`${order.start_date} → ${order.end_date} (${order.days} ngày)`}
                    />
                    {/* Thời gian mặc định (chưa chốt giờ): ca sáng/chiều theo buổi khách chọn, hoặc
                        giờ khách xin. Đơn CẢ NGÀY / nhiều ngày không hiện gì (feedback 2026-07-28). */}
                    {defaultTimeText && (
                        <DetailRow label="Thời gian" value={defaultTimeText} />
                    )}
                    {/* Chỉ hiện khi admin ĐÃ chốt giờ — đây là giờ shipper phải theo. */}
                    {(order.confirmed_pickup_time ||
                        order.confirmed_return_time) && (
                        <DetailRow
                            label="Thời gian thay đổi"
                            value={`giao ${order.confirmed_pickup_time ?? '—'} · thu ${order.confirmed_return_time ?? '—'}${order.schedule_confirmed_at ? ` · chốt ${order.schedule_confirmed_at}` : ''}`}
                            mono
                            bold
                            accent="#b3493a"
                        />
                    )}
                    <DetailRow label="Đặt lúc" value={order.created_at} />
                </div>

                {/* Chốt giờ giao/thu (bopcamping-641t) — đơn con/thường, chưa trả/huỷ */}
                {!order.is_parent &&
                    !['returned', 'cancelled'].includes(order.status) && (
                        <ScheduleEditor order={order} />
                    )}

                {/* Per-store: cửa hàng thuê + đổi store */}
                <div className="mb-2 mt-3 flex items-center gap-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Cơ sở giao
                    {order.location_auto_assigned && (
                        <span className="rounded-pill bg-[#f7e7da] px-2 py-0.5 text-[10px] font-semibold normal-case text-[#8a5a1f]">
                            Hệ thống gán · cần duyệt
                        </span>
                    )}
                    {order.service_location &&
                        !order.location_auto_assigned && (
                            <span className="rounded-pill bg-[#eef5e1] px-2 py-0.5 text-[10px] font-semibold normal-case text-grass">
                                Khách chọn
                            </span>
                        )}
                </div>
                <StoreChanger order={order} locations={locations} />

                {/* Hình thức giao khách chọn (bopcamping-z3ug). Đơn 'ship' phải báo phí
                    cho khách rồi nhập vào Phí phát sinh — checkout không tự tính. */}
                <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Hình thức giao
                </div>
                <div
                    className={`rounded-[11px] border px-3 py-2.5 text-[13px] ${
                        order.delivery_method === 'ship'
                            ? 'border-[#e6c9a8] bg-[#fdf6ee]'
                            : 'border-cardBorder bg-white'
                    }`}
                >
                    <span className="font-bold text-ink">
                        {order.delivery_method_label}
                    </span>
                    {order.delivery_method === 'ship' ? (
                        <p className="mt-0.5 text-[12px] text-[#8a5a1f]">
                            Khách chọn Bốp giao — nhớ báo phí giao khi gọi xác
                            nhận, rồi nhập vào <b>Phí phát sinh</b> bên dưới.
                        </p>
                    ) : (
                        <p className="mt-0.5 text-[12px] text-moss">
                            Khách tự đến kho lấy và kiểm đồ tại chỗ. Không phát
                            sinh phí giao.
                        </p>
                    )}
                </div>

                {/* Đổi lịch — chỉ đơn chưa giao (bopcamping-5hjm) */}
                {['pending', 'confirmed'].includes(order.status) && (
                    <>
                        <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                            Đổi lịch thuê
                        </div>
                        <DatesChanger
                            order={order}
                            maxDiscountPercent={maxDiscountPercent}
                        />
                    </>
                )}

                <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Thiết bị
                </div>
                <div className="overflow-hidden rounded-[10px] border border-[#eef2e3]">
                    <table className="w-full text-[12px]">
                        <thead>
                            <tr style={{ background: '#f1f4ea' }}>
                                <th className="px-3 py-2 text-left font-semibold text-moss">
                                    Thiết bị
                                </th>
                                <th className="px-3 py-2 text-center font-semibold text-moss">
                                    SL × ngày
                                </th>
                                <th className="px-3 py-2 text-right font-semibold text-moss">
                                    Thành tiền
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {groupItems(order.items).map((g) =>
                                g.combo === null ? (
                                    <tr
                                        key={g.key}
                                        className="border-t border-[#eef2e3]"
                                    >
                                        <td className="px-3 py-2 text-ink">
                                            {g.items[0].name}
                                            <div className="text-[11px] text-moss">
                                                {money(
                                                    g.items[0].price_per_day,
                                                )}
                                                /ngày
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-center text-moss">
                                            {g.items[0].quantity} ×{' '}
                                            {g.items[0].days}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="font-mono font-bold text-ink">
                                                {money(g.items[0].subtotal)}
                                            </div>
                                            {g.items[0]
                                                .duration_discount_percent >
                                                0 && (
                                                <div className="text-[10.5px]">
                                                    <span className="font-mono text-[#8a967a] line-through">
                                                        {money(
                                                            g.items[0]
                                                                .price_per_day *
                                                                g.items[0]
                                                                    .quantity *
                                                                g.items[0].days,
                                                        )}
                                                    </span>
                                                    <span className="ml-1 font-bold text-[#3a5a1f]">
                                                        −
                                                        {
                                                            g.items[0]
                                                                .duration_discount_percent
                                                        }
                                                        % thuê dài ngày
                                                    </span>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ) : (
                                    <Fragment key={g.key}>
                                        <tr
                                            className="border-t border-[#eef2e3]"
                                            style={{ background: '#f3f7ec' }}
                                        >
                                            <td
                                                className="px-3 py-2"
                                                colSpan={2}
                                            >
                                                <span className="mr-1.5 rounded-pill bg-grass px-1.5 py-0.5 font-mono text-[9.5px] font-bold text-white">
                                                    COMBO
                                                </span>
                                                <span className="font-bold text-pine">
                                                    {g.combo}
                                                </span>
                                                <div className="mt-0.5 text-[11px] text-moss">
                                                    {g.items.length} món · tổng
                                                    giá phân bổ = giá combo
                                                </div>
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="font-mono font-bold text-pine">
                                                    {money(
                                                        g.items.reduce(
                                                            (s, it) =>
                                                                s + it.subtotal,
                                                            0,
                                                        ),
                                                    )}
                                                </div>
                                                {g.items[0]
                                                    .duration_discount_percent >
                                                    0 && (
                                                    <div className="text-[10.5px]">
                                                        <span className="font-mono text-[#8a967a] line-through">
                                                            {money(
                                                                g.items.reduce(
                                                                    (s, it) =>
                                                                        s +
                                                                        (it.allocated_price ??
                                                                            it.price_per_day) *
                                                                            it.days,
                                                                    0,
                                                                ),
                                                            )}
                                                        </span>
                                                        <span className="ml-1 font-bold text-[#3a5a1f]">
                                                            −
                                                            {
                                                                g.items[0]
                                                                    .duration_discount_percent
                                                            }
                                                            % thuê dài ngày
                                                        </span>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                        {g.items.map((item, i) => (
                                            <tr
                                                key={i}
                                                className="border-t border-[#f3f7ec]"
                                            >
                                                <td className="py-1.5 pl-7 pr-3 text-ink">
                                                    {item.name}
                                                    <div className="text-[11px] text-moss">
                                                        phân bổ{' '}
                                                        {money(
                                                            item.allocated_price ??
                                                                item.price_per_day,
                                                        )}
                                                        /ngày · giá lẻ{' '}
                                                        {money(
                                                            item.price_per_day,
                                                        )}
                                                        /ngày
                                                    </div>
                                                </td>
                                                <td className="px-3 py-1.5 text-center text-moss">
                                                    {item.quantity} ×{' '}
                                                    {item.days}
                                                </td>
                                                <td className="px-3 py-1.5 text-right font-mono text-ink">
                                                    {money(item.subtotal)}
                                                </td>
                                            </tr>
                                        ))}
                                    </Fragment>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Cột phải: tiền + ưu đãi + ghi chú */}
            <div>
                <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Thanh toán
                </div>
                <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                    {(() => {
                        const grossRental = order.items.reduce(
                            (s, it) =>
                                s +
                                (it.combo_group_uuid
                                    ? (it.allocated_price ?? it.price_per_day)
                                    : it.price_per_day * it.quantity) *
                                    it.days,
                            0,
                        );
                        const durSaved = grossRental - order.total_price;
                        return (
                            <div className="flex items-start justify-between gap-3 py-0.5">
                                <span className="shrink-0 text-moss">
                                    Tiền thuê
                                </span>
                                <span className="text-right">
                                    {durSaved > 0 && (
                                        <span className="mr-1.5 font-mono text-[11px] text-[#8a967a] line-through">
                                            {money(grossRental)}
                                        </span>
                                    )}
                                    <span className="font-mono text-ink">
                                        {money(order.total_price)}
                                    </span>
                                    {durSaved > 0 && (
                                        <div className="text-[10.5px] text-[#3a5a1f]">
                                            đã giảm thuê dài ngày −
                                            {money(durSaved)}
                                        </div>
                                    )}
                                </span>
                            </div>
                        );
                    })()}
                    {order.discount_total > 0 && (
                        <DetailRow
                            label="Giảm giá"
                            value={`−${money(order.discount_total)}`}
                            mono
                            accent="#3a5a1f"
                        />
                    )}
                    <DetailRow
                        label="Tiền cọc"
                        value={money(order.deposit_total)}
                        mono
                    />
                    {/* Mỗi phụ phí một dòng, khớp đúng thứ khách thấy trong mail (bopcamping-f1yj) */}
                    {order.extra_fees?.map((f, i) => (
                        <DetailRow
                            key={i}
                            label={`Phụ phí (${f.name})`}
                            value={`+${money(f.value)}`}
                            mono
                            accent="#8a5a1f"
                        />
                    ))}
                    <div className="mt-1 flex items-center justify-between border-t border-[#eef2e3] pt-2">
                        <span className="font-bold text-ink">Trả khi nhận</span>
                        <span className="font-mono text-[14px] font-extrabold text-pine">
                            {money(order.amount_due)}
                        </span>
                    </div>
                </div>

                {/* Phụ phí: giao tận nơi HOẶC ngoài khung giờ — admin nhập tay (bopcamping-marf) */}
                {!order.is_parent && <ExtraFeeEditor order={order} />}

                <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Ưu đãi đã dùng
                </div>
                <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                    {(order.discount_breakdown ?? []).map((d, i) => (
                        <div
                            key={i}
                            className="flex items-center justify-between gap-2 py-0.5"
                        >
                            <span className="text-ink">
                                {DISCOUNT_SOURCE_LABEL[d.source] ?? d.source}
                                {d.code && (
                                    <span className="ml-1 font-mono font-semibold text-pine">
                                        {d.code}
                                    </span>
                                )}
                            </span>
                            <span
                                className="font-mono font-bold"
                                style={{
                                    color:
                                        d.amount >= 0 ? '#3a5a1f' : '#b3493a',
                                }}
                            >
                                {d.amount >= 0
                                    ? `−${money(d.amount)}`
                                    : `+${money(-d.amount)}`}
                            </span>
                        </div>
                    ))}
                    {!order.discount_breakdown?.length &&
                        order.discount_total > 0 && (
                            <div className="flex items-center justify-between py-0.5">
                                <span className="text-moss">
                                    Giảm giá (đơn cũ — không có chi tiết nguồn)
                                </span>
                                <span className="font-mono font-bold text-grass">
                                    −{money(order.discount_total)}
                                </span>
                            </div>
                        )}
                    {!order.discount_breakdown?.length &&
                        order.vouchers.map((v) => (
                            <div
                                key={v.code}
                                className="flex items-center justify-between py-0.5"
                            >
                                <span className="font-mono font-semibold text-ink">
                                    {v.code}
                                </span>
                                <span className="text-moss">
                                    {VOUCHER_SOURCE_LABEL[v.source] ??
                                        VOUCHER_SOURCE_FALLBACK}{' '}
                                    ·{' '}
                                    <strong className="text-grass">
                                        {voucherValueText(v.type, v.value)}
                                    </strong>
                                </span>
                            </div>
                        ))}
                    {order.referral && (
                        <div className="flex items-center justify-between py-0.5">
                            <span className="text-ink">🎁 Mã giới thiệu</span>
                            <span className="text-moss">
                                từ{' '}
                                <strong>
                                    {order.referral.referrer_name ?? '—'}
                                </strong>
                            </span>
                        </div>
                    )}
                    {!order.discount_breakdown?.length &&
                        order.vouchers.length === 0 &&
                        !order.referral &&
                        order.discount_total === 0 && (
                            <span className="text-moss">Không có</span>
                        )}
                </div>

                {/* Thu tiền: 2 khoản ĐỘC LẬP (bopcamping-q7i0) — khách có thể chuyển tiền thuê
                    trước, cọc trả khi nhận đồ. Shipper cũng đánh dấu được trong app của họ. */}
                <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Thu tiền
                </div>
                <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
                    <PaidToggle
                        label="Tiền thuê"
                        amount={order.rental_due}
                        paid={order.rental_paid}
                        at={order.rental_paid_at}
                        by={order.rental_paid_by}
                        disabled={order.status === 'cancelled'}
                        onToggle={(paid) => togglePaid('rental', paid)}
                    />
                    <PaidToggle
                        label="Tiền cọc"
                        amount={order.deposit_total}
                        paid={order.deposit_paid}
                        at={order.deposit_paid_at}
                        by={order.deposit_paid_by}
                        disabled={order.status === 'cancelled'}
                        onToggle={(paid) => togglePaid('deposit', paid)}
                    />
                    <p className="mt-2 border-t border-[#f1f4ea] pt-2 text-[11.5px] text-[#a3ad92]">
                        Tổng phải thu khi giao {money(order.amount_due)}. Khoản
                        nào chưa thu thì shipper thu hộ được.
                    </p>
                </div>

                {order.status === 'returned' && <RefundControl order={order} />}

                <ActionLog actions={order.actions} />

                {order.note && (
                    <p className="mt-3 rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px] text-moss">
                        <span className="font-semibold text-ink">Ghi chú:</span>{' '}
                        {order.note}
                    </p>
                )}
            </div>
        </div>
    );
}
