<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Support\FaqRows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-0345 — FAQ riêng cho từng sản phẩm / combo: admin lưu, trang chi tiết hiện,
 * và FAQPage JSON-LD sinh từ CÙNG dữ liệu (không có FAQ thì không có schema).
 */
class ItemFaqTest extends TestCase
{
    use RefreshDatabase;

    private const FAQS = [
        ['question' => 'Lều này ngủ được mấy người?', 'answer' => '2–3 người lớn kèm đồ.'],
        ['question' => 'Chở xe máy được không?', 'answer' => 'Được, túi dài khoảng 98cm.'],
    ];

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function product(array $attrs = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create($attrs + [
            'category_id' => $category->id,
            'name' => 'Lều 4 người',
            'slug' => 'leu-4-nguoi',
            'price_per_day' => 50000,
            'quantity' => 5,
        ]);
    }

    private function productPayload(Product $p): array
    {
        $loc = ServiceLocation::firstOrCreate(
            ['name' => 'Vinh'],
            ['area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1],
        );

        return [
            'name' => $p->name,
            'category_id' => $p->category_id,
            'price_per_day' => 50000,
            'quantity' => 5,
            'status' => 'active',
            'service_location_ids' => [$loc->id],
        ];
    }

    private function combo(array $attrs = []): Combo
    {
        $combo = Combo::factory()->create($attrs + ['name' => 'Relax', 'slug' => 'relax']);
        $combo->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        return $combo;
    }

    /** @return list<string> các @type trong mọi khối JSON-LD của trang */
    private function jsonLdTypes(string $html): array
    {
        preg_match_all('#application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        $types = [];
        foreach ($m[1] as $block) {
            $data = json_decode($block, true);
            foreach (array_is_list($data) ? $data : [$data] as $node) {
                $types[] = $node['@type'] ?? null;
            }
        }

        return $types;
    }

    // ── Admin lưu ────────────────────────────────────────────────────────────

    /** @test */
    public function admin_saves_trims_and_clears_product_faqs(): void
    {
        $p = $this->product();

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->productPayload($p) + [
                'faqs' => [['question' => '  Ngủ mấy người? ', 'answer' => " 2–3 người.\n"]],
            ])
            ->assertSessionHasNoErrors();
        // assertEquals: MySQL JSON sắp lại thứ tự khoá.
        $this->assertEquals([['question' => 'Ngủ mấy người?', 'answer' => '2–3 người.']], $p->fresh()->faqs);

        // Form không gửi khoá faqs (vd màn khác cập nhật) → giữ nguyên.
        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->productPayload($p))
            ->assertSessionHasNoErrors();
        $this->assertCount(1, $p->fresh()->faqs);

        // FormData gửi '' khi admin xoá hết → null.
        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->productPayload($p) + ['faqs' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($p->fresh()->faqs);
    }

    /** @test */
    public function incomplete_or_too_many_faq_rows_are_rejected(): void
    {
        $p = $this->product();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $p), $this->productPayload($p) + [
                'faqs' => [['question' => 'Có câu hỏi', 'answer' => '']],
            ])
            ->assertSessionHasErrors('faqs.0.answer');

        $this->actingAs($admin)
            ->put(route('admin.products.update', $p), $this->productPayload($p) + [
                'faqs' => array_fill(0, FaqRows::MAX + 1, self::FAQS[0]),
            ])
            ->assertSessionHasErrors('faqs');

        $this->assertNull($p->fresh()->faqs);
    }

    // ── Trang khách ──────────────────────────────────────────────────────────

    /** @test */
    public function product_page_shows_faqs_and_emits_matching_faq_schema(): void
    {
        $this->product(['faqs' => self::FAQS]);

        $res = $this->get('/thiet-bi/leu-4-nguoi')->assertOk()
            ->assertInertia(fn ($p) => $p->where('product.faqs', self::FAQS));

        $html = $res->getContent();
        $this->assertContains('FAQPage', $this->jsonLdTypes($html));
        // Schema khai ĐÚNG các câu đang hiện trên trang.
        foreach (self::FAQS as $row) {
            $this->assertStringContainsString(json_encode($row['question'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $html);
        }
        // Product + Breadcrumb vẫn còn nguyên.
        $this->assertContains('Product', $this->jsonLdTypes($html));
        $this->assertContains('BreadcrumbList', $this->jsonLdTypes($html));
    }

    /** @test */
    public function product_without_faqs_has_no_faq_schema(): void
    {
        $this->product();

        $res = $this->get('/thiet-bi/leu-4-nguoi')->assertOk()
            ->assertInertia(fn ($p) => $p->where('product.faqs', []));

        $this->assertNotContains('FAQPage', $this->jsonLdTypes($res->getContent()));
    }

    /** @test */
    public function combo_page_shows_faqs_and_schema_only_when_it_has_some(): void
    {
        $this->combo(['faqs' => self::FAQS]);
        $this->combo(['name' => 'BBQ', 'slug' => 'bbq']);

        $with = $this->get('/combos/relax')->assertOk()
            ->assertInertia(fn ($p) => $p->where('combo.faqs', self::FAQS));
        $this->assertContains('FAQPage', $this->jsonLdTypes($with->getContent()));

        $without = $this->get('/combos/bbq')->assertOk()
            ->assertInertia(fn ($p) => $p->where('combo.faqs', []));
        $this->assertNotContains('FAQPage', $this->jsonLdTypes($without->getContent()));
    }

    /** @test */
    public function listing_pages_do_not_carry_faq_payload(): void
    {
        $this->product(['faqs' => self::FAQS]);
        $this->combo(['faqs' => self::FAQS]);

        $this->get('/thiet-bi')->assertInertia(fn ($p) => $p->missing('products.0.faqs'));
        $this->get('/combos')->assertInertia(fn ($p) => $p->missing('combos.0.faqs'));
    }
}
