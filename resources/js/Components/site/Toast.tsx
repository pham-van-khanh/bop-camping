import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import { on, EVENTS } from '@/lib/bus';

/** Toast nổi giữa-dưới, tự ẩn ~3.2s (RULES mục 7). Nghe sự kiện EVENTS.toast. */
export default function Toast() {
    const [msg, setMsg] = useState<string | null>(null);
    const timer = useRef<number>();

    useEffect(() => {
        return on(EVENTS.toast, (detail) => {
            setMsg(String(detail ?? 'Đã thêm vào giỏ'));
            window.clearTimeout(timer.current);
            timer.current = window.setTimeout(() => setMsg(null), 3200);
        });
    }, []);

    return (
        <AnimatePresence>
            {msg && (
                <motion.div
                    initial={{ opacity: 0, y: 24 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: 24 }}
                    transition={{ duration: 0.25, ease: [0.2, 0.7, 0.2, 1] }}
                    className="fixed bottom-[26px] left-1/2 z-[80] flex -translate-x-1/2 items-center gap-3 rounded-[13px] px-5 py-3 text-white shadow-toast"
                    style={{ background: '#2C3D22' }}
                    role="status"
                >
                    <span className="grid h-6 w-6 flex-none place-items-center rounded-full" style={{ background: '#7FA64B' }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="m5 13 4 4L19 7" stroke="#fff" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" /></svg>
                    </span>
                    <span className="text-[14px] font-semibold">{msg}</span>
                    <Link href="/gio-thue" className="rounded-[9px] bg-grass px-3 py-[7px] text-[13px] font-bold text-white" onClick={() => setMsg(null)}>
                        Xem giỏ
                    </Link>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
