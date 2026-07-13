import { motion } from 'framer-motion';
import type { SiteZalo } from '@/types';

const EASE: [number, number, number, number] = [0.2, 0.7, 0.2, 1];

function ZaloMark() {
    return (
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" className="flex-none">
            <rect x="1.5" y="1.5" width="21" height="21" rx="6" fill="#0068FF" />
            <path d="M7.5 8h6L7.7 16h6.3" stroke="#fff" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

/** Dải liên hệ Zalo ngay dưới hero — mỗi tài khoản 1 card, bấm mở Zalo. */
export default function ZaloContactStrip({ accounts }: { accounts: SiteZalo[] }) {
    const usable = accounts.filter((z) => z?.url);
    if (usable.length === 0) return null;

    return (
        <section className="mx-auto max-w-[1400px] px-5 pt-8">
            <motion.div
                initial={{ opacity: 0, y: 16 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, amount: 0.3 }}
                transition={{ duration: 0.5, ease: EASE }}
                className="rounded-[18px] border border-cardBorder bg-card p-4"
            >
                <div className="mb-3 flex items-center gap-2">
                    <span className="font-mono text-[12px] tracking-[0.1em] text-campfire">LIÊN HỆ NHANH QUA ZALO</span>
                    <span className="text-[13px] text-moss">— nhắn tụi mình để được tư vấn và đặt đồ nhanh nhất</span>
                </div>
                <div className="grid gap-3" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))' }}>
                    {usable.map((z, i) => (
                        <a
                            key={i}
                            href={z.url as string}
                            target="_blank"
                            rel="noreferrer"
                            className="group flex items-center gap-3 rounded-[14px] border border-cardBorder bg-white px-4 py-3 transition hover:-translate-y-0.5 hover:border-[#0068FF] hover:shadow-cardhover"
                        >
                            <ZaloMark />
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-[14.5px] font-bold text-ink">{z.label || 'Liên hệ Zalo'}</div>
                                {z.phone && <div className="font-mono text-[13px] text-moss">{z.phone}</div>}
                            </div>
                            <span
                                className="whitespace-nowrap rounded-control px-3.5 py-2 text-[13px] font-bold text-white transition"
                                style={{ background: '#0068FF' }}
                            >
                                Nhắn Zalo →
                            </span>
                        </a>
                    ))}
                </div>
            </motion.div>
        </section>
    );
}
