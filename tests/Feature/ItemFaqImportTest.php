<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\Product;
use App\Support\FaqRows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-0345 — migration nhập FAQ soạn sẵn: chỉ điền món chưa có câu nào, và dữ
 * liệu trong file phải qua được đúng luật của form admin.
 */
class ItemFaqImportTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'dem-hoi-bom-hoi-horizon';

    private function migration(): object
    {
        return require database_path('migrations/2026_11_12_000002_import_item_faqs.php');
    }

    /** @return array<string, array<string, list<array{question: string, answer: string}>>> */
    private function data(): array
    {
        return json_decode((string) file_get_contents(database_path('seeders/data/item_faqs.json')), true);
    }

    /** @test */
    public function data_file_obeys_the_admin_form_rules(): void
    {
        $data = $this->data();
        $this->assertSame(['products', 'combos'], array_keys($data));

        foreach ($data as $group => $items) {
            $this->assertNotEmpty($items);
            foreach ($items as $slug => $faqs) {
                $this->assertNotEmpty($faqs, $slug);
                $this->assertLessThanOrEqual(FaqRows::MAX, count($faqs), $slug);
                // clean() không bỏ dòng nào = không dòng nào thiếu câu hỏi / câu trả lời.
                $this->assertSame($faqs, FaqRows::clean($faqs), $slug);
                $this->assertSame(count($faqs), count(array_unique(array_column($faqs, 'question'))), "$slug: trùng câu hỏi");
                foreach ($faqs as $row) {
                    $this->assertLessThanOrEqual(200, mb_strlen($row['question']), $slug);
                    $this->assertLessThanOrEqual(1500, mb_strlen($row['answer']), $slug);
                }
            }
        }
    }

    /** @test */
    public function fills_items_without_faqs_and_leaves_admin_content_alone(): void
    {
        $mine = [['question' => 'Câu của shop?', 'answer' => 'Trả lời của shop.']];
        $empty = Product::factory()->create(['slug' => self::SLUG, 'faqs' => null]);
        $edited = Product::factory()->create(['slug' => 'bat-lot-leu-naturehike', 'faqs' => $mine]);
        $other = Product::factory()->create(['slug' => 'mon-khong-co-trong-file', 'faqs' => null]);
        $combo = Combo::factory()->create(['slug' => 'relax', 'faqs' => null]);

        $this->migration()->up();

        $data = $this->data();
        $this->assertEquals($data['products'][self::SLUG], $empty->fresh()->faqs);
        $this->assertEquals($data['combos']['relax'], $combo->fresh()->faqs);
        $this->assertEquals($mine, $edited->fresh()->faqs);
        $this->assertNull($other->fresh()->faqs);
    }

    /** @test */
    public function imported_faqs_show_up_on_the_page_with_schema(): void
    {
        Product::factory()->create(['slug' => self::SLUG, 'faqs' => null]);
        $this->migration()->up();

        $html = $this->get('/thiet-bi/'.self::SLUG)->assertOk()
            ->assertInertia(fn ($p) => $p->has('product.faqs', count($this->data()['products'][self::SLUG])))
            ->getContent();

        $this->assertStringContainsString('"FAQPage"', $html);
    }

    /** @test */
    public function rollback_removes_only_untouched_imports(): void
    {
        $kept = Product::factory()->create(['slug' => self::SLUG, 'faqs' => null]);
        $changed = Combo::factory()->create(['slug' => 'relax', 'faqs' => null]);
        $this->migration()->up();
        $changed->update(['faqs' => [['question' => 'Đã sửa?', 'answer' => 'Rồi.']]]);

        $this->migration()->down();

        $this->assertNull($kept->fresh()->faqs);
        $this->assertCount(1, $changed->fresh()->faqs);
    }
}
