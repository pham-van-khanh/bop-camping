import SiteLayout from '@/Layouts/SiteLayout';
import { catLabel, PRODUCTS } from '@/lib/catalog';
import { money } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

const STATUS = [
    'Chờ xác nhận',
    'Đã xác nhận',
    'Đang thuê',
    'Đã trả',
    'Đã huỷ',
] as const;
type Status = (typeof STATUS)[number];

const STATUS_COLOR: Record<Status, { c: string; bg: string }> = {
    'Chờ xác nhận': { c: '#9a7a2a', bg: '#fbf2d8' },
    'Đã xác nhận': { c: '#2a6ea0', bg: '#dceaf6' },
    'Đang thuê': { c: '#3a5a1f', bg: '#dcebc4' },
    'Đã trả': { c: '#5C6E47', bg: '#e7ecdc' },
    'Đã huỷ': { c: '#b3493a', bg: '#f6ddd6' },
};

type Order = {
    code: string;
    name: string;
    phone: string;
    dates: string;
    status: Status;
    total: number;
};

const INITIAL_ORDERS: Order[] = [
    {
        code: 'BOP-2048',
        name: 'Nguyễn Minh',
        phone: '0905123456',
        dates: '18/06 → 21/06',
        status: 'Chờ xác nhận',
        total: 525000,
    },
    {
        code: 'BOP-2041',
        name: 'Trần Lan',
        phone: '0978222333',
        dates: '22/06 → 24/06',
        status: 'Đã xác nhận',
        total: 380000,
    },
    {
        code: 'BOP-2033',
        name: 'Lê Hùng',
        phone: '0912888777',
        dates: '25/06 → 27/06',
        status: 'Đang thuê',
        total: 360000,
    },
    {
        code: 'BOP-2025',
        name: 'Phạm Vy',
        phone: '0903111222',
        dates: '12/06 → 14/06',
        status: 'Đã trả',
        total: 240000,
    },
];

const ORDER_COLS = '1.1fr 1.4fr 1.2fr 1fr 1.6fr 1fr';
const INV_COLS = '2fr 1.2fr 1fr 1.2fr 1fr';

