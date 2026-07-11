import { Head, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';

type Settings = {
    hotline_primary: string | null;
    hotline_secondary: string | null;
    zalo1_label: string | null;
    zalo1_phone: string | null;
    zalo1_url: string | null;
    zalo2_label: string | null;
    zalo2_phone: string | null;
    zalo2_url: string | null;
    facebook_url: string | null;
    tiktok_url: string | null;
    working_hours: string | null;
    ga_measurement_id: string | null;
    google_site_verification: string | null;
};

type Props = PageProps<{
    settings: Settings;
    locations: { name: string; area: string | null }[];
}>;

const inputCls = 'w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass';

export default function AdminSiteSettings() {
    const { settings, locations, flash } = usePage<Props>().props;
    const [toast, setToast] = useState('');

    const { data, setData, put, processing, errors } = useForm<Record<string, string>>({
        hotline_primary: settings.hotline_primary ?? '',
        hotline_secondary: settings.hotline_secondary ?? '',
        zalo1_label: settings.zalo1_label ?? '',
        zalo1_phone: settings.zalo1_phone ?? '',
        zalo1_url: settings.zalo1_url ?? '',
        zalo2_label: settings.zalo2_label ?? '',
        zalo2_phone: settings.zalo2_phone ?? '',
        zalo2_url: settings.zalo2_url ?? '',
        facebook_url: settings.facebook_url ?? '',
        tiktok_url: settings.tiktok_url ?? '',
        working_hours: settings.working_hours ?? '',
        ga_measurement_id: settings.ga_measurement_id ?? '',
        google_site_verification: settings.google_site_verification ?? '',
    });

    useEffect(() => {
        if (flash.success) {
            setToast(flash.success);
            const t = setTimeout(() => setToast(''), 2500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    const field = (key: keyof typeof data, label: string, placeholder = '', hint = '') => (
        <div>
            <label className="mb-1.5 block text-[13px] font-semibold text-pine">{label}</label>
            <input value={data[key]} onChange={(e) => setData(key, e.target.value)} placeholder={placeholder} className={inputCls} />
            {hint && <p className="mt-1 text-[11px] text-[#a3ad92]">{hint}</p>}
            {errors[key] && <p className="mt-1 text-[12px] text-[#b3493a]">{errors[key]}</p>}
        </div>
    );

    return (
        <>
            <Head title="Admin · Cài đặt shop" />
            <div className="mx-auto max-w-[820px] p-6">
                <h1 className="mb-1 text-[22px] font-extrabold text-ink">Cài đặt shop</h1>
                <p className="mb-5 text-[14px] text-moss">Thông tin liên hệ &amp; mạng xã hội hiển thị ở footer và dải Zalo trang chủ.</p>

                {toast && (
                    <div className="mb-4 rounded-[10px] border border-grass/40 bg-[#eef2e3] px-4 py-2.5 text-[14px] font-semibold text-pine">{toast}</div>
                )}

                <form onSubmit={submit} className="space-y-6">
                    {/* Hotline */}
                    <section className="rounded-card border border-cardBorder bg-card p-5">
                        <h2 className="mb-3 text-[15px] font-bold text-pine">Số điện thoại</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {field('hotline_primary', 'Hotline chính', '0976544370', 'Khách bấm vào sẽ gọi trực tiếp')}
                            {field('hotline_secondary', 'Hotline phụ', '0373655008')}
                            {field('working_hours', 'Giờ làm việc', '8:00 – 21:00 hằng ngày')}
                        </div>
                    </section>

                    {/* Zalo */}
                    <section className="rounded-card border border-cardBorder bg-card p-5">
                        <h2 className="mb-1 text-[15px] font-bold text-pine">Tài khoản Zalo</h2>
                        <p className="mb-3 text-[12.5px] text-moss">Để trống ô "Link Zalo" thì hệ thống tự dùng <span className="font-mono">zalo.me/&lt;số điện thoại&gt;</span>.</p>
                        <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            {field('zalo1_label', 'Tên hiển thị #1', 'Tư vấn & đặt đồ')}
                            {field('zalo1_phone', 'SĐT Zalo #1', '0976544370')}
                            {field('zalo1_url', 'Link Zalo #1 (tuỳ chọn)', 'https://zalo.me/...')}
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            {field('zalo2_label', 'Tên hiển thị #2', 'Hỗ trợ thêm')}
                            {field('zalo2_phone', 'SĐT Zalo #2', '0373655008')}
                            {field('zalo2_url', 'Link Zalo #2 (tuỳ chọn)', 'https://zalo.me/...')}
                        </div>
                    </section>

                    {/* Social */}
                    <section className="rounded-card border border-cardBorder bg-card p-5">
                        <h2 className="mb-1 text-[15px] font-bold text-pine">Mạng xã hội</h2>
                        <p className="mb-3 text-[12.5px] text-moss">Để trống thì icon tương ứng sẽ ẩn ở footer.</p>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {field('facebook_url', 'Facebook', 'https://facebook.com/...')}
                            {field('tiktok_url', 'TikTok', 'https://tiktok.com/@...')}
                        </div>
                    </section>

                    {/* SEO & theo dõi */}
                    <section className="rounded-card border border-cardBorder bg-card p-5">
                        <h2 className="mb-1 text-[15px] font-bold text-pine">SEO &amp; theo dõi</h2>
                        <p className="mb-3 text-[12.5px] text-moss">
                            Để trống thì không chèn mã nào. Lấy mã GA4 ở <span className="font-semibold">Google Analytics → Quản trị → Luồng dữ liệu</span>,
                            mã xác minh ở <span className="font-semibold">Google Search Console</span> (phương thức thẻ HTML).
                        </p>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {field('ga_measurement_id', 'Mã đo lường GA4', 'G-XXXXXXXXXX', 'Dạng G-XXXXXXXX — bật Google Analytics 4')}
                            {field('google_site_verification', 'Mã xác minh Search Console', 'abcXyz123...', 'Chỉ dán phần content của thẻ meta verification')}
                        </div>
                    </section>

                    {/* Địa chỉ (read-only, lấy từ Điểm cắm trại) */}
                    <section className="rounded-card border border-cardBorder bg-[#f8faf4] p-5">
                        <h2 className="mb-1 text-[15px] font-bold text-pine">Địa chỉ hiển thị</h2>
                        <p className="mb-3 text-[12.5px] text-moss">
                            Địa chỉ ở footer lấy từ các vị trí đang mở trong mục <span className="font-semibold">Điểm cắm trại</span> — sửa ở đó.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {locations.length === 0 ? (
                                <span className="text-[13px] text-moss">Chưa có vị trí phục vụ nào đang mở.</span>
                            ) : (
                                locations.map((l) => (
                                    <span key={l.name} className="rounded-pill bg-white px-3 py-1.5 text-[13px] font-semibold text-pine border border-cardBorder">
                                        {l.name}{l.area ? ` – ${l.area}` : ''}
                                    </span>
                                ))
                            )}
                        </div>
                    </section>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-[10px] bg-grass px-6 py-2.5 text-[14px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                        >
                            {processing ? 'Đang lưu…' : 'Lưu thông tin'}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}

AdminSiteSettings.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
