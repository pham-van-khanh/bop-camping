<?php

namespace Tests\Feature;

use App\Models\CampingSpot;
use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AssertsSeoTitle;
use Tests\TestCase;

/**
 * bopcamping-y05c — landing page SEO địa phương:
 *   /thue-do-cam-trai/{slug}, /diem-cam-trai, /diem-cam-trai/{slug}.
 *
 * Theo SKILL seo: mỗi trang có SEO RIÊNG, schema có ở trang đúng VÀ vắng ở trang sai,
 * URL vào sitemap.
 */
class LocalLandingPagesTest extends TestCase
{
    use AssertsSeoTitle;
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    private Category $tents;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2]);
        $this->tents = Category::create(['name' => 'Lều cắm trại', 'slug' => 'leu-cam-trai']);
    }

    private function product(string $name, string $slug, array $locationIds): Product
    {
        $p = Product::create([
            'category_id' => $this->tents->id,
            'name' => $name,
            'slug' => $slug,
            'price_per_day' => 80000,
            'quantity' => 5,
            'status' => 'active',
        ]);
        foreach ($locationIds as $id) {
            $p->serviceLocations()->attach($id, ['quantity' => 5, 'buffer_days' => 0]);
        }

        return $p;
    }

    private function combo(string $name, string $slug, Product $item, array $locationIds): Combo
    {
        $c = Combo::create(['name' => $name, 'slug' => $slug, 'combo_price' => 300000, 'is_active' => true, 'sort_order' => 1]);
        ComboItem::create(['combo_id' => $c->id, 'product_id' => $item->id, 'quantity' => 1]);
        $c->serviceLocations()->sync($locationIds);

        return $c;
    }

    private function spot(array $attrs = []): CampingSpot
    {
        return CampingSpot::create(array_merge([
            'name' => 'Hồ Đồng Mô',
            'province' => 'Hà Nội',
            'district' => 'Ba Vì',
            'terrain_tag' => 'Ven hồ',
            'description' => 'Hồ rộng, đồi thông ven hồ, đi xe máy khoảng một tiếng từ trung tâm.',
            'map_url' => 'https://maps.google.com/?q=ho-dong-mo',
            'best_season_from' => 10,
            'best_season_to' => 4,
            'nearest_service_location_id' => $this->hanoi->id,
        ], $attrs));
    }

    /** @return array<int, string> @type của mọi khối JSON-LD trong HTML thô */
    private function schemaTypes(string $html): array
    {
        preg_match_all('#application/ld\+json">(.*?)</script>#s', $html, $m);
        $types = [];
        foreach ($m[1] as $json) {
            $d = json_decode(html_entity_decode($json), true);
            foreach (array_is_list($d) ? $d : [$d] as $block) {
                $types[] = $block['@type'] ?? null;
            }
        }

        return $types;
    }

    /* ---------------- Slug ---------------- */

    /** @test */
    public function slug_is_auto_generated_and_kept_when_name_changes(): void
    {
        $this->assertSame('vinh', $this->vinh->slug);
        $this->assertSame('ha-noi', $this->hanoi->slug);

        // Đổi tên KHÔNG đổi URL đã index.
        $this->vinh->update(['name' => 'Kho Vinh']);
        $this->assertSame('vinh', $this->vinh->fresh()->slug);

        // Trùng tên -> slug duy nhất.
        $a = $this->spot();
        $b = $this->spot(['province' => 'Nghệ An']);
        $this->assertSame('ho-dong-mo', $a->slug);
        $this->assertNotSame($a->slug, $b->slug);

        // Slug chỉ toàn ký tự lạ -> chuẩn hoá ra rỗng -> sinh lại từ tên, không lưu slug rỗng.
        $c = $this->spot(['name' => 'Bãi Đá', 'slug' => '---']);
        $this->assertSame('bai-da', $c->slug);
    }

    /** @test */
    public function migration_backfills_slugs_for_existing_rows(): void
    {
        $this->assertTrue(Schema::hasColumn('service_locations', 'slug'));
        $this->assertTrue(Schema::hasColumn('camping_spots', 'slug'));

        // Dòng cũ không có slug (chèn thẳng, né model) -> chạy lại backfill của migration.
        DB::table('camping_spots')->insert(['name' => 'Núi Quyết', 'province' => 'Nghệ An', 'terrain_tag' => 'Đồi', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('camping_spots')->insert(['name' => 'Núi Quyết', 'province' => 'Nghệ An', 'terrain_tag' => 'Đồi', 'created_at' => now(), 'updated_at' => now()]);

        $migration = require database_path('migrations/2026_10_10_000002_add_slug_to_camping_spots.php');
        $migration->down();
        $migration->up();

        $this->assertSame(['nui-quyet', 'nui-quyet-1'], DB::table('camping_spots')->orderBy('id')->pluck('slug')->all());
    }

    /** ?vi-tri= vẫn dùng chung slug (trước đây là accessor Str::slug(name)) — không vỡ link cũ. */
    /** @test */
    public function product_listing_location_filter_still_resolves_by_slug(): void
    {
        $this->product('Lều Vinh', 'leu-vinh', [$this->vinh->id]);
        $this->product('Lều HN', 'leu-hn', [$this->hanoi->id]);

        $this->get('/thiet-bi?vi-tri=ha-noi')->assertInertia(fn ($p) => $p
            ->where('filters.vi_tri', 'ha-noi')
            ->has('products', 1)
            ->where('products.0.slug', 'leu-hn'));
    }

    /** @test */
    public function admin_can_set_slug_and_intro_and_slug_is_validated(): void
    {
        $admin = User::create(['name' => 'Admin', 'phone' => '0976544370', 'email' => 'admin@bop.test', 'is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.service-locations.update', $this->vinh), [
            'name' => 'Vinh', 'slug' => 'tp-vinh', 'intro' => 'Kho ở trung tâm TP Vinh.', 'status' => 'open',
        ])->assertSessionHasNoErrors();
        $this->assertSame('tp-vinh', $this->vinh->fresh()->slug);
        $this->assertSame('Kho ở trung tâm TP Vinh.', $this->vinh->fresh()->intro);

        $this->actingAs($admin)->put(route('admin.service-locations.update', $this->hanoi), [
            'name' => 'Hà Nội', 'slug' => 'tp-vinh', 'status' => 'open',
        ])->assertSessionHasErrors('slug');

        $spot = $this->spot();
        $this->actingAs($admin)->put(route('admin.camping-spots.update', $spot), [
            'name' => $spot->name, 'slug' => 'Có Dấu!', 'province' => 'Hà Nội', 'terrain_tag' => 'Ven hồ',
        ])->assertSessionHasErrors('slug');
    }

    /* ---------------- Trang khu vực ---------------- */

    /** @test */
    public function area_page_renders_with_own_seo_products_combos_and_spots(): void
    {
        $tent = $this->product('Lều 4 người', 'leu-4-nguoi', [$this->hanoi->id]);
        $this->product('Lều chỉ ở Vinh', 'leu-chi-vinh', [$this->vinh->id]);
        $this->combo('Combo cặp đôi', 'combo-cap-doi', $tent, [$this->hanoi->id]);
        $this->spot();

        $this->get('/thue-do-cam-trai/ha-noi')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('AreaLanding')
                ->where('seo.title', 'Thuê đồ cắm trại tại Hà Nội theo ngày | BỐP CAMPING')
                ->where('seo.url', url('/thue-do-cam-trai/ha-noi'))
                ->where('location.slug', 'ha-noi')
                ->where('product_count', 1)
                ->has('products', 1)
                ->where('products.0.slug', 'leu-4-nguoi')
                ->has('combos', 1)
                ->has('spots', 1)
                ->where('spots.0.slug', 'ho-dong-mo')
                ->where('other_locations.0.slug', 'vinh')
                ->where('seo.jsonld.0.@type', 'BreadcrumbList'));
    }

    /** @test */
    public function area_titles_and_descriptions_are_unique_and_well_sized(): void
    {
        $this->product('Lều 4 người', 'leu-4-nguoi', [$this->vinh->id, $this->hanoi->id]);

        $seo = [];
        foreach (['vinh', 'ha-noi'] as $slug) {
            $this->get('/thue-do-cam-trai/'.$slug)->assertInertia(function ($p) use (&$seo, $slug) {
                $seo[$slug] = $p->toArray()['props']['seo'];
            });
        }

        $this->assertNotSame($seo['vinh']['title'], $seo['ha-noi']['title']);
        $this->assertNotSame($seo['vinh']['description'], $seo['ha-noi']['description']);
        foreach ($seo as $s) {
            $this->assertSame(1, substr_count($s['title'], 'BỐP CAMPING'), 'brand đúng 1 lần');
            $this->assertLessThanOrEqual(62, mb_strlen($s['title']));
            $this->assertGreaterThanOrEqual(120, mb_strlen($s['description']));
            $this->assertLessThanOrEqual(160, mb_strlen($s['description']));
            $this->assertStringStartsWith('http', $s['image']);
        }
    }

    /** Intro admin viết đè fallback; đủ dài thì thành meta description. @test */
    public function admin_intro_overrides_auto_intro(): void
    {
        $this->get('/thue-do-cam-trai/vinh')->assertInertia(fn ($p) => $p
            ->where('intro', fn ($v) => str_contains($v, 'kho đồ cắm trại tại Vinh (Nghệ An)')));

        $intro = str_repeat('Kho Bốp ở Vinh giao đồ cắm trại tận nơi nội thành và Cửa Lò. ', 3);
        $this->vinh->update(['intro' => $intro]);

        $this->get('/thue-do-cam-trai/vinh')->assertInertia(fn ($p) => $p
            ->where('intro', $intro)
            ->where('seo.description', fn ($d) => str_starts_with($d, 'Kho Bốp ở Vinh')));
    }

    /** @test */
    public function area_page_declares_local_business_for_that_location_only(): void
    {
        SiteSetting::current()->update(['hotline_primary' => '0976544370', 'working_hours' => '8:00 – 21:00']);

        $html = $this->get('/thue-do-cam-trai/ha-noi')->getContent();
        $this->assertContains('LocalBusiness', $this->schemaTypes($html));
        $this->assertStringContainsString('"name":"BỐP CAMPING - Hà Nội"', $html);
        $this->assertStringContainsString('"@id":"'.url('/thue-do-cam-trai/ha-noi').'#store"', $html);
        // Không bịa địa chỉ khi admin chưa điền.
        $this->assertStringNotContainsString('"streetAddress"', $html);
        $this->assertNotContains('TouristAttraction', $this->schemaTypes($html));

        // LocalBusiness riêng của Hà Nội KHÔNG lọt sang trang chủ / trang khu vực khác.
        $this->assertStringNotContainsString('"name":"BỐP CAMPING - Hà Nội"', $this->get('/')->getContent());
        $this->assertStringNotContainsString('"name":"BỐP CAMPING - Hà Nội"', $this->get('/thue-do-cam-trai/vinh')->getContent());
    }

    /** @test */
    public function site_wide_local_business_points_to_area_page_once_address_filled(): void
    {
        SiteSetting::current()->update(['hotline_primary' => '0976544370']);
        $this->vinh->update(['address' => '12 Lê Duẩn']);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('"url":"'.url('/thue-do-cam-trai/vinh').'"', $html);
        $this->assertStringContainsString('"streetAddress":"12 Lê Duẩn"', $html);
    }

    /** @test */
    public function unknown_or_coming_area_returns_404(): void
    {
        ServiceLocation::create(['name' => 'Đà Nẵng', 'status' => 'coming', 'sort_order' => 3]);

        $this->get('/thue-do-cam-trai/khong-ton-tai')->assertNotFound();
        $this->get('/thue-do-cam-trai/da-nang')->assertNotFound();
    }

    /* ---------------- Trang điểm cắm trại ---------------- */

    /** @test */
    public function spot_page_renders_with_tourist_attraction_and_breadcrumb(): void
    {
        $tent = $this->product('Lều 4 người', 'leu-4-nguoi', [$this->hanoi->id]);
        $this->combo('Combo HN', 'combo-hn', $tent, [$this->hanoi->id]);
        $this->combo('Combo Vinh', 'combo-vinh', $tent, [$this->vinh->id]);
        $spot = $this->spot();
        $this->spot(['name' => 'Núi Hàm Lợn', 'district' => 'Sóc Sơn', 'terrain_tag' => 'Rừng núi']);

        $this->get('/diem-cam-trai/'.$spot->slug)
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('CampingSpotDetail')
                ->where('spot.name', 'Hồ Đồng Mô')
                ->where('store.slug', 'ha-noi')
                // Gợi ý đồ = combo có sẵn ở kho gần nhất, không lẫn combo kho khác.
                ->has('combos', 1)
                ->where('combos.0.slug', 'combo-hn')
                ->has('nearby', 1)
                ->where('nearby.0.slug', 'nui-ham-lon')
                ->where('seo.title', 'Cắm trại ở Hồ Đồng Mô, Hà Nội: mùa đẹp, đồ cần mang | BỐP CAMPING')
                ->where('seo.jsonld.0.@type', 'BreadcrumbList')
                ->where('seo.jsonld.0.itemListElement.1.item', url('/diem-cam-trai'))
                ->where('seo.jsonld.1.@type', 'TouristAttraction')
                ->where('seo.jsonld.1.address.addressRegion', 'Hà Nội')
                ->where('seo.jsonld.1.hasMap', 'https://maps.google.com/?q=ho-dong-mo')
                // Không có toạ độ trong DB -> không khai geo.
                ->missing('seo.jsonld.1.geo'));
    }

    /** @test */
    public function spot_descriptions_are_unique_and_well_sized_even_when_admin_text_is_short(): void
    {
        $a = $this->spot(['description' => null]);
        $b = $this->spot(['name' => 'Biển Cửa Lò', 'province' => 'Nghệ An', 'district' => 'Cửa Lò', 'terrain_tag' => 'Bãi biển', 'description' => 'Biển đẹp.', 'nearest_service_location_id' => null]);

        $seo = [];
        foreach ([$a, $b] as $s) {
            $this->get('/diem-cam-trai/'.$s->slug)->assertOk()->assertInertia(function ($p) use (&$seo) {
                $seo[] = $p->toArray()['props']['seo'];
            });
        }

        $this->assertNotSame($seo[0]['title'], $seo[1]['title']);
        $this->assertNotSame($seo[0]['description'], $seo[1]['description']);
        foreach ($seo as $s) {
            $this->assertSame(1, substr_count($s['title'], 'BỐP CAMPING'));
            $this->assertLessThanOrEqual(65, mb_strlen($s['title']));
            $this->assertGreaterThanOrEqual(120, mb_strlen($s['description']));
            $this->assertLessThanOrEqual(160, mb_strlen($s['description']));
        }
    }

    /** @test */
    public function tourist_attraction_only_appears_on_spot_pages(): void
    {
        $spot = $this->spot();
        $this->product('Lều 4 người', 'leu-4-nguoi', [$this->hanoi->id]);

        $this->assertContains('TouristAttraction', $this->schemaTypes($this->get('/diem-cam-trai/'.$spot->slug)->getContent()));

        foreach (['/', '/thiet-bi/leu-4-nguoi', '/thue-do-cam-trai/ha-noi', '/diem-cam-trai'] as $url) {
            $this->assertNotContains('TouristAttraction', $this->schemaTypes($this->get($url)->getContent()), $url);
        }
    }

    /** @test */
    public function spot_index_lists_spots_grouped_by_province(): void
    {
        $this->spot();
        $this->spot(['name' => 'Núi Quyết', 'province' => 'Nghệ An', 'district' => 'Vinh', 'nearest_service_location_id' => $this->vinh->id]);

        $this->get('/diem-cam-trai')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('CampingSpotIndex')
                ->where('seo.title', 'Điểm cắm trại đẹp theo tỉnh thành | BỐP CAMPING')
                ->where('seo.description', fn ($d) => mb_strlen($d) >= 120 && mb_strlen($d) <= 160)
                ->where('seo.jsonld.@type', 'BreadcrumbList')
                ->has('provinces', 2)
                ->has('locations', 2));
    }

    /** @test */
    public function server_title_is_seo_title_with_brand_once_on_area_and_spot_pages(): void
    {
        $spot = $this->spot();

        $this->assertSame(
            'Thuê đồ cắm trại tại Vinh theo ngày | BỐP CAMPING',
            $this->assertSeoTitleSingleSource($this->get('/thue-do-cam-trai/vinh')),
        );
        $this->assertSame(
            'Điểm cắm trại đẹp theo tỉnh thành | BỐP CAMPING',
            $this->assertSeoTitleSingleSource($this->get('/diem-cam-trai')),
        );
        $this->assertSeoTitleSingleSource($this->get('/diem-cam-trai/'.$spot->slug));
    }

    /** @test */
    public function unknown_spot_returns_404(): void
    {
        $this->get('/diem-cam-trai/khong-co')->assertNotFound();
    }

    /* ---------------- Liên kết nội bộ + sitemap ---------------- */

    /** @test */
    public function homepage_spot_data_carries_slug_for_links(): void
    {
        $this->spot(['is_suggested' => true]);

        $this->get('/')->assertInertia(fn ($p) => $p
            ->where('suggested_spots.0.slug', 'ho-dong-mo')
            ->where('camping_provinces.0.spots.0.slug', 'ho-dong-mo')
            ->where('site.addresses.0.slug', 'vinh'));
    }

    /** @test */
    public function sitemap_lists_area_and_spot_pages_with_lastmod(): void
    {
        ServiceLocation::create(['name' => 'Đà Nẵng', 'status' => 'coming', 'sort_order' => 3]);
        $this->product('Lều 4 người', 'leu-4-nguoi', [$this->hanoi->id]);
        $spot = $this->spot(['description' => str_repeat('Hồ rộng, đồi thông ven hồ, nước trong. ', 8)]);
        // Điểm mỏng (1 câu, không ảnh) mang noindex -> KHÔNG được khai trong sitemap.
        $thin = $this->spot(['name' => 'Bãi Mỏng', 'description' => 'Đẹp.']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);

        $lastmods = [];
        foreach ($doc->url as $u) {
            $lastmods[(string) $u->loc] = (string) $u->lastmod;
        }

        foreach ([url('/thue-do-cam-trai/vinh'), url('/thue-do-cam-trai/ha-noi'), url('/diem-cam-trai'), url('/diem-cam-trai/'.$spot->slug)] as $loc) {
            $this->assertArrayHasKey($loc, $lastmods);
            $this->assertNotSame('', $lastmods[$loc], $loc.' thiếu lastmod');
        }
        // Cơ sở sắp mở trả 404 -> không được khai.
        $this->assertArrayNotHasKey(url('/thue-do-cam-trai/da-nang'), $lastmods);
        $this->assertArrayNotHasKey(url('/diem-cam-trai/'.$thin->slug), $lastmods);
    }

    /* ---------------- Thin content ---------------- */

    /** @test */
    public function thin_spot_page_is_noindex_but_rich_spot_page_is_indexable(): void
    {
        $thin = $this->spot(['description' => 'Hồ đẹp, gần Hà Nội.']);
        $rich = $this->spot(['name' => 'Núi Hàm Lợn', 'description' => str_repeat('Rừng thông, đồi cỏ, view hồ. ', 10)]);

        $this->get('/diem-cam-trai/'.$thin->slug)
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('seo.robots', 'noindex, follow'))
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get('/diem-cam-trai/'.$rich->slug)
            ->assertOk()
            ->assertInertia(fn ($p) => $p->missing('seo.robots'))
            ->assertSee('<meta name="robots" content="index, follow">', false);

        // Ảnh cũng là nội dung: mô tả ngắn nhưng có ảnh -> không coi là mỏng.
        $thin->media()->create(['type' => 'image', 'path' => 'admin/camping-spots/a.jpg', 'sort_order' => 1]);
        $this->get('/diem-cam-trai/'.$thin->slug)->assertInertia(fn ($p) => $p->missing('seo.robots'));

        // Trang khác không bị dính noindex.
        $this->get('/diem-cam-trai')->assertSee('<meta name="robots" content="index, follow">', false);
    }

    /* ---------------- XSS qua JSON-LD ---------------- */

    /**
     * Tên/mô tả do admin nhập đi thẳng vào <script type="application/ld+json">. Thiếu
     * JSON_HEX_TAG thì "</script>" trong dữ liệu đóng thẻ sớm và phần sau chạy như JS.
     */
    /** @test */
    public function json_ld_cannot_break_out_of_script_tag(): void
    {
        $payload = '</script><script>alert(1)</script>';
        SiteSetting::current()->update(['hotline_primary' => '0900000000']);
        $this->hanoi->update(['address' => '1 Phố Huế', 'intro' => 'Kho '.$payload]);
        $this->hanoi->update(['name' => 'Hà Nội '.$payload]);
        $spot = $this->spot(['name' => 'Hồ '.$payload, 'description' => 'Mô tả '.$payload]);

        foreach (['/diem-cam-trai/'.$spot->slug, '/thue-do-cam-trai/'.$this->hanoi->slug, '/'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<script>alert(1)', $html, $url);
            // JSON-LD vẫn là JSON hợp lệ, giải mã ra đúng chuỗi gốc.
            $this->assertNotEmpty($this->schemaTypes($html), $url);
        }
    }
}