export default function Admin() {
    const [orders, setOrders] = useState<Order[]>(INITIAL_ORDERS);
    const [stock, setStock] = useState<Record<number, number>>(() =>
        Object.fromEntries(PRODUCTS.map((p) => [p.id, p.stock])),
    );

    const setStatus = (i: number, s: Status) =>
        setOrders((prev) =>
            prev.map((o, idx) => (idx === i ? { ...o, status: s } : o)),
        );
    const bump = (id: number, delta: number) =>
        setStock((prev) => ({
            ...prev,
            [id]: Math.max(0, (prev[id] ?? 0) + delta),
        }));

    const stats = useMemo(() => {
        const totalStock = Object.values(stock).reduce((s, n) => s + n, 0);
        return [
            { value: String(orders.length), label: 'Đơn thuê' },
            {
                value: String(
                    orders.filter((o) => o.status === 'Chờ xác nhận').length,
                ),
                label: 'Chờ xác nhận',
            },
            {
                value: String(
                    orders.filter(
                        (o) =>
                            o.status === 'Đang thuê' ||
                            o.status === 'Đã xác nhận',
                    ).length,
                ),
                label: 'Đang hoạt động',
            },
            { value: String(totalStock), label: 'Bộ trong kho' },
        ];
    }, [orders, stock]);

    return (
        <>
            <Head title="Quản trị" />
            <main className="mx-auto max-w-[1160px] px-5 pb-12 pt-[30px]">
                <div className="mb-1.5 flex items-center gap-2.5">
                    <h1
                        className="font-extrabold tracking-tight text-ink"
                        style={{ fontSize: 'clamp(22px,3vw,30px)' }}
                    >
                        Quản trị
                    </h1>
                    <span
                        className="rounded-[6px] px-2 py-[3px] font-mono text-[11px] text-white"
                        style={{ background: '#C97B36' }}
                    >
                        DEMO
                    </span>
                </div>
                <p className="mb-6 text-moss">
                    Theo dõi đơn thuê và tồn kho thiết bị.
                </p>

                {/* stats */}
                <div
                    className="mb-[26px] grid gap-3"
                    style={{
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(140px, 1fr))',
                    }}
                >
                    {stats.map((a) => (
                        <div
                            key={a.label}
                            className="rounded-[14px] border border-cardBorder bg-card p-4"
                        >
                            <div className="font-mono text-[24px] font-bold text-grass">
                                {a.value}
                            </div>
                            <div className="mt-[3px] text-[13px] text-moss">
                                {a.label}
                            </div>
                        </div>
                    ))}
                </div>

                {/* recent orders */}
                <div className="mb-3 text-[17px] font-bold text-ink">
                    Đơn thuê gần đây
                </div>
                <div className="mb-[30px] overflow-hidden rounded-card border border-cardBorder bg-card">
                    <div className="overflow-x-auto">
                        <div style={{ minWidth: 680 }}>
                            <div
                                className="grid gap-3 px-[18px] py-[13px] text-[11px] font-bold uppercase tracking-[0.04em] text-[#8a967a]"
                                style={{
                                    gridTemplateColumns: ORDER_COLS,
                                    background: '#eef2e3',
                                }}
                            >
                                <span>Mã đơn</span>
                                <span>Khách</span>
                                <span>SĐT</span>
                                <span>Ngày thuê</span>
                                <span>Trạng thái</span>
                                <span className="text-right">Tổng</span>
                            </div>
                            {orders.map((o, i) => {
                                const col = STATUS_COLOR[o.status];
                                return (
                                    <div
                                        key={o.code}
                                        className="grid items-center gap-3 border-t px-[18px] py-[13px] text-[14px]"
                                        style={{
                                            gridTemplateColumns: ORDER_COLS,
                                            borderColor: '#eef0e6',
                                        }}
                                    >
                                        <span className="font-mono font-bold text-grass">
                                            {o.code}
                                        </span>
                                        <span className="font-semibold">
                                            {o.name}
                                        </span>
                                        <span className="font-mono text-[13px] text-moss">
                                            {o.phone}
                                        </span>
                                        <span className="font-mono text-[12px] text-moss">
                                            {o.dates}
                                        </span>
                                        <select
                                            value={o.status}
                                            onChange={(e) =>
                                                setStatus(
                                                    i,
                                                    e.target.value as Status,
                                                )
                                            }
                                            aria-label={`Trạng thái đơn ${o.code}`}
                                            className="h-8 w-max cursor-pointer rounded-pill border-none px-3 text-[12px] font-bold"
                                            style={{
                                                background: col.bg,
                                                color: col.c,
                                            }}
                                        >
                                            {STATUS.map((s) => (
                                                <option key={s} value={s}>
                                                    {s}
                                                </option>
                                            ))}
                                        </select>
                                        <span className="text-right font-mono font-bold">
                                            {money(o.total)}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* inventory */}
                <div className="mb-3 text-[17px] font-bold text-ink">
                    Kho thiết bị
                </div>
                <div className="overflow-hidden rounded-card border border-cardBorder bg-card">
                    <div className="overflow-x-auto">
                        <div style={{ minWidth: 620 }}>
                            <div
                                className="grid gap-3 px-[18px] py-[13px] text-[11px] font-bold uppercase tracking-[0.04em] text-[#8a967a]"
                                style={{
                                    gridTemplateColumns: INV_COLS,
                                    background: '#eef2e3',
                                }}
                            >
                                <span>Thiết bị</span>
                                <span>Danh mục</span>
                                <span>Giá/ngày</span>
                                <span>Tồn kho</span>
                                <span className="text-right">Trạng thái</span>
                            </div>
                            {PRODUCTS.map((p) => {
                                const s = stock[p.id] ?? 0;
                                const status =
                                    s === 0
                                        ? {
                                              t: 'Hết hàng',
                                              c: '#b3493a',
                                              bg: '#f6ddd6',
                                          }
                                        : s <= 2
                                          ? {
                                                t: 'Sắp hết',
                                                c: '#C97B36',
                                                bg: '#f7e7da',
                                            }
                                          : {
                                                t: 'Còn hàng',
                                                c: '#3a5a1f',
                                                bg: '#dcebc4',
                                            };
                                return (
                                    <div
                                        key={p.id}
                                        className="grid items-center gap-3 border-t px-[18px] py-3 text-[14px]"
                                        style={{
                                            gridTemplateColumns: INV_COLS,
                                            borderColor: '#eef0e6',
                                        }}
                                    >
                                        <span className="font-semibold">
                                            {p.name}
                                        </span>
                                        <span className="text-[13px] text-moss">
                                            {catLabel(p.cat)}
                                        </span>
                                        <span className="font-mono font-semibold">
                                            {money(p.price)}
                                        </span>
                                        <div className="flex w-max items-center overflow-hidden rounded-[9px] border border-cardBorder">
                                            <button
                                                onClick={() => bump(p.id, -1)}
                                                className="h-[30px] w-7 bg-[#f1f4ea] text-grass"
                                            >
                                                −
                                            </button>
                                            <span className="w-[34px] text-center font-mono text-[13px] font-bold">
                                                {s}
                                            </span>
                                            <button
                                                onClick={() => bump(p.id, 1)}
                                                className="h-[30px] w-7 bg-[#f1f4ea] text-grass"
                                            >
                                                +
                                            </button>
                                        </div>
                                        <span
                                            className="justify-self-end rounded-pill px-3 py-1 text-[12px] font-bold"
                                            style={{
                                                background: status.bg,
                                                color: status.c,
                                            }}
                                        >
                                            {status.t}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </main>
        </>
    );
}

Admin.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
