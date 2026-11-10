import SelectInput from '@/Components/admin/SelectInput';
import AdminLayout from '@/Layouts/AdminLayout';
import { lineRent, type CartLine } from '@/lib/cart';
import { dayCount, money, todayISO } from '@/lib/format';
import type { Session } from '@/lib/session';
import type { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';

/**
 * Admin tự tạo đơn hộ khách (bopcamping-yv2w). Một khoảng ngày cho cả đơn.
 * Tiền hiển thị ở đây chỉ là ƯỚC TÍNH (mirror RentalPricingService qua lib/cart) —
 * server tính lại và kiểm tồn kho khi lưu; số "còn" chỉ để admin nhìn trước.
 */

type CatalogEntry = {
    id: number;
    name: string;
    category?: string | null;
    price_per_day: number;
    deposit: number;
    early_return_pct: number;
    location_ids: number[];
    items?: { name: string; qty: number }[];
};

type Kind = 'product' | 'combo';
type Line = { kind: Kind; id: number; qty: number };
type Option = { value: string; label: string };

type Props = {
    products: CatalogEntry[];
    combos: CatalogEntry[];
    service_locations: { id: number; name: string }[];
    delivery_methods: Option[];
};

type FormData = {
    phone: string;
    name: string;
    email: string;
    address: string;
    note: string;
    delivery_method: string;
    service_location_id: string;
    start: string;
    end: string;
    session: Session | '';
    lines: Line[];
    discount: string;
    discount_note: string;
};

type Availability = {
    products: Record<string, number>;
    combos: Record<string, number>;
};

const SESSIONS: { value: Session | ''; label: string }[] = [
    { value: '', label: 'Không chọn' },
    { value: 'morning', label: 'Buổi sáng' },
    { value: 'afternoon', label: 'Buổi chiều' },
    { value: 'full', label: 'Cả ngày' },
];

export default function AdminOrderCreate({
    products,
    combos,
    service_locations,
    delivery_methods,
}: Props) {
    const { durationTiers } = usePage<PageProps>().props;
    const today = todayISO();

    const form = useForm<FormData>({
        phone: '',
        name: '',
        email: '',
        address: '',
        note: '',
        delivery_method: delivery_methods[0]?.value ?? 'self_pickup',
        service_location_id:
            service_locations.length === 1
                ? String(service_locations[0].id)
                : '',
        start: today,
        end: today,
        session: '',
        lines: [],
        discount: '',
        discount_note: '',
    });
    const { data, setData } = form;
    // Lỗi server có cả key không nằm trong form (items/combos/... sau transform).
    const errors = form.errors as Partial<Record<string, string>>;

    const singleDay = data.start !== '' && data.start === data.end;
    const days =
        data.start && data.end && data.end >= data.start
            ? dayCount(data.start, data.end)
            : 0;
    const locationId = data.service_location_id
        ? Number(data.service_location_id)
        : null;

    const catalog = (kind: Kind) => (kind === 'combo' ? combos : products);
    const find = (l: Line) => catalog(l.kind).find((e) => e.id === l.id);

    // ── Tồn kho theo khoảng ngày + cơ sở (chỉ để hiển thị) ──────────────
    const [stock, setStock] = useState<Availability | null>(null);
    useEffect(() => {
        if (!days) {
            setStock(null);
            return;
        }
        let cancelled = false;
        axios
            .get<Availability>(route('admin.orders.create.availability'), {
                params: {
                    start: data.start,
                    end: data.end,
                    location_id: locationId ?? undefined,
                },
            })
            .then((res) => !cancelled && setStock(res.data))
            .catch(() => !cancelled && setStock(null));
        return () => {
            cancelled = true;
        };
    }, [data.start, data.end, locationId, days]);

    const available = (kind: Kind, id: number): number | null => {
        if (!stock) return null;
        const map = kind === 'combo' ? stock.combos : stock.products;
        return map[String(id)] ?? 0;
    };

    // ── Khách cũ theo SĐT: điền sẵn các ô còn trống ─────────────────────
    const [customerHint, setCustomerHint] = useState<string | null>(null);
    const lookupCustomer = () => {
        if (!/^0[0-9]{8,10}$/.test(data.phone)) {
            setCustomerHint(null);
            return;
        }
        axios
            .get(route('admin.orders.create.customer'), {
                params: { phone: data.phone },
            })
            .then(({ data: c }) => {
                if (!c.found) {
                    setCustomerHint('Khách mới.');
                    return;
                }
                form.setData((d) => ({
                    ...d,
                    name: d.name || c.name || '',
                    email: d.email || c.email || '',
                    address: d.address || c.address || '',
                }));
                setCustomerHint(
                    c.has_account
                        ? 'Khách đã có tài khoản. Đơn sẽ hiện trong "Đơn của tôi" của họ.'
                        : 'Khách cũ (đã từng đặt). Đã điền sẵn thông tin.',
                );
            })
            .catch(() => setCustomerHint(null));
    };

    // ── Dòng thiết bị ────────────────────────────────────────────────────
    const [query, setQuery] = useState('');
    const results = useMemo(() => {
        const q = query.trim().toLowerCase();
        const servesHere = (e: CatalogEntry) =>
            locationId === null ||
            e.location_ids.length === 0 ||
            e.location_ids.includes(locationId);
        const all: { kind: Kind; entry: CatalogEntry }[] = [
            ...combos.map((entry) => ({ kind: 'combo' as Kind, entry })),
            ...products.map((entry) => ({ kind: 'product' as Kind, entry })),
        ];
        return all
            .filter(({ entry }) => servesHere(entry))
            .filter(
                ({ entry }) =>
                    q === '' ||
                    entry.name.toLowerCase().includes(q) ||
                    (entry.category ?? '').toLowerCase().includes(q),
            )
            .slice(0, 12);
    }, [query, products, combos, locationId]);

    const addLine = (kind: Kind, id: number) => {
        const idx = data.lines.findIndex((l) => l.kind === kind && l.id === id);
        if (idx >= 0) {
            setQty(idx, data.lines[idx].qty + 1);
            return;
        }
        setData('lines', [...data.lines, { kind, id, qty: 1 }]);
    };
    const setQty = (idx: number, qty: number) =>
        setData(
            'lines',
            data.lines.map((l, i) =>
                i === idx ? { ...l, qty: Math.max(1, Math.min(99, qty)) } : l,
            ),
        );
    const removeLine = (idx: number) =>
        setData(
            'lines',
            data.lines.filter((_, i) => i !== idx),
        );

    // ── Tiền ước tính — cùng công thức với giỏ khách (lib/cart → lib/pricing) ─
    const session: Session | null =
        singleDay && data.session ? data.session : null;
    const priced = data.lines.map((l) => {
        const e = find(l);
        if (!e || !days) return { rent: 0, deposit: 0 };
        const cartLine: CartLine = {
            id: e.id,
            name: e.name,
            cat: '',
            grad: '',
            price: e.price_per_day,
            deposit: e.deposit,
            qty: l.qty,
            start: data.start,
            end: data.end,
            kind: l.kind,
            early_return_pct: e.early_return_pct,
            session,
        };
        return {
            rent: lineRent(cartLine, durationTiers),
            deposit: e.deposit * l.qty,
        };
    });
    const rent = priced.reduce((s, p) => s + p.rent, 0);
    const deposit = priced.reduce((s, p) => s + p.deposit, 0);
    const discount = Math.max(0, Number(data.discount) || 0);
    const pay = Math.max(0, rent - discount) + deposit;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            name: d.name,
            phone: d.phone,
            email: d.email || null,
            address: d.address || null,
            note: d.note || null,
            delivery_method: d.delivery_method,
            service_location_id: d.service_location_id
                ? Number(d.service_location_id)
                : null,
            start: d.start,
            end: d.end,
            session: singleDay && d.session ? d.session : null,
            items: d.lines
                .filter((l) => l.kind === 'product')
                .map((l) => ({ product_id: l.id, quantity: l.qty })),
            combos: d.lines
                .filter((l) => l.kind === 'combo')
                .map((l) => ({ combo_id: l.id, quantity: l.qty })),
            discount: d.discount ? Number(d.discount) : null,
            discount_note: d.discount_note || null,
        }));
        form.post(route('admin.orders.store'), { preserveScroll: true });
    };

    const isPast = data.start !== '' && data.start < today;

    return (
        <>
            <Head title="Quản trị · Tạo đơn" />
            <form onSubmit={submit} className="p-6">
                <div className="mb-4 text-[13px]">
                    <Link
                        href={route('admin.orders')}
                        className="font-semibold text-grass hover:underline"
                    >
                        ← Danh sách đơn
                    </Link>
                </div>
                <h1 className="text-[22px] font-extrabold text-pine">
                    Tạo đơn mới
                </h1>
                <p className="mb-5 mt-0.5 text-[13px] text-moss">
                    Đơn chốt qua Zalo, điện thoại hoặc tại shop. Đơn tạo xong ở
                    trạng thái <b>Đã xác nhận</b>; khách có email sẽ nhận mail
                    xác nhận.
                </p>

                {errors.items && (
                    <div
                        role="alert"
                        className="mb-4 rounded-[10px] border border-[#e8b4a8] bg-[#fbeee9] px-4 py-2.5 text-[13.5px] font-semibold text-[#b3493a]"
                    >
                        {errors.items}
                    </div>
                )}

                <div className="grid gap-5 lg:grid-cols-[1fr_340px]">
                    <div className="flex min-w-0 flex-col gap-5">
                        {/* Khách hàng */}
                        <Section title="Khách hàng">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field
                                    label="Số điện thoại *"
                                    error={errors.phone}
                                >
                                    <input
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData(
                                                'phone',
                                                e.target.value.trim(),
                                            )
                                        }
                                        onBlur={lookupCustomer}
                                        inputMode="tel"
                                        placeholder="09..."
                                        className={inputCls}
                                    />
                                    {customerHint && (
                                        <span className="text-[12px] text-moss">
                                            {customerHint}
                                        </span>
                                    )}
                                </Field>
                                <Field label="Họ tên *" error={errors.name}>
                                    <input
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        className={inputCls}
                                    />
                                </Field>
                                <Field
                                    label="Email (để gửi mail xác nhận)"
                                    error={errors.email}
                                >
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        className={inputCls}
                                    />
                                </Field>
                                <Field label="Hình thức giao">
                                    <SelectInput
                                        value={data.delivery_method}
                                        onChange={(v) =>
                                            setData('delivery_method', v)
                                        }
                                        options={delivery_methods}
                                        ariaLabel="Hình thức giao"
                                    />
                                </Field>
                                <Field
                                    label="Địa chỉ"
                                    error={errors.address}
                                    wide
                                >
                                    <input
                                        value={data.address}
                                        onChange={(e) =>
                                            setData('address', e.target.value)
                                        }
                                        className={inputCls}
                                    />
                                </Field>
                                <Field label="Ghi chú" error={errors.note} wide>
                                    <textarea
                                        value={data.note}
                                        onChange={(e) =>
                                            setData('note', e.target.value)
                                        }
                                        rows={2}
                                        className={`${inputCls} h-auto py-2`}
                                    />
                                </Field>
                            </div>
                        </Section>

                        {/* Lịch thuê */}
                        <Section title="Lịch thuê & cơ sở">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Field label="Ngày nhận *" error={errors.start}>
                                    <input
                                        type="date"
                                        value={data.start}
                                        onChange={(e) => {
                                            const v = e.target.value;
                                            setData((d) => ({
                                                ...d,
                                                start: v,
                                                end: d.end < v ? v : d.end,
                                            }));
                                        }}
                                        className={inputCls}
                                    />
                                </Field>
                                <Field label="Ngày trả *" error={errors.end}>
                                    <input
                                        type="date"
                                        value={data.end}
                                        min={data.start}
                                        onChange={(e) =>
                                            setData('end', e.target.value)
                                        }
                                        className={inputCls}
                                    />
                                </Field>
                                <Field
                                    label="Cơ sở"
                                    error={errors.service_location_id}
                                >
                                    <SelectInput
                                        value={data.service_location_id}
                                        onChange={(v) =>
                                            setData('service_location_id', v)
                                        }
                                        placeholder="Tự chọn cơ sở còn đủ hàng"
                                        options={service_locations.map((l) => ({
                                            value: String(l.id),
                                            label: l.name,
                                        }))}
                                        ariaLabel="Cơ sở"
                                    />
                                </Field>
                            </div>
                            <p className="mt-2 text-[12.5px] text-moss">
                                {days > 0 ? `${days} ngày thuê.` : 'Chọn ngày.'}
                                {isPast &&
                                    ' Ngày nhận đã qua: đơn được ghi lùi.'}
                            </p>
                            {singleDay && (
                                <div className="mt-3">
                                    <span className="text-[12px] font-semibold text-moss">
                                        Buổi (thuê trong ngày)
                                    </span>
                                    <div className="mt-1 flex flex-wrap gap-2">
                                        {SESSIONS.map((s) => (
                                            <button
                                                key={s.value}
                                                type="button"
                                                onClick={() =>
                                                    setData('session', s.value)
                                                }
                                                aria-pressed={
                                                    data.session === s.value
                                                }
                                                className={`h-9 rounded-pill border px-3.5 text-[13px] font-semibold transition ${data.session === s.value ? 'border-grass bg-grass text-white' : 'border-cardBorder bg-white text-pine hover:border-grass/60'}`}
                                            >
                                                {s.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </Section>

                        {/* Thiết bị */}
                        <Section title="Thiết bị">
                            <input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Tìm sản phẩm hoặc combo…"
                                aria-label="Tìm thiết bị"
                                className={`${inputCls} w-full`}
                            />
                            <ul className="mt-2 max-h-[260px] divide-y divide-[#eef2e3] overflow-y-auto rounded-[10px] border border-cardBorder bg-white">
                                {results.map(({ kind, entry }) => {
                                    const left = available(kind, entry.id);
                                    return (
                                        <li key={`${kind}-${entry.id}`}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    addLine(kind, entry.id)
                                                }
                                                className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-[#f6f8f0]"
                                            >
                                                <span className="min-w-0">
                                                    <span className="block truncate text-[13.5px] font-semibold text-ink">
                                                        {kind === 'combo' && (
                                                            <span className="mr-1.5 rounded-pill bg-[#e7eed5] px-1.5 py-0.5 text-[10px] font-bold text-[#3a5a1f]">
                                                                COMBO
                                                            </span>
                                                        )}
                                                        {entry.name}
                                                    </span>
                                                    <span className="text-[12px] text-moss">
                                                        {money(
                                                            entry.price_per_day,
                                                        )}
                                                        /ngày · cọc{' '}
                                                        {money(entry.deposit)}
                                                    </span>
                                                </span>
                                                <span
                                                    className={`shrink-0 font-mono text-[12px] font-bold ${left === 0 ? 'text-[#b3493a]' : 'text-pine'}`}
                                                >
                                                    {left === null
                                                        ? '+ Thêm'
                                                        : `còn ${left}`}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                                {results.length === 0 && (
                                    <li className="px-3 py-3 text-[13px] text-moss">
                                        Không tìm thấy thiết bị phục vụ tại cơ
                                        sở này.
                                    </li>
                                )}
                            </ul>

                            {data.lines.length > 0 && (
                                <table className="mt-4 w-full text-[13px]">
                                    <thead>
                                        <tr className="text-left text-[11.5px] uppercase tracking-[0.04em] text-moss">
                                            <th className="pb-1.5 font-bold">
                                                Đã chọn
                                            </th>
                                            <th className="pb-1.5 font-bold">
                                                SL
                                            </th>
                                            <th className="pb-1.5 text-right font-bold">
                                                Tiền thuê
                                            </th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.lines.map((l, i) => {
                                            const e = find(l);
                                            const left = available(
                                                l.kind,
                                                l.id,
                                            );
                                            const short =
                                                left !== null && l.qty > left;
                                            return (
                                                <tr
                                                    key={`${l.kind}-${l.id}`}
                                                    className="border-t border-[#eef2e3] align-top"
                                                >
                                                    <td className="py-2 pr-2">
                                                        <div className="font-semibold text-ink">
                                                            {e?.name}
                                                        </div>
                                                        {e?.items && (
                                                            <div className="text-[11.5px] text-moss">
                                                                {e.items
                                                                    .map(
                                                                        (x) =>
                                                                            `${x.qty}× ${x.name}`,
                                                                    )
                                                                    .join(', ')}
                                                            </div>
                                                        )}
                                                        {short && (
                                                            <div className="text-[12px] font-semibold text-[#b3493a]">
                                                                Chỉ còn {left} —
                                                                sẽ không lưu
                                                                được.
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="py-2">
                                                        <input
                                                            type="number"
                                                            min={1}
                                                            max={99}
                                                            value={l.qty}
                                                            onChange={(ev) =>
                                                                setQty(
                                                                    i,
                                                                    Number(
                                                                        ev
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                )
                                                            }
                                                            aria-label={`Số lượng ${e?.name ?? ''}`}
                                                            className="h-9 w-16 rounded-[8px] border border-cardBorder px-2 text-center"
                                                        />
                                                    </td>
                                                    <td className="py-2 text-right font-mono font-semibold">
                                                        {money(priced[i].rent)}
                                                    </td>
                                                    <td className="py-2 pl-2 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeLine(i)
                                                            }
                                                            aria-label={`Bỏ ${e?.name ?? ''}`}
                                                            className="text-[13px] font-semibold text-[#b3493a] hover:underline"
                                                        >
                                                            Bỏ
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            )}
                        </Section>
                    </div>

                    {/* Tóm tắt */}
                    <aside className="h-fit rounded-card border border-cardBorder bg-card p-4 lg:sticky lg:top-6">
                        <h2 className="mb-3 text-[15px] font-extrabold text-pine">
                            Tóm tắt
                        </h2>
                        <Row label="Tiền thuê (ước tính)" value={money(rent)} />
                        <Row label="Tiền cọc" value={money(deposit)} />

                        <div className="mt-3 flex flex-col gap-2 border-t border-cardBorder pt-3">
                            <Field label="Giảm giá (đ)" error={errors.discount}>
                                <input
                                    type="number"
                                    min={0}
                                    step={1000}
                                    value={data.discount}
                                    onChange={(e) =>
                                        setData('discount', e.target.value)
                                    }
                                    placeholder="0"
                                    className={inputCls}
                                />
                            </Field>
                            {discount > 0 && (
                                <Field
                                    label="Lý do giảm *"
                                    error={errors.discount_note}
                                >
                                    <input
                                        value={data.discount_note}
                                        onChange={(e) =>
                                            setData(
                                                'discount_note',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="vd: khách quen"
                                        className={inputCls}
                                    />
                                </Field>
                            )}
                        </div>

                        <div className="mt-3 flex items-baseline justify-between border-t border-cardBorder pt-3">
                            <span className="text-[13px] font-bold text-ink">
                                Khách trả khi nhận
                            </span>
                            <span className="font-mono text-[18px] font-extrabold text-pine">
                                {money(pay)}
                            </span>
                        </div>
                        <p className="mt-1 text-[11.5px] text-moss">
                            Gồm cọc. Tiền thuê chính xác do hệ thống tính khi
                            lưu.
                        </p>

                        <button
                            type="submit"
                            disabled={
                                form.processing || data.lines.length === 0
                            }
                            className="mt-4 h-11 w-full rounded-control bg-grass text-[14px] font-bold text-white disabled:opacity-60"
                        >
                            {form.processing ? 'Đang tạo…' : 'Tạo đơn'}
                        </button>
                    </aside>
                </div>
            </form>
        </>
    );
}

const inputCls =
    'h-11 rounded-[10px] border border-cardBorder bg-white px-3 text-[14px] text-ink outline-none focus:border-grass';

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-card border border-cardBorder bg-card p-4">
            <h2 className="mb-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                {title}
            </h2>
            {children}
        </section>
    );
}

function Field({
    label,
    error,
    wide,
    children,
}: {
    label: string;
    error?: string;
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <label className={`flex flex-col gap-1 ${wide ? 'sm:col-span-2' : ''}`}>
            <span className="text-[12px] font-semibold text-moss">{label}</span>
            {children}
            {error && <span className="text-[12px] text-red-500">{error}</span>}
        </label>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between py-1 text-[13px]">
            <span className="text-moss">{label}</span>
            <span className="font-mono font-semibold text-ink">{value}</span>
        </div>
    );
}

AdminOrderCreate.layout = (page: ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);
