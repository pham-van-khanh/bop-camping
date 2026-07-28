import { Link, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import Logo from '@/Components/Logo';

/** Icon mạng xã hội (chỉ render khi có URL). */
function SocialIcon({ kind }: { kind: 'facebook' | 'tiktok' | 'zalo' }) {
    if (kind === 'facebook') {
        return (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z" /></svg>
        );
    }
    if (kind === 'tiktok') {
        return (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 3h-2.6v12.3a2.3 2.3 0 1 1-2.3-2.3c.16 0 .32.02.47.05V10.3a5 5 0 1 0 4.43 4.97V8.9a6 6 0 0 0 3.5 1.12V7.4a3.5 3.5 0 0 1-3.5-3.5V3Z" /></svg>
        );
    }
    // zalo — chữ "Z" trong khung bo góc
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="2.5" width="19" height="19" rx="5" fill="currentColor" /><path d="M8 8.4h5.4L8.2 15h5.6" stroke="#fff" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" /></svg>
    );
}

export default function Footer() {
    const { site } = usePage<PageProps>().props;
    const hotlines = [site?.hotline_primary, site?.hotline_secondary].filter(Boolean) as string[];
    const socials = [
        { kind: 'facebook' as const, url: site?.facebook_url, label: 'Facebook' },
        { kind: 'tiktok' as const, url: site?.tiktok_url, label: 'TikTok' },
        { kind: 'zalo' as const, url: site?.zalo_1?.url, label: 'Zalo' },
    ].filter((s) => !!s.url);

    return (
        <footer className="mt-5 border-t border-[#c2dcec]" style={{ background: 'rgba(214,236,247,.55)' }}>
            <div className="mx-auto grid max-w-[1400px] gap-7 px-5 pb-[30px] pt-10" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))' }}>
                <div>
                    <div className="mb-3 flex items-center gap-2.5">
                        <Logo size={120} />
                        <span className="text-base font-extrabold text-pine">BỐP CAMPING</span>
                    </div>
                    <p className="m-0 mb-4 max-w-[240px] text-[13.5px] leading-[1.6] text-[#8a967a]">
                        Cho thuê thiết bị cắm trại theo ngày. Giao nhận tận nơi, cọc linh hoạt, trả tiền khi nhận.
                    </p>
                    {/* Social — chỉ hiện icon có link */}
                    {socials.length > 0 && (
                        <div className="flex gap-2.5">
                            {socials.map((s) => (
                                <a
                                    key={s.kind}
                                    href={s.url as string}
                                    target="_blank"
                                    rel="noreferrer"
                                    aria-label={s.label}
                                    title={s.label}
                                    className="grid h-9 w-9 place-items-center rounded-[10px] border border-[#cdd6b6] bg-white text-pine transition hover:border-grass hover:text-grass"
                                >
                                    <SocialIcon kind={s.kind} />
                                </a>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">Thuê đồ</div>
                    <div className="flex flex-col gap-[9px] text-[14px] text-moss">
                        <Link href="/thiet-bi" className="hover:text-grass">Tất cả thiết bị</Link>
                        <Link href="/combos" className="hover:text-grass">Combo tiết kiệm</Link>
                        <Link href="/gioi-thieu" className="hover:text-grass">Giới thiệu</Link>
                        <Link href="/tra-cuu" className="hover:text-grass">Tra cứu đơn</Link>
                        <Link href="/#faq" className="hover:text-grass">Câu hỏi thường gặp</Link>
                    </div>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">Chính sách</div>
                    <div className="flex flex-col gap-[9px] text-[14px] text-moss">
                        <Link href="/chinh-sach-bao-mat" className="hover:text-grass">Chính sách bảo mật</Link>
                        <Link href="/dieu-khoan-su-dung" className="hover:text-grass">Điều khoản sử dụng</Link>
                        <Link href="/chinh-sach-thanh-toan" className="hover:text-grass">Chính sách thanh toán</Link>
                        <Link href="/chinh-sach-giao-nhan" className="hover:text-grass">Chính sách giao nhận</Link>
                        <Link href="/chinh-sach-doi-tra" className="hover:text-grass">Chính sách hủy / đổi / trả</Link>
                    </div>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">Liên hệ Zalo</div>
                    <div className="flex flex-col gap-[9px] text-[14px] text-moss">
                        {[site?.zalo_1, site?.zalo_2].filter((z) => z?.url).map((z, i) => (
                            <a key={i} href={z!.url as string} target="_blank" rel="noreferrer" className="hover:text-grass">
                                {z!.label || 'Zalo'}{z!.phone ? ` · ${z!.phone}` : ''}
                            </a>
                        ))}
                    </div>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">Liên hệ</div>
                    {hotlines.map((h) => (
                        <a key={h} href={`tel:${h.replace(/\s/g, '')}`} className="mb-1.5 block font-mono text-[15px] font-bold text-grass hover:text-pine">
                            {h}
                        </a>
                    ))}
                    <div className="mt-1.5 text-[13.5px] leading-[1.6] text-moss">
                        {site?.working_hours && <>{site.working_hours}<br /></>}
                        {(site?.addresses ?? []).map((a) => (
                            <span key={a.name} className="block">
                                {a.name}{a.area ? ` – ${a.area}` : ''}
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
