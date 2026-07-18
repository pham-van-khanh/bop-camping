import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import SelectInput from '@/Components/admin/SelectInput';
import { money } from '@/lib/format';
import type { PageProps } from '@/types';

type Settings = {
    referral_enabled: boolean;
    referee_discount_type: 'fixed' | 'percent';
    referee_discount_value: number | string;
    referrer_reward_type: 'fixed' | 'percent';
    referrer_reward_value: number | string;
    referrer_reward_per_referral: boolean;
    max_discount_percent_per_order: number | string;
    max_vouchers_stack_per_order: number;
    voucher_validity_days: number;
    min_order_amount: number | string;
    discount_applies_to: 'rental_fee' | 'total';
    conversion_trigger_status: string;
    max_referrals_per_user_per_month: number;
    reward_clawback_enabled: boolean;
    email_bonus_enabled: boolean;
    email_bonus_discount_type: 'fixed' | 'percent';
    email_bonus_discount_value: number | string;
};

type DurationTierRow = { min_days: number | string; discount_percent: number | string; is_active: boolean };

type Props = PageProps<{
    settings: Settings;
    duration_tiers: DurationTierRow[];
    stats: {
        referrals_this_month: number;
        vouchers_active: number;
        vouchers_used: number;
        voucher_value_issued: number;
    };
}>;

const STATUS_OPTIONS = [
    { value: 'pending', label: 'Chờ xác nhận' },
    { value: 'confirmed', label: 'Đã xác nhận' },
    { value: 'renting', label: 'Đang thuê' },
    { value: 'returned', label: 'Đã trả (mặc định)' },
];

