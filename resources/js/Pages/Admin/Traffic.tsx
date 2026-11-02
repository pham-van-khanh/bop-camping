import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

type Period = 'today' | '7d' | '30d';
type Method = 'zalo' | 'phone' | 'messenger';

type Props = PageProps<{
    period: Period;
    totals: {
        visitors: number;
        page_views: number;
        product_views: number;
        contacts: number;
        contact_visitors: number;
    };
    contact_by_method: { method: Method; count: number }[];
    chart: { label: string; views: number; contacts: number }[];
    top_products: { name: string; views: number; visitors: number }[];
    top_pages: { path: string; views: number }[];
    sources: { source: string; visitors: number }[];
    recent_contacts: {
        at: string;
        method: Method;
        page: string;
        source: string | null;
        who: string | null;
    }[];
}>;

const PERIODS: { key: Period; label: string }[] = [
    { key: 'today', label: 'Hôm nay' },
    { key: '7d', label: '7 ngày' },
    { key: '30d', label: '30 ngày' },
];

const METHOD_LABEL: Record<Method, string> = {
    zalo: 'Zalo',
    phone: 'Gọi điện',
    messenger: 'Messenger',
};

function Card({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-[14px] border border-[#e8e0d0] bg-white p-4">
            <h2 className="mb-3 text-[13px] font-bold uppercase tracking-[0.05em] text-[#7a6f5c]">
                {title}
            </h2>
            {children}
        </section>
    );
}

function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: number;
    hint?: string;
}) {
    return (
        <div className="rounded-[14px] border border-[#e8e0d0] bg-white p-4">
            <div className="text-[12px] text-[#7a6f5c]">{label}</div>
            <div className="text-[26px] font-bold text-[#3d3425]">
                {value.toLocaleString('vi-VN')}
            </div>
            {hint && <div className="text-[11px] text-[#a09580]">{hint}</div>}
        </div>
    );
}

function Empty() {
    return <p className="text-[13px] text-[#a09580]">Chưa có dữ liệu.</p>;
}

export default function AdminTraffic() {
    const p = usePage<Props>().props;
    const maxViews = Math.max(1, ...p.chart.map((d) => d.views));

    return (
        <>
            <Head title="Quản trị · Truy cập" />
            <div className="space-y-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-[20px] font-bold text-[#3d3425]">
                        Thống kê truy cập
                    </h1>
                    <div className="flex gap-1.5">
                        {PERIODS.map((o) => (
                            <button
                                key={o.key}
                                onClick={() =>
                                    router.get(
                                        route('admin.traffic'),
                                        { period: o.key },
                                        { preserveScroll: true, replace: true },
                                    )
                                }
                                className={`rounded-full px-3.5 py-1.5 text-[13px] font-semibold ${
                                    p.period === o.key
                                        ? 'bg-[#3d3425] text-white'
                                        : 'border border-[#e8e0d0] bg-white text-[#7a6f5c]'
                                }`}
                            >
                                {o.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Stat
                        label="Khách truy cập"
                        value={p.totals.visitors}
                        hint="đếm theo trình duyệt"
                    />
                    <Stat label="Lượt xem trang" value={p.totals.page_views} />
                    <Stat
                        label="Lượt xem sản phẩm"
                        value={p.totals.product_views}
                    />
                    <Stat
                        label="Lần bấm liên hệ"
                        value={p.totals.contacts}
                        hint={`${p.totals.contact_visitors} khách khác nhau`}
                    />
                </div>

                <Card title="Lượt xem theo ngày">
                    <div className="flex h-32 items-end gap-1">
                        {p.chart.map((d) => (
                            <div
                                key={d.label}
                                className="flex flex-1 flex-col items-center justify-end gap-1"
                                title={`${d.label}: ${d.views} lượt xem, ${d.contacts} lần bấm liên hệ`}
                            >
                                <span className="text-[10px] text-[#a09580]">
                                    {d.contacts > 0 ? `💬${d.contacts}` : ''}
                                </span>
                                <div
                                    className="w-full rounded-t bg-[#b08d57]"
                                    style={{
                                        height: `${(d.views / maxViews) * 80}%`,
                                        minHeight: 2,
                                    }}
                                />
                                <span className="text-[10px] text-[#a09580]">
                                    {d.label}
                                </span>
                            </div>
                        ))}
                    </div>
                </Card>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card title="Bấm liên hệ theo kênh">
                        <ul className="space-y-1.5 text-[14px]">
                            {p.contact_by_method.map((m) => (
                                <li
                                    key={m.method}
                                    className="flex justify-between"
                                >
                                    <span>{METHOD_LABEL[m.method]}</span>
                                    <b>{m.count}</b>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card title="Nguồn truy cập (số khách)">
                        {p.sources.length === 0 ? (
                            <Empty />
                        ) : (
                            <ul className="space-y-1.5 text-[14px]">
                                {p.sources.map((s) => (
                                    <li
                                        key={s.source}
                                        className="flex justify-between"
                                    >
                                        <span>{s.source}</span>
                                        <b>{s.visitors}</b>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                    <Card title="Sản phẩm xem nhiều">
                        {p.top_products.length === 0 ? (
                            <Empty />
                        ) : (
                            <ul className="space-y-1.5 text-[14px]">
                                {p.top_products.map((r) => (
                                    <li
                                        key={r.name}
                                        className="flex justify-between gap-3"
                                    >
                                        <span className="truncate">
                                            {r.name}
                                        </span>
                                        <span className="flex-none">
                                            <b>{r.views}</b> lượt · {r.visitors}{' '}
                                            khách
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                    <Card title="Trang xem nhiều">
                        {p.top_pages.length === 0 ? (
                            <Empty />
                        ) : (
                            <ul className="space-y-1.5 text-[14px]">
                                {p.top_pages.map((r) => (
                                    <li
                                        key={r.path}
                                        className="flex justify-between gap-3"
                                    >
                                        <span className="truncate">
                                            {r.path}
                                        </span>
                                        <b>{r.views}</b>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                <Card title="Các lần bấm liên hệ gần đây">
                    {p.recent_contacts.length === 0 ? (
                        <Empty />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-[13px]">
                                <thead className="text-[#7a6f5c]">
                                    <tr>
                                        <th className="py-1 pr-3">Lúc</th>
                                        <th className="pr-3">Kênh</th>
                                        <th className="pr-3">Đang xem</th>
                                        <th className="pr-3">Nguồn</th>
                                        <th>Khách</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {p.recent_contacts.map((c, i) => (
                                        <tr
                                            key={i}
                                            className="border-t border-[#f0eadc]"
                                        >
                                            <td className="whitespace-nowrap py-1.5 pr-3">
                                                {c.at}
                                            </td>
                                            <td className="pr-3">
                                                {METHOD_LABEL[c.method]}
                                            </td>
                                            <td className="pr-3">{c.page}</td>
                                            <td className="pr-3">
                                                {c.source ?? '—'}
                                            </td>
                                            <td>{c.who ?? 'Ẩn danh'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

AdminTraffic.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
