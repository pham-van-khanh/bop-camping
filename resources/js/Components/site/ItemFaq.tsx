export type ItemFaqRow = { question: string; answer: string };

/**
 * "Câu hỏi thường gặp" của MỘT sản phẩm / combo (bopcamping-0345).
 *
 * Dùng <details> gốc của trình duyệt, không giữ state: câu trả lời luôn nằm trong HTML
 * (kể cả khi đang gập) nên crawler đọc được, và khớp từng chữ với FAQPage JSON-LD mà
 * server sinh từ cùng mảng `faqs`. Không có câu nào thì không vẽ gì.
 */
export default function ItemFaq({
    faqs,
    className = '',
}: {
    faqs?: ItemFaqRow[] | null;
    className?: string;
}) {
    if (!faqs || faqs.length === 0) return null;

    return (
        <section
            aria-labelledby="item-faq-heading"
            className={`rounded-[16px] border border-cardBorder bg-white px-5 py-4 ${className}`}
        >
            <h2
                id="item-faq-heading"
                className="mb-1 font-mono text-[12px] font-bold tracking-[0.14em] text-campfire"
            >
                CÂU HỎI THƯỜNG GẶP
            </h2>
            <div className="divide-y divide-[#f1f4ea]">
                {faqs.map((row, i) => (
                    <details key={i} className="group py-1" open={i === 0}>
                        <summary className="flex cursor-pointer list-none items-start justify-between gap-3 py-2 text-[14.5px] font-semibold text-ink marker:hidden [&::-webkit-details-marker]:hidden">
                            <h3 className="text-[14.5px] font-semibold">
                                {row.question}
                            </h3>
                            <span
                                aria-hidden
                                className="mt-0.5 flex-none text-[18px] leading-none text-moss transition group-open:rotate-45"
                            >
                                +
                            </span>
                        </summary>
                        <p className="whitespace-pre-line pb-2.5 text-[14px] leading-relaxed text-pine">
                            {row.answer}
                        </p>
                    </details>
                ))}
            </div>
        </section>
    );
}