export default function AdminPromotion() {
    const { settings, duration_tiers, stats, flash } = usePage<Props>().props;
    const [toast, setToast] = useState('');
    const { data, setData, put, processing, errors } = useForm<Settings>({ ...settings });

    useEffect(() => {
        if (flash.success) {
            setToast(flash.success);
            const t = setTimeout(() => setToast(''), 2500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.promotion.update'), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Admin · Khuyến mãi" />
            <div className="mx-auto max-w-[860px]">
                <h1 className="mb-1 text-[22px] font-extrabold text-ink">Cấu hình khuyến mãi</h1>
                <p className="mb-5 text-[14px] text-moss">Mọi % và số tiền voucher chỉnh ở đây — không hardcode.</p>

                {toast && (
                    <div className="mb-4 rounded-[10px] border border-grass/40 bg-[#eef2e3] px-4 py-2.5 text-[14px] font-semibold text-pine">{toast}</div>
                )}

                {Object.keys(errors).length > 0 && (
                    <div className="mb-4 rounded-[10px] border border-red-300 bg-red-50 px-4 py-3 text-[14px] text-red-700">
                        <div className="mb-1 font-bold">Chưa lưu được — vui lòng kiểm tra:</div>
                        <ul className="list-disc pl-5">
                            {Object.values(errors).map((e, i) => <li key={i}>{e}</li>)}
                        </ul>
                    </div>
                )}

                {/* Widget thống kê */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Metric label="Giới thiệu tháng này" value={String(stats.referrals_this_month)} />
                    <Metric label="Voucher đang dùng" value={String(stats.vouchers_active)} />
                    <Metric label="Voucher đã dùng" value={String(stats.vouchers_used)} />
                    <Metric label="Tổng giá trị phát" value={money(stats.voucher_value_issued)} />
                </div>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <Section title="Chung">
                        <Toggle label="Bật chương trình giới thiệu" checked={data.referral_enabled} onChange={(v) => setData('referral_enabled', v)} />
                        <Num label="Đơn tối thiểu để áp khuyến mãi (đ)" value={data.min_order_amount} onChange={(v) => setData('min_order_amount', v)} error={errors.min_order_amount} />
                        <Select label="Khuyến mãi áp lên" value={data.discount_applies_to} onChange={(v) => setData('discount_applies_to', v as Settings['discount_applies_to'])}
                            options={[{ value: 'rental_fee', label: 'Phí thuê (không gồm cọc)' }, { value: 'total', label: 'Tổng đơn' }]} />
                        <Select label="Trạng thái tính giới thiệu thành công" value={data.conversion_trigger_status} onChange={(v) => setData('conversion_trigger_status', v)}
                            options={STATUS_OPTIONS} />
                    </Section>

                    <Section title="Van an toàn margin">
                        <Num label="Trần giảm tối đa / đơn (%)" value={data.max_discount_percent_per_order} onChange={(v) => setData('max_discount_percent_per_order', v)} error={errors.max_discount_percent_per_order} />
                        <Num label="Số voucher tối đa / đơn" value={data.max_vouchers_stack_per_order} onChange={(v) => setData('max_vouchers_stack_per_order', Number(v))} error={errors.max_vouchers_stack_per_order} />
                        <Num label="Hạn dùng voucher (ngày)" value={data.voucher_validity_days} onChange={(v) => setData('voucher_validity_days', Number(v))} error={errors.voucher_validity_days} />
                    </Section>

                    <Section title="Ưu đãi người được giới thiệu (referee)">
                        <Select label="Kiểu giảm" value={data.referee_discount_type} onChange={(v) => setData('referee_discount_type', v as Settings['referee_discount_type'])}
                            options={[{ value: 'percent', label: 'Phần trăm (%)' }, { value: 'fixed', label: 'Số tiền (đ)' }]} />
                        <Num label="Giá trị giảm đơn đầu" value={data.referee_discount_value} onChange={(v) => setData('referee_discount_value', v)} error={errors.referee_discount_value} />
                    </Section>

                    <Section title="Ưu đãi thêm email (email không bắt buộc khi đăng ký)">
                        <Toggle label="Bật ưu đãi khi khách thêm email" checked={data.email_bonus_enabled} onChange={(v) => setData('email_bonus_enabled', v)} />
                        <Select label="Kiểu giảm" value={data.email_bonus_discount_type} onChange={(v) => setData('email_bonus_discount_type', v as Settings['email_bonus_discount_type'])}
                            options={[{ value: 'percent', label: 'Phần trăm (%)' }, { value: 'fixed', label: 'Số tiền (đ)' }]} />
                        <Num label="Giá trị giảm đơn đầu" value={data.email_bonus_discount_value} onChange={(v) => setData('email_bonus_discount_value', v)} error={errors.email_bonus_discount_value} />
                    </Section>

                    <Section title="Thưởng người giới thiệu (referrer)">
                        <Select label="Kiểu thưởng" value={data.referrer_reward_type} onChange={(v) => setData('referrer_reward_type', v as Settings['referrer_reward_type'])}
                            options={[{ value: 'fixed', label: 'Số tiền (đ)' }, { value: 'percent', label: 'Phần trăm (%)' }]} />
                        <Num label="Giá trị voucher thưởng" value={data.referrer_reward_value} onChange={(v) => setData('referrer_reward_value', v)} error={errors.referrer_reward_value} />
                        <Toggle label="Thưởng mỗi lượt giới thiệu (tắt = chỉ thưởng 1 lần)" checked={data.referrer_reward_per_referral} onChange={(v) => setData('referrer_reward_per_referral', v)} />
                        <Num label="Giới hạn lượt thưởng / tháng (0 = không giới hạn)" value={data.max_referrals_per_user_per_month} onChange={(v) => setData('max_referrals_per_user_per_month', Number(v))} error={errors.max_referrals_per_user_per_month} />
                        <Toggle label="Thu hồi voucher khi đơn bị huỷ sau khi đã thưởng" checked={data.reward_clawback_enabled} onChange={(v) => setData('reward_clawback_enabled', v)} />
                    </Section>

                    <div>
                        <button type="submit" disabled={processing}
                            className="h-11 rounded-control bg-grass px-6 text-[15px] font-bold text-white transition hover:bg-pine disabled:opacity-60">
                            {processing ? 'Đang lưu…' : 'Lưu cấu hình'}
                        </button>
                    </div>
                </form>

                <DurationTiers initial={duration_tiers} />
            </div>
        </>
    );
}

// Bậc giảm giá thuê dài ngày (bopcamping-e36e) — form riêng, sync toàn bảng khi lưu.
function DurationTiers({ initial }: { initial: DurationTierRow[] }) {
    const [rows, setRows] = useState<DurationTierRow[]>(initial);
    const [saving, setSaving] = useState(false);
    const [err, setErr] = useState('');

    const setRow = (i: number, patch: Partial<DurationTierRow>) =>
        setRows((r) => r.map((row, j) => (j === i ? { ...row, ...patch } : row)));
    const addRow = () => setRows((r) => [...r, { min_days: '', discount_percent: '', is_active: true }]);
    const removeRow = (i: number) => setRows((r) => r.filter((_, j) => j !== i));

    const save = () => {
        setErr('');
        const days = rows.map((r) => Number(r.min_days));
        if (days.some((d) => !Number.isInteger(d) || d < 1)) return setErr('Số ngày tối thiểu phải là số nguyên ≥ 1.');
        if (new Set(days).size !== days.length) return setErr('Các mốc ngày không được trùng nhau.');
        if (rows.some((r) => Number(r.discount_percent) < 0 || Number(r.discount_percent) > 100)) return setErr('Phần trăm giảm phải trong khoảng 0–100.');
        setSaving(true);
        router.put(route('admin.promotion.duration-tiers'),
            { tiers: rows.map((r) => ({ min_days: Number(r.min_days), discount_percent: Number(r.discount_percent), is_active: r.is_active })) },
            { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    return (
        <div className="mt-6 rounded-[14px] border border-cardBorder bg-white p-5">
            <h2 className="mb-1 text-[15px] font-bold text-pine">Giảm giá thuê dài ngày</h2>
            <p className="mb-4 text-[13px] text-moss">Thuê càng nhiều ngày càng giảm — áp thẳng vào giá thuê (ngoài trần voucher). Bậc cao nhất khớp số ngày sẽ được dùng.</p>

            {err && <div className="mb-3 rounded-[8px] bg-red-50 px-3 py-2 text-[13px] text-red-700">{err}</div>}

            <div className="flex flex-col gap-2">
                {rows.length === 0 && <div className="text-[13px] text-[#8a967a]">Chưa có bậc nào. Bấm “Thêm bậc”.</div>}
                {rows.map((row, i) => (
                    <div key={i} className="flex flex-wrap items-center gap-2">
                        <span className="text-[13px] text-moss">Thuê từ</span>
                        <input type="number" min={1} value={row.min_days} onChange={(e) => setRow(i, { min_days: e.target.value })}
                            className="h-9 w-20 rounded-[8px] border border-cardBorder px-2 text-[14px]" />
                        <span className="text-[13px] text-moss">ngày → giảm</span>
                        <input type="number" min={0} max={100} value={row.discount_percent} onChange={(e) => setRow(i, { discount_percent: e.target.value })}
                            className="h-9 w-20 rounded-[8px] border border-cardBorder px-2 text-[14px]" />
                        <span className="text-[13px] text-moss">%</span>
                        <label className="flex items-center gap-1.5 text-[13px] text-moss">
                            <input type="checkbox" checked={row.is_active} onChange={(e) => setRow(i, { is_active: e.target.checked })} /> Bật
                        </label>
                        <button type="button" onClick={() => removeRow(i)} className="ml-auto rounded-[8px] border border-cardBorder px-2.5 py-1 text-[12.5px] text-[#b3493a] hover:border-[#b3493a]">Xoá</button>
                    </div>
                ))}
            </div>

            <div className="mt-4 flex gap-2">
                <button type="button" onClick={addRow} className="h-10 rounded-control border border-cardBorder px-4 text-[14px] font-semibold text-pine hover:border-grass">+ Thêm bậc</button>
                <button type="button" onClick={save} disabled={saving}
                    className="h-10 rounded-control bg-grass px-5 text-[14px] font-bold text-white transition hover:bg-pine disabled:opacity-60">
                    {saving ? 'Đang lưu…' : 'Lưu bậc giảm giá'}
                </button>
            </div>
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <fieldset className="rounded-card border border-cardBorder bg-card p-5">
            <legend className="px-2 text-[13px] font-bold uppercase tracking-[0.05em] text-grass">{title}</legend>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </fieldset>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[12px] border border-cardBorder bg-white p-3">
            <div className="font-mono text-[18px] font-extrabold text-grass">{value}</div>
            <div className="text-[12px] text-moss">{label}</div>
        </div>
    );
}

const fieldCls = 'h-11 w-full rounded-[10px] border border-cardBorder bg-white px-3 text-[14px] text-ink outline-none focus:border-grass';

function Num({ label, value, onChange, error }: { label: string; value: number | string; onChange: (v: string) => void; error?: string }) {
    return (
        <label className="block">
            <span className="mb-1 block text-[13px] font-semibold text-ink">{label}</span>
            <input type="number" step="any" value={value} onChange={(e) => onChange(e.target.value)} className={fieldCls} />
            {error && <span className="mt-1 block text-[12px] text-red-500">{error}</span>}
        </label>
    );
}

function Select({ label, value, onChange, options }: { label: string; value: string; onChange: (v: string) => void; options: { value: string; label: string }[] }) {
    return (
        <div className="block">
            <span className="mb-1 block text-[13px] font-semibold text-ink">{label}</span>
            <SelectInput value={value} onChange={onChange} options={options} ariaLabel={label} />
        </div>
    );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex cursor-pointer items-center gap-2.5 self-end py-2 sm:col-span-2">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4 accent-grass" />
            <span className="text-[14px] text-ink">{label}</span>
        </label>
    );
}

AdminPromotion.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
