import type { ItemFaqRow } from '@/Components/site/ItemFaq';

/** Khớp App\Support\FaqRows::MAX. */
export const FAQ_MAX = 12;

/**
 * Ô nhập các cặp hỏi–đáp cho một sản phẩm / combo (bopcamping-0345). Dùng chung ở
 * ProductForm và form combo. Dòng thiếu câu hỏi hoặc câu trả lời bị server báo lỗi.
 */
export default function FaqRowsEditor({
    rows,
    onChange,
    errors,
}: {
    rows: ItemFaqRow[];
    onChange: (rows: ItemFaqRow[]) => void;
    errors: Partial<Record<string, string>>;
}) {
    const set = (i: number, field: keyof ItemFaqRow, value: string) =>
        onChange(
            rows.map((row, idx) =>
                idx === i ? { ...row, [field]: value } : row,
            ),
        );
    const firstError = Object.entries(errors).find(([k]) =>
        k.startsWith('faqs'),
    )?.[1];
    const input =
        'w-full rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] outline-none transition focus:border-grass';

    return (
        <div>
            <p className="mb-2 text-[12px] leading-[1.5] text-moss">
                Những câu khách hay hỏi về riêng món này (mấy người dùng, chở xe
                máy được không, có kèm phụ kiện gì…). Hiện ở cuối trang và giúp
                Google hiểu trang trả lời câu hỏi nào.
            </p>
            {rows.length > 0 && (
                <div className="mb-2 space-y-2.5">
                    {rows.map((row, i) => (
                        <div
                            key={i}
                            className="rounded-[10px] border border-cardBorder bg-[#fbfcf8] p-2.5"
                        >
                            <div className="flex items-start gap-1.5">
                                <input
                                    type="text"
                                    aria-label={`Câu hỏi ${i + 1}`}
                                    value={row.question}
                                    maxLength={200}
                                    onChange={(e) =>
                                        set(i, 'question', e.target.value)
                                    }
                                    placeholder="VD: Lều này ngủ được mấy người?"
                                    className={input}
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        onChange(
                                            rows.filter((_, idx) => idx !== i),
                                        )
                                    }
                                    aria-label={`Xoá câu hỏi ${i + 1}`}
                                    title="Xoá câu hỏi"
                                    className="grid h-8 w-8 flex-none place-items-center rounded-[8px] border border-[#f6ddd6] text-[13px] font-bold text-[#b3493a] transition hover:bg-[#f6ddd6]"
                                >
                                    ×
                                </button>
                            </div>
                            <textarea
                                aria-label={`Trả lời ${i + 1}`}
                                value={row.answer}
                                maxLength={1500}
                                rows={2}
                                onChange={(e) =>
                                    set(i, 'answer', e.target.value)
                                }
                                placeholder="Trả lời ngắn gọn, đúng với món này."
                                className={`${input} mt-1.5`}
                            />
                        </div>
                    ))}
                </div>
            )}
            {rows.length < FAQ_MAX && (
                <button
                    type="button"
                    onClick={() =>
                        onChange([...rows, { question: '', answer: '' }])
                    }
                    className="rounded-[8px] border border-dashed border-cardBorder px-3 py-1.5 text-[12.5px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                >
                    + Thêm câu hỏi
                </button>
            )}
            {firstError && (
                <p className="mt-1 text-[12px] text-[#b3493a]">{firstError}</p>
            )}
        </div>
    );
}
