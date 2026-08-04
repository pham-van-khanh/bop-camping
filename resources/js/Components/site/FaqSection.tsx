import { motion } from 'framer-motion';
import { useState } from 'react';

export type FaqItem = { id: number; question: string; answer: string };

const EASE: [number, number, number, number] = [0.2, 0.7, 0.2, 1];

/** Câu hỏi thường gặp — accordion tông be/đất, mở 1 câu tại một thời điểm. */
export default function FaqSection({ faqs }: { faqs: FaqItem[] }) {
    const [open, setOpen] = useState<number | null>(faqs[0]?.id ?? null);

    if (faqs.length === 0) return null;

    return (
        <section
            id="faq"
            className="mx-auto max-w-[820px] scroll-mt-24 px-5 pb-5 pt-[54px]"
        >
            <motion.div
                initial={{ opacity: 0, y: 18 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, amount: 0.2 }}
                transition={{ duration: 0.6, ease: EASE }}
                className="mb-6 text-center"
            >
                <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                    GIẢI ĐÁP THẮC MẮC
                </div>
                <h2
                    className="font-extrabold tracking-tight text-ink"
                    style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                >
                    Câu hỏi thường gặp
                </h2>
            </motion.div>

            <div className="flex flex-col gap-3">
                {faqs.map((faq, i) => {
                    const isOpen = open === faq.id;
                    return (
                        <motion.div
                            key={faq.id}
                            initial={{ opacity: 0, y: 12 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, amount: 0.3 }}
                            transition={{
                                duration: 0.45,
                                delay: Math.min(i, 6) * 0.04,
                                ease: EASE,
                            }}
                            className="overflow-hidden rounded-[14px] border border-cardBorder bg-card"
                        >
                            <button
                                onClick={() => setOpen(isOpen ? null : faq.id)}
                                aria-expanded={isOpen}
                                className="flex w-full items-center justify-between gap-3 px-5 py-4 text-left"
                            >
                                <span className="text-[15.5px] font-bold text-ink">
                                    {faq.question}
                                </span>
                                <span
                                    className="grid h-7 w-7 flex-none place-items-center rounded-full transition"
                                    style={{
                                        background: isOpen
                                            ? '#557A2B'
                                            : '#eef2e3',
                                        color: isOpen ? '#fff' : '#557A2B',
                                        transform: isOpen
                                            ? 'rotate(45deg)'
                                            : 'none',
                                    }}
                                >
                                    <svg
                                        width="14"
                                        height="14"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <path
                                            d="M12 5v14M5 12h14"
                                            stroke="currentColor"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                        />
                                    </svg>
                                </span>
                            </button>
                            <motion.div
                                initial={false}
                                animate={{
                                    height: isOpen ? 'auto' : 0,
                                    opacity: isOpen ? 1 : 0,
                                }}
                                transition={{ duration: 0.3, ease: EASE }}
                                style={{ overflow: 'hidden' }}
                            >
                                <p className="whitespace-pre-line px-5 pb-4 text-[14.5px] leading-[1.65] text-[#3f4a32]">
                                    {faq.answer}
                                </p>
                            </motion.div>
                        </motion.div>
                    );
                })}
            </div>
        </section>
    );
}
