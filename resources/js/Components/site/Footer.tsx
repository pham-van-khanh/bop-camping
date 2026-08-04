import Logo from '@/Components/Logo';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';

/** Icon mạng xã hội (chỉ render khi có URL). */

export default function Footer() {
    const { site } = usePage<PageProps>().props;
    const hotlines = [site?.hotline_primary, site?.hotline_secondary].filter(
        Boolean,
    ) as string[];

    return (
        <footer
            className="mt-5 border-t border-[#c2dcec]"
            style={{ background: 'rgba(214,236,247,.55)' }}
        >
            <div
                className="mx-auto grid max-w-[1400px] gap-7 px-5 pb-[30px] pt-10"
                style={{
                    gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                }}
            >
                <div>
                    <div className="mb-3 flex items-center gap-2.5">
                        <Logo size={120} />
                        <span className="text-base font-extrabold text-pine">
                            BỐP CAMPING
                        </span>
                    </div>
                    <p className="m-0 mb-4 max-w-[240px] text-[13.5px] leading-[1.6] text-[#8a967a]">
                        Cho thuê thiết bị cắm trại theo ngày. Giao nhận tận nơi,
                        cọc linh hoạt, trả tiền khi nhận.
                    </p>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">
                        Thuê đồ
                    </div>
                    <div className="flex flex-col gap-[9px] text-[14px] text-moss">
                        <Link href="/thiet-bi" className="hover:text-grass">
                            Tất cả thiết bị
                        </Link>
                        <Link href="/combos" className="hover:text-grass">
                            Combo tiết kiệm
                        </Link>
                        <Link href="/gioi-thieu" className="hover:text-grass">
                            Giới thiệu
                        </Link>
                        <Link href="/tra-cuu" className="hover:text-grass">
                            Tra cứu đơn
                        </Link>
                        <Link href="/#faq" className="hover:text-grass">
                            Câu hỏi thường gặp
                        </Link>
                    </div>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">
                        Chính sách
                    </div>
                    <div className="flex flex-col gap-[9px] text-[14px] text-moss">
                        <Link
                            href="/chinh-sach-bao-mat"
                            className="hover:text-grass"
                        >
                            Chính sách bảo mật
                        </Link>
                        <Link
                            href="/dieu-khoan-su-dung"
                            className="hover:text-grass"
                        >
                            Điều khoản sử dụng
                        </Link>
                        <Link
                            href="/chinh-sach-thanh-toan"
                            className="hover:text-grass"
                        >
                            Chính sách thanh toán
                        </Link>
                        <Link
                            href="/chinh-sach-giao-nhan"
                            className="hover:text-grass"
                        >
                            Chính sách giao nhận
                        </Link>
                        <Link
                            href="/chinh-sach-doi-tra"
                            className="hover:text-grass"
                        >
                            Chính sách hủy / đổi / trả
                        </Link>
                    </div>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">
                        Liên hệ Zalo
                    </div>
                    <div className="flex flex-col gap-[9px] text-[14px] text-moss">
                        {[site?.zalo_1, site?.zalo_2]
                            .filter((z) => z?.url)
                            .map((z, i) => (
                                <a
                                    key={i}
                                    href={z!.url as string}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="hover:text-grass"
                                >
                                    {z!.label || 'Zalo'}
                                    {z!.phone ? ` · ${z!.phone}` : ''}
                                </a>
                            ))}
                    </div>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">
                        Liên hệ
                    </div>
                    {hotlines.map((h) => (
                        <a
                            key={h}
                            href={`tel:${h.replace(/\s/g, '')}`}
                            className="mb-1.5 block font-mono text-[15px] font-bold text-grass hover:text-pine"
                        >
                            {h}
                        </a>
                    ))}
                    <div className="mt-1.5 text-[13.5px] leading-[1.6] text-moss">
                        {site?.working_hours && (
                            <>
                                {site.working_hours}
                                <br />
                            </>
                        )}
                        {(site?.addresses ?? []).map((a) => (
                            <span key={a.name} className="block">
                                {a.name}
                                {a.area ? ` – ${a.area}` : ''}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
            <div className="mx-auto max-w-[1400px] border-t border-[#eef0e6] px-5 pb-[30px] pt-3.5 font-mono text-[12px] text-[#a3ad92]">
                © 2026 BỐP CAMPING · Thuê đồ, đi liền.
            </div>
        </footer>
    );
}
