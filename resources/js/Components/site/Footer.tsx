import { Link } from '@inertiajs/react';

export default function Footer() {
    return (
        <footer className="mt-14 border-t border-[#c2dcec]" style={{ background: 'rgba(214,236,247,.55)' }}>
            <div className="mx-auto grid max-w-[1200px] grid-cols-1 gap-8 px-5 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div className="flex items-center gap-2.5">
                        <span className="grid h-[34px] w-[34px] place-items-center rounded-[10px] bg-grass">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2" strokeLinejoin="round">
                                <path d="M12 4 L21 20 H3 Z" />
                                <path d="M12 4 V20" />
                            </svg>
                        </span>
                        <span className="text-base font-extrabold tracking-tight text-pine">BỐP CAMPING</span>
                    </div>
                    <p className="mt-3 max-w-[34ch] text-sm text-moss">
                        Cho thuê thiết bị cắm trại theo ngày. Soạn sẵn cả khu trại, tụi mình giao tận nơi.
                    </p>
                </div>

                <div>
                    <h4 className="font-mono text-[11px] uppercase tracking-[0.1em] text-moss">Thiết bị</h4>
                    <ul className="mt-3 space-y-2 text-sm text-ink/80">
                        <li><Link href="/thiet-bi" className="hover:text-grass">Lều trại</Link></li>
                        <li><Link href="/thiet-bi" className="hover:text-grass">Túi ngủ & nệm</Link></li>
                        <li><Link href="/thiet-bi" className="hover:text-grass">Bếp & nấu</Link></li>
                        <li><Link href="/thiet-bi" className="hover:text-grass">Đèn & bàn ghế</Link></li>
                    </ul>
                </div>

                <div>
                    <h4 className="font-mono text-[11px] uppercase tracking-[0.1em] text-moss">Hỗ trợ</h4>
                    <ul className="mt-3 space-y-2 text-sm text-ink/80">
                        <li><Link href="/tra-cuu" className="hover:text-grass">Tra cứu đơn</Link></li>
                        <li>Cách thuê đồ</li>
                        <li>Chính sách cọc</li>
                        <li>Câu hỏi thường gặp</li>
                    </ul>
                </div>

                <div>
                    <h4 className="font-mono text-[11px] uppercase tracking-[0.1em] text-moss">Liên hệ</h4>
                    <ul className="mt-3 space-y-2 text-sm text-ink/80">
                        <li className="font-mono text-grass">0905 123 456</li>
                        <li>8:00 - 21:00 mỗi ngày</li>
                        <li>Giao nhận nội thành</li>
                    </ul>
                </div>
            </div>
            <div className="mx-auto max-w-[1200px] border-t border-[#c2dcec] px-5 py-5">
                <p className="font-mono text-[11px] tracking-wider text-moss">© 2026 BỐP CAMPING · Thuê đồ dã ngoại</p>
            </div>
        </footer>
    );
}
