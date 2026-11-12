<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nhập FAQ soạn sẵn cho sản phẩm / combo (bopcamping-0345) từ
 * database/seeders/data/item_faqs.json — bản đọc được: artifacts/faq_san_pham_combo_2026-11.md.
 *
 * Là MIGRATION chứ không phải seeder chạy mỗi lần deploy: FAQ không có trạng thái nháp,
 * nên nếu nhập lặp lại thì món nào chủ shop cố ý xoá hết câu hỏi sẽ bị điền lại. Chạy
 * đúng một lần thì quyền sửa/xoá về sau hoàn toàn thuộc admin.
 *
 * Chỉ điền món CHƯA có câu nào (faqs NULL) — không ghi đè thứ admin đã nhập. Slug không
 * có trong DB (dev và production đặt tên khác nhau) thì bỏ qua.
 */
return new class extends Migration
{
    private const DATA_FILE = __DIR__.'/../seeders/data/item_faqs.json';

    private const TABLES = ['products' => 'products', 'combos' => 'combos'];

    public function up(): void
    {
        foreach ($this->data() as $group => $items) {
            foreach ($items as $slug => $faqs) {
                DB::table(self::TABLES[$group])
                    ->where('slug', $slug)
                    ->whereNull('faqs')
                    ->update(['faqs' => json_encode($faqs, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    /** Gỡ đúng những món còn nguyên nội dung đã nhập; món admin đã sửa thì để yên. */
    public function down(): void
    {
        foreach ($this->data() as $group => $items) {
            foreach ($items as $slug => $faqs) {
                $row = DB::table(self::TABLES[$group])->where('slug', $slug)->first(['id', 'faqs']);
                if ($row && json_decode((string) $row->faqs, true) == $faqs) {
                    DB::table(self::TABLES[$group])->where('id', $row->id)->update(['faqs' => null]);
                }
            }
        }
    }

    /** @return array<string, array<string, list<array{question: string, answer: string}>>> */
    private function data(): array
    {
        $data = json_decode((string) file_get_contents(self::DATA_FILE), true, 512, JSON_THROW_ON_ERROR);

        return array_intersect_key($data, self::TABLES);
    }
};
