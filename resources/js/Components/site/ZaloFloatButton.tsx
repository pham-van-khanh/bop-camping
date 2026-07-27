import { usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import type { PageProps, SiteZalo } from '@/types';

const EASE: [number, number, number, number] = [0.2, 0.7, 0.2, 1];
const ZALO_BLUE = '#0068FF';

function ZaloMark({ size = 26 }: { size?: number }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className="flex-none">
            <rect x="1.5" y="1.5" width="21" height="21" rx="6" fill="#fff" />
            <path d="M7.5 8h6L7.7 16h6.3" stroke={ZALO_BLUE} strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

/** Class dùng chung cho nút tròn — giữ cùng chiều cao (h-12) với nút Góp ý bên dưới. */
const BTN_CLS =
    'grid h-12 w-12 place-items-center rounded-full shadow-lg outline-none transition hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#0068FF]';

/**
 * Nút Zalo nổi, nằm ngay TRÊN nút Góp ý (FeedbackWidget: bottom-5 + h-12 = chiếm 20→68px,
 * nút này ở 80→128px nên hở 12px). Đọc site.zalo_1/zalo_2 từ shared prop.
 * 1 tài khoản → bấm mở thẳng Zalo; 2 tài khoản → mở panel cho khách chọn số.
 */
export default function ZaloFloatButton() {
    const { site } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);

    const accounts: SiteZalo[] = [site?.zalo_1, site?.zalo_2].filter((z): z is SiteZalo => Boolean(z?.url));

    // Đóng panel khi bấm ra ngoài hoặc nhấn Esc.
    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (!wrapRef.current?.contains(e.target as Node)) setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    if (accounts.length === 0) return null;

    // Chỉ 1 tài khoản → liên hệ thẳng, không cần panel.
    if (accounts.length === 1) {
        const only = accounts[0];
        return (
            <a
                href={only.url as string}
                target="_blank"
                rel="noreferrer"
                aria-label={only.label ? `Liên hệ Zalo — ${only.label}` : 'Liên hệ Zalo'}
                title={only.phone ? `Nhắn Zalo ${only.phone}` : 'Nhắn Zalo'}
                className={`fixed bottom-[80px] right-5 z-[80] ${BTN_CLS}`}
                style={{ background: ZALO_BLUE }}
            >
                <ZaloMark />
            </a>
        );
    }

    return (
        <div ref={wrapRef} className="fixed bottom-[80px] right-5 z-[80]">
            <AnimatePresence>
                {open && (
                    <motion.div
                        role="menu"
                        aria-label="Chọn tài khoản Zalo"
                        initial={{ opacity: 0, y: 6 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: 6 }}
                        transition={{ duration: 0.18, ease: EASE }}
                        className="absolute bottom-[calc(100%+10px)] right-0 w-[248px] rounded-[14px] border border-cardBorder bg-white p-2 shadow-xl"
                    >
                        <div className="px-2 pb-1.5 pt-1 font-mono text-[11px] tracking-[0.1em] text-campfire">NHẮN ZALO CHO TỤI MÌNH</div>
                        {accounts.map((z, i) => (
                            <a
                                key={i}
                                role="menuitem"
                                href={z.url as string}
                                target="_blank"
                                rel="noreferrer"
                                onClick={() => setOpen(false)}
                                className="flex items-center gap-2.5 rounded-[10px] px-2 py-2 transition hover:bg-[#f1f4ea]"
                            >
                                <span className="grid h-8 w-8 flex-none place-items-center rounded-[9px]" style={{ background: ZALO_BLUE }}>
                                    <ZaloMark size={18} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[13.5px] font-bold text-ink">{z.label || 'Liên hệ Zalo'}</span>
                                    {z.phone && <span className="block font-mono text-[12.5px] text-moss">{z.phone}</span>}
                                </span>
                            </a>
                        ))}
                    </motion.div>
                )}
            </AnimatePresence>

            <button
                onClick={() => setOpen((v) => !v)}
                aria-label="Liên hệ Zalo"
                aria-haspopup="menu"
                aria-expanded={open}
                title="Nhắn Zalo cho BỐP CAMPING"
                className={BTN_CLS}
                style={{ background: ZALO_BLUE }}
            >
                <ZaloMark />
            </button>
        </div>
    );
}
