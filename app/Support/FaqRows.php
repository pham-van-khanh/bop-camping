<?php

namespace App\Support;

/**
 * Câu hỏi thường gặp gắn với MỘT sản phẩm / combo (bopcamping-0345) — cột JSON `faqs`.
 *
 * Một nguồn cho cả ba chỗ: form admin (RULES + clean), mục FAQ hiện trên trang chi tiết,
 * và FAQPage JSON-LD (SeoService::faqPage đọc đúng mảng này). Hai chỗ sau dùng chung dữ
 * liệu nên schema không bao giờ khai câu hỏi không hiện trên trang.
 */
class FaqRows
{
    public const MAX = 12;

    /** Luật validate dùng chung cho form sản phẩm và combo. */
    public const RULES = [
        'faqs' => 'sometimes|nullable|array|max:'.self::MAX,
        'faqs.*.question' => 'required|string|max:200',
        'faqs.*.answer' => 'required|string|max:1500',
    ];

    public const MESSAGES = [
        'faqs.max' => 'Tối đa '.self::MAX.' câu hỏi cho mỗi mục.',
        'faqs.*.question.required' => 'Câu hỏi không được bỏ trống.',
        'faqs.*.answer.required' => 'Câu trả lời không được bỏ trống.',
    ];

    /**
     * Chuẩn hoá từ form: trim, bỏ dòng thiếu câu hỏi hoặc câu trả lời; rỗng → null.
     *
     * @param  array<int, array<string, mixed>>|null|string  $rows  ('' khi form gửi "xoá hết")
     * @return list<array{question: string, answer: string}>|null
     */
    public static function clean(mixed $rows): ?array
    {
        $clean = collect(is_array($rows) ? $rows : [])
            ->map(fn ($row) => [
                'question' => trim((string) ($row['question'] ?? '')),
                'answer' => trim((string) ($row['answer'] ?? '')),
            ])
            ->filter(fn (array $row) => $row['question'] !== '' && $row['answer'] !== '')
            ->values()
            ->all();

        return $clean === [] ? null : $clean;
    }
}
