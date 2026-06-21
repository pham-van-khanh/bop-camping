import { Link } from '@inertiajs/react';

export default function Footer() {
    return (
        <footer className="mt-5 border-t border-[#c2dcec]" style={{ background: 'rgba(214,236,247,.55)' }}>
            <div className="mx-auto grid max-w-[1200px] gap-7 px-5 pb-[30px] pt-10" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))' }}>
                <div>
                    <div className="mb-3 flex items-center gap-2.5">
                        <span className="grid h-8 w-8 place-items-center rounded-[9px] bg-grass">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 4 4 19h16L12 4Z" fill="#cfe0a8" /><path d="M12 10l-4 9h8l-4-9Z" fill="#557A2B" /></svg>
                        </span>
                        <span className="text-base font-extrabold text-pine">BỐP CAMPING</span>
                    </div>
                    <p className="m-0 max-w-[240px] text-[13.5px] leading-[1.6] text-[#8a967a]">
                        Cho thuê thiết bị cắm trại theo ngày. Giao nhận tận nơi, cọc linh hoạt, trả tiền khi nhận.
                    </p>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">Thiết bị</div>
                    <div className="flex flex-col gap-[9px] text-[14px] text-moss">
                        <Link href="/thiet-bi" className="hover:text-grass">Lều trại</Link>
                        <Link href="/thiet-bi" className="hover:text-grass">Túi ngủ &amp; nệm</Link>
                        <Link href="/thiet-bi" className="hover:text-grass">Bếp &amp; nấu nướng</Link>
                        <Link href="/thiet-bi" className="hover:text-grass">Đèn &amp; ánh sáng</Link>
                    </div>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">Hỗ trợ</div>
                    <div className="flex flex-col gap-[9px] text-[14px] text-moss">
                        <span>Hướng dẫn thuê</span>
                        <span>Chính sách cọc</span>
                        <Link href="/tra-cuu" className="hover:text-grass">Tra cứu đơn</Link>
                        <span>Câu hỏi thường gặp</span>
                    </div>
                </div>

                <div>
                    <div className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#a3ad92]">Liên hệ</div>
                    <div className="mb-1.5 font-mono text-[15px] font-bold text-grass">0905 123 456</div>
                    <div className="text-[13.5px] leading-[1.6] text-moss">
                        8:00 - 21:00 hằng ngày<br />123 Đường Trại, Đà Nẵng
                    </div>
                </div>
            </div>
            <div className="mx-auto max-w-[1200px] border-t border-[#eef0e6] px-5 pb-[30px] pt-3.5 font-mono text-[12px] text-[#a3ad92]">
                © 2026 BỐP CAMPING · Thuê đồ, đi liền.
            </div>
        </footer>
    );
}
