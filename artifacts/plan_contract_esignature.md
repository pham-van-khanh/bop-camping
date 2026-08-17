# Hệ thống hợp đồng thuê điện tử — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Số hoá Hợp đồng thuê thiết bị camping số 1408/HĐTTB (hợp đồng chính + Phụ lục A bàn giao + Phụ lục B nhận lại) để khách ký online qua một link, bỏ hẳn in giấy.

**Architecture:** Một đơn = một `Contract` = một token = một link. Ba giai đoạn ký (`main` → `handover` → `return`) lưu trong `contract_signatures`, mỗi giai đoạn đóng băng HTML + SHA-256 riêng. Toàn bộ render/ký/sinh PDF tập trung trong `ContractService` — single source of truth. PDF sinh bằng dompdf, lưu trên disk `media`, gửi mail cho khách sau mỗi lần ký.

**Tech Stack:** Laravel 12 · Inertia + React + TypeScript · `barryvdh/laravel-dompdf` · `signature_pad` · MySQL 8 (dev qua Docker port 3307) · PHPUnit + Vitest.

**Spec:** [design_spec_contract_esignature.md](design_spec_contract_esignature.md) · **ADR:** [adr_contract_esignature.md](adr_contract_esignature.md) · **Bead:** bopcamping-4jao

## Global Constraints

- **Nhánh:** làm trên `feature/contract-esignature` (đã tạo từ `feat/scaffold-laravel`). KHÔNG commit thẳng vào `feat/scaffold-laravel` / `develop` / `main`.
- **Quality gate trước MỖI commit** — tất cả phải pass: `php artisan test` · `npm test` · `npx tsc --noEmit` · `npm run lint` · `./vendor/bin/pint --test` · `npm run build`.
- `npm run lint` là read-only. Lỗi format thì chạy `npm run lint:fix` rồi **xem lại diff**. TUYỆT ĐỐI không thêm `--fix` vào script `lint`.
- **PHP 8.3.8** (pin ở `composer.json` `config.platform`). KHÔNG gỡ pin.
- Test phải **collation-safe** — chạy đúng trên cả sqlite lẫn MySQL `utf8mb4_unicode_ci`.
- **Không dùng `any`** trong TypeScript. Biến buộc phải giữ mà không dùng thì đặt tên bắt đầu bằng `_`.
- **Số CCCD và đường dẫn ảnh CCCD KHÔNG BAO GIỜ** xuất hiện trong prop Inertia của trang khách, và không ghi log.
- Ảnh đi qua `MediaVariantService` — KHÔNG resize ở chỗ khác. Ảnh CCCD **không** sinh biến thể public.
- Mail là `ShouldQueue` → cần `php artisan queue:work` (hoặc `composer run dev`) mới gửi thật.
- Tiếng Việt trong toàn bộ chuỗi hiển thị và comment, khớp giọng văn sẵn có của repo.

---

## File Structure

**Tạo mới**

| File | Trách nhiệm |
|---|---|
| `database/migrations/2026_08_17_000001_create_contracts_table.php` | Bảng `contracts` |
| `database/migrations/2026_08_17_000002_create_contract_signatures_table.php` | Bảng `contract_signatures` |
| `database/migrations/2026_08_17_000003_create_contract_items_table.php` | Bảng `contract_items` |
| `database/migrations/2026_08_17_000004_create_handover_photos_table.php` | Bảng `handover_photos` |
| `database/migrations/2026_08_17_000005_add_contract_fields_to_products_table.php` | `replacement_value`, `accessories` |
| `database/migrations/2026_08_17_000006_add_contract_templates_to_site_settings_table.php` | 3 cột mẫu |
| `app/Models/Contract.php` · `ContractSignature.php` · `ContractItem.php` · `HandoverPhoto.php` | Eloquent |
| `app/Services/ContractService.php` | **Single source of truth** — dựng, render, ký, sinh PDF |
| `app/Services/ContractPdf.php` | Chỉ việc dompdf (tách khỏi ContractService để test font riêng) |
| `app/Http/Controllers/Shop/ContractController.php` | Trang ký của khách |
| `app/Http/Controllers/Admin/AdminContractController.php` | Tạo hợp đồng, nhập CCCD, ảnh |
| `app/Http/Controllers/Admin/AdminContractTemplateController.php` | 3 mẫu |
| `app/Mail/ContractSignedMail.php` | Mail kèm PDF |
| `app/Console/Commands/PurgeContractIdentity.php` | Xoá CCCD + ảnh |
| `resources/views/pdf/contract.blade.php` | Layout PDF (table + inline style) |
| `resources/views/emails/contract_signed.blade.php` | Nội dung mail |
| `resources/js/Pages/Contract.tsx` | Trang ký (cả 3 giai đoạn) |
| `resources/js/Components/SignaturePadField.tsx` | Canvas ký |
| `resources/js/Pages/Admin/ContractTemplates.tsx` | Soạn 3 mẫu |
| `tests/Feature/Contract*.php` · `tests/js/SignaturePadField.test.tsx` | Test |

**Sửa**

| File | Sửa gì |
|---|---|
| `.claude/rules/tech-strategy.md` | Thêm lại dòng PDF + `signature_pad` vào golden path |
| `composer.json` · `package.json` | Dependency |
| `config/dompdf.php` | `defaultFont` = DejaVu Sans |
| `routes/web.php` | Route khách + admin |
| `routes/console.php` | Lịch chạy `contracts:purge-identity` |
| `app/Models/Product.php` | `replacement_value`, `accessories` vào `$fillable`/`$casts` |
| `app/Models/SiteSetting.php` | 3 cột mẫu vào `$guarded` (đã là `[]`, chỉ cần default) |
| `app/Models/Order.php` | Quan hệ `contract()` |
| `app/Http/Controllers/Admin/AdminOrderController.php` | Prop hợp đồng cho trang chi tiết đơn |
| `resources/js/Pages/Admin/OrderShow.tsx` | Khối hợp đồng |

---

### Task 1: Dependency + cấu hình font tiếng Việt

Lý do làm trước và tách riêng: font hỏng là **lỗi im lặng** (chữ ra ô vuông). Phải chốt và có test bảo vệ trước khi ai đó viết layout PDF.

**Files:**
- Modify: `composer.json`, `package.json`, `.claude/rules/tech-strategy.md`
- Create: `config/dompdf.php`, `tests/Feature/ContractPdfFontTest.php`, `app/Services/ContractPdf.php`

**Interfaces:**
- Produces: `App\Services\ContractPdf::render(string $html): string` — trả **binary PDF**.

- [ ] **Step 1: Cài dependency**

```bash
composer require barryvdh/laravel-dompdf
npm install signature_pad
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

- [ ] **Step 2: Đặt font mặc định có dấu tiếng Việt**

Sửa `config/dompdf.php`, trong mảng `options`:

```php
'defaultFont' => 'DejaVu Sans',
```

- [ ] **Step 3: Viết test thất bại**

Tạo `tests/Feature/ContractPdfFontTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\ContractPdf;
use Tests\TestCase;

/**
 * bopcamping-4jao — chặn lỗi font dompdf, vốn là LỖI IM LẶNG: thiếu font có dấu thì
 * chữ tiếng Việt ra ô vuông mà không ném exception nào.
 *
 * Test này khẳng định PDF có NHÚNG DejaVu Sans (font có đủ dấu tiếng Việt). Cố ý
 * KHÔNG bóc text để so chuỗi: lớp text của PDF vẫn giữ đúng ký tự Unicode kể cả khi
 * glyph không vẽ được, nên so text sẽ PASS ngay cả lúc file hỏng về mặt nhìn.
 */
class ContractPdfFontTest extends TestCase
{
    /** @test */
    public function pdf_nhung_font_co_dau_tieng_viet(): void
    {
        $html = '<html><body><p>Lều Village 6.0 — bồi thường 100% giá trị thiết bị</p></body></html>';

        $pdf = app(ContractPdf::class)->render($html);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('DejaVuSans', $pdf, 'PDF không nhúng DejaVu Sans — chữ có dấu sẽ ra ô vuông.');
    }
}
```

- [ ] **Step 4: Chạy test, xác nhận FAIL**

Run: `php artisan test --filter=ContractPdfFontTest`
Expected: FAIL — `Class "App\Services\ContractPdf" does not exist`.

- [ ] **Step 5: Viết implementation tối thiểu**

Tạo `app/Services/ContractPdf.php`:

```php
<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Bọc dompdf — CHỖ DUY NHẤT dự án gọi thư viện PDF.
 *
 * Tách khỏi ContractService để test font đứng độc lập, và để sau này muốn ký số bản PDF
 * bằng chứng thư CA Việt Nam thì chỉ phải sửa đúng file này (xem adr_contract_esignature
 * mục 3.5).
 */
class ContractPdf
{
    public function render(string $html): string
    {
        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }
}
```

- [ ] **Step 6: Chạy test, xác nhận PASS**

Run: `php artisan test --filter=ContractPdfFontTest`
Expected: PASS (2 assertions).

- [ ] **Step 7: Cập nhật golden path**

Trong `.claude/rules/tech-strategy.md`, bảng **Data**, thêm sau dòng "Resize ảnh":

```markdown
| Sinh PDF (hợp đồng) | **barryvdh/laravel-dompdf** | Single source = `app/Services/ContractPdf.php`. Font `DejaVu Sans` để có dấu tiếng Việt — lỗi font là LỖI IM LẶNG nên có `ContractPdfFontTest` canh. Xem `artifacts/adr_contract_esignature.md`. |
```

Bảng **Golden Path**, thêm sau dòng "Component library":

```markdown
| Vẽ chữ ký tay | **signature_pad** | Canvas ký hợp đồng. ~4KB, xử lý pointer events + DPI đa thiết bị. |
```

- [ ] **Step 8: Quality gate + commit**

```bash
php artisan test && npx tsc --noEmit && npm run lint && ./vendor/bin/pint --test && npm run build
git add composer.json composer.lock package.json package-lock.json config/dompdf.php app/Services/ContractPdf.php tests/Feature/ContractPdfFontTest.php .claude/rules/tech-strategy.md
git commit -m "feat(contract): dompdf + signature_pad, chốt font DejaVu Sans có dấu (bopcamping-4jao)"
```

---

### Task 2: Schema + model

**Files:**
- Create: 6 migration (tên đầy đủ ở File Structure), `app/Models/Contract.php`, `ContractSignature.php`, `ContractItem.php`, `HandoverPhoto.php`
- Modify: `app/Models/Product.php`, `app/Models/Order.php`
- Test: `tests/Feature/ContractModelTest.php`

**Interfaces:**
- Consumes: không có.
- Produces:
  - `Contract::STAGES = ['main', 'handover', 'return']`
  - `Contract::signatureFor(string $stage): ?ContractSignature`
  - `Contract::isSigned(string $stage): bool`
  - `Contract::nextStage(): ?string` — giai đoạn kế tiếp chưa ký, `null` nếu xong cả ba
  - `Order::contract(): HasOne`

- [ ] **Step 1: Viết migration `contracts`**

`database/migrations/2026_08_17_000001_create_contracts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-4jao — hợp đồng thuê điện tử, MỘT đơn một hợp đồng.
 *
 * Đơn CHA (is_parent) không có hợp đồng: nó chỉ gom đợt, không có ngày/đồ riêng —
 * hợp đồng bám vào đơn con. Ràng buộc đó nằm ở ContractService::createFor(), không
 * ép được ở tầng schema.
 *
 * CCCD: số mã hoá ở tầng ứng dụng (cast 'encrypted'), ảnh 2 mặt để riêng và KHÔNG
 * sinh biến thể public. Cả hai bị xoá bởi lệnh contracts:purge-identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('token', 64)->unique();

            // Danh tính bên thuê — admin nhập từ ảnh CCCD khách gửi qua Zalo.
            // signer_id_number là TEXT vì bản mã hoá dài hơn số gốc rất nhiều.
            $table->text('signer_id_number')->nullable();
            $table->date('signer_id_issued_on')->nullable();
            $table->string('signer_id_issued_place')->nullable();
            $table->string('id_front_path')->nullable();
            $table->string('id_back_path')->nullable();

            $table->string('pdf_path')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
```

- [ ] **Step 2: Viết migration `contract_signatures`**

`database/migrations/2026_08_17_000002_create_contract_signatures_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Một dòng cho MỖI giai đoạn ký (main / handover / return).
 *
 * Cố ý tách bảng thay vì nhân ba bộ cột trên contracts: ba giai đoạn có cùng cấu trúc
 * dấu vết, gộp lại thì truy vấn "ai ký gì lúc nào" viết một lần thay vì ba lần.
 *
 * content_html đóng băng LÚC KÝ (không phải lúc tạo) vì admin sửa mẫu được giữa chừng;
 * content_hash để đối chiếu bản khách đang đọc với bản khách bấm ký.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 16);
            $table->longText('content_html');
            $table->char('content_hash', 64);
            $table->string('signature_path');
            $table->timestamp('signed_at');
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_user_agent', 512)->nullable();
            $table->timestamps();

            // Mỗi giai đoạn ký ĐÚNG MỘT LẦN — chặn ở DB chứ không chỉ ở controller.
            $table->unique(['contract_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signatures');
    }
};
```

- [ ] **Step 3: Viết migration `contract_items`**

`database/migrations/2026_08_17_000003_create_contract_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ảnh chụp danh mục đồ tại thời điểm lập hợp đồng + tình trạng hai lượt (giao / trả).
 *
 * Đóng băng name/accessories/replacement_value: sản phẩm đổi giá hay đổi tên về sau
 * KHÔNG được làm thay đổi hợp đồng đã lập. product_id nullable vì sản phẩm có thể bị xoá.
 *
 * Ba giá trị của mỗi cột tình trạng lấy đúng từ checkbox của Phụ lục A và B trên hợp
 * đồng giấy 1408/HĐTTB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('combo_name')->nullable();
            $table->string('name');
            $table->text('accessories')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('replacement_value')->default(0);

            // Phụ lục A: Mới / Tốt / Có vết cũ
            $table->string('handover_condition', 16)->nullable();
            $table->string('handover_note', 500)->nullable();
            // Phụ lục B: Như lúc giao / Hao mòn thường / Hư hỏng
            $table->string('return_condition', 16)->nullable();
            $table->string('return_note', 500)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_items');
    }
};
```

- [ ] **Step 4: Viết migration `handover_photos`**

`database/migrations/2026_08_17_000004_create_handover_photos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ảnh chụp lúc giao / lúc thu đồ — bằng chứng THỰC HIỆN hợp đồng (adr mục 3.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handover_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // pickup | return
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_photos');
    }
};
```

- [ ] **Step 5: Viết migration cột mới trên `products`**

`database/migrations/2026_08_17_000005_add_contract_fields_to_products_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-4jao — GIÁ TRỊ ĐỀN BÙ, thứ hợp đồng đang thiếu.
 *
 * Điều 6.3 hợp đồng 1408/HĐTTB bắt đền "100% giá trị thiết bị theo bảng Điều 1", nhưng
 * bảng Điều 1 chỉ ghi "15-90% giá trị thiết bị" — KHÔNG có con số gốc nào để nhân tỷ lệ
 * vào. products cũng chỉ có `deposit` (tiền cọc), vốn là chuyện khác. Không có cột này
 * thì shop không có căn cứ trừ cọc khi khách làm mất đồ.
 *
 * Mặc định 0 = "chưa khai giá" — hợp đồng sẽ in "—" chứ KHÔNG in "0đ", tránh việc số 0
 * bị đọc thành "đền 0 đồng".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_value')->default(0)->after('deposit');
            $table->text('accessories')->nullable()->after('replacement_value');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['replacement_value', 'accessories']);
        });
    }
};
```

- [ ] **Step 6: Viết migration 3 cột mẫu trên `site_settings`**

`database/migrations/2026_08_17_000006_add_contract_templates_to_site_settings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ba mẫu văn bản: hợp đồng chính, Phụ lục A, Phụ lục B. Admin sửa ở trang Mẫu hợp đồng. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->longText('contract_template_html')->nullable();
            $table->longText('handover_template_html')->nullable();
            $table->longText('return_template_html')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['contract_template_html', 'handover_template_html', 'return_template_html']);
        });
    }
};
```

- [ ] **Step 7: Viết test thất bại cho model**

`tests/Feature/ContractModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeContract(): Contract
    {
        $order = Order::create([
            'code' => 'BOP-HD001',
            'customer_name' => 'Khách',
            'customer_phone' => '0912345678',
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 361000,
            'deposit_total' => 1500000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);

        return Contract::create([
            'order_id' => $order->id,
            'code' => '1408/HĐTTB',
            'token' => str_repeat('a', 64),
        ]);
    }

    /** @test */
    public function next_stage_di_tu_main_den_return_roi_het(): void
    {
        $c = $this->makeContract();
        $this->assertSame('main', $c->nextStage());

        $this->sign($c, 'main');
        $this->assertSame('handover', $c->fresh()->nextStage());

        $this->sign($c, 'handover');
        $this->assertSame('return', $c->fresh()->nextStage());

        $this->sign($c, 'return');
        $this->assertNull($c->fresh()->nextStage());
    }

    /** @test */
    public function so_cccd_duoc_ma_hoa_trong_db_nhung_doc_ra_van_dung(): void
    {
        $c = $this->makeContract();
        $c->update(['signer_id_number' => '040202015437']);

        $this->assertSame('040202015437', $c->fresh()->signer_id_number);
        $this->assertNotSame(
            '040202015437',
            \DB::table('contracts')->where('id', $c->id)->value('signer_id_number'),
            'Số CCCD phải được mã hoá ở tầng ứng dụng, không nằm thô trong DB.'
        );
    }

    private function sign(Contract $c, string $stage): void
    {
        $c->signatures()->create([
            'stage' => $stage,
            'content_html' => '<p>x</p>',
            'content_hash' => hash('sha256', '<p>x</p>'),
            'signature_path' => "contracts/{$stage}.png",
            'signed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 8: Chạy test, xác nhận FAIL**

Run: `php artisan migrate && php artisan test --filter=ContractModelTest`
Expected: FAIL — `Class "App\Models\Contract" does not exist`.

- [ ] **Step 9: Viết model `Contract`**

`app/Models/Contract.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hợp đồng thuê điện tử của một đơn (bopcamping-4jao) — bám hợp đồng giấy 1408/HĐTTB.
 */
class Contract extends Model
{
    /** Ba giai đoạn ký, ĐÚNG THỨ TỰ. Không đổi thứ tự — nextStage() dựa vào nó. */
    public const STAGES = ['main', 'handover', 'return'];

    public const STAGE_LABELS = [
        'main' => 'Hợp đồng',
        'handover' => 'Biên bản bàn giao',
        'return' => 'Biên bản nhận lại',
    ];

    protected $fillable = [
        'order_id', 'code', 'token',
        'signer_id_number', 'signer_id_issued_on', 'signer_id_issued_place',
        'id_front_path', 'id_back_path',
        'pdf_path', 'first_viewed_at',
    ];

    protected $casts = [
        // Mã hoá ở tầng ứng dụng: lộ DB cũng không đọc được số CCCD.
        'signer_id_number' => 'encrypted',
        'signer_id_issued_on' => 'date',
        'first_viewed_at' => 'datetime',
    ];

    /**
     * KHÔNG bao giờ để dữ liệu định danh rò ra prop Inertia của trang khách. Ẩn ở tầng
     * model là lớp chặn cuối — controller vẫn phải chọn field tường minh.
     */
    protected $hidden = ['signer_id_number', 'id_front_path', 'id_back_path', 'token'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ContractSignature::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class)->orderBy('sort_order');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HandoverPhoto::class);
    }

    public function signatureFor(string $stage): ?ContractSignature
    {
        return $this->signatures->firstWhere('stage', $stage);
    }

    public function isSigned(string $stage): bool
    {
        return $this->signatureFor($stage) !== null;
    }

    /** Giai đoạn kế tiếp cần ký — null khi đã ký đủ cả ba. */
    public function nextStage(): ?string
    {
        foreach (self::STAGES as $stage) {
            if (! $this->isSigned($stage)) {
                return $stage;
            }
        }

        return null;
    }

    /** 4 số cuối SĐT — cửa mở link (xem ContractController). */
    public function phoneLast4(): string
    {
        return substr(preg_replace('/\D/', '', (string) $this->order->customer_phone), -4);
    }
}
```

- [ ] **Step 10: Viết 3 model còn lại**

`app/Models/ContractSignature.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần ký của một giai đoạn — kèm nội dung đóng băng và dấu vết. */
class ContractSignature extends Model
{
    protected $fillable = [
        'contract_id', 'stage', 'content_html', 'content_hash',
        'signature_path', 'signed_at', 'signed_ip', 'signed_user_agent',
    ];

    protected $casts = ['signed_at' => 'datetime'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
```

`app/Models/ContractItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Món đồ trong hợp đồng — đóng băng tên/phụ kiện/giá đền tại thời điểm lập. */
class ContractItem extends Model
{
    public const HANDOVER_CONDITIONS = ['new', 'good', 'used_marks'];
    public const RETURN_CONDITIONS = ['same', 'wear', 'damaged'];

    public const HANDOVER_LABELS = [
        'new' => 'Mới',
        'good' => 'Tốt',
        'used_marks' => 'Có vết cũ',
    ];

    public const RETURN_LABELS = [
        'same' => 'Như lúc giao',
        'wear' => 'Hao mòn thường',
        'damaged' => 'Hư hỏng',
    ];

    protected $fillable = [
        'contract_id', 'product_id', 'combo_name', 'name', 'accessories', 'quantity',
        'replacement_value', 'handover_condition', 'handover_note',
        'return_condition', 'return_note', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'replacement_value' => 'integer',
        'sort_order' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
```

`app/Models/HandoverPhoto.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ảnh chụp lúc giao / lúc thu đồ. */
class HandoverPhoto extends Model
{
    public const KINDS = ['pickup', 'return'];

    protected $fillable = ['contract_id', 'contract_item_id', 'kind', 'path', 'uploaded_by'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
```

- [ ] **Step 11: Nối quan hệ vào `Order` và `Product`**

Trong `app/Models/Order.php`, thêm cạnh các quan hệ `hasOne` sẵn có:

```php
    /** Hợp đồng điện tử của đơn (bopcamping-4jao) — đơn cha không có. */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }
```

Trong `app/Models/Product.php`, thêm `'replacement_value'` và `'accessories'` vào `$fillable` (ngay sau `'deposit'`), và thêm vào `$casts`:

```php
        'replacement_value' => 'integer',
```

- [ ] **Step 12: Chạy test, xác nhận PASS**

Run: `php artisan test --filter=ContractModelTest`
Expected: PASS (2 test).

- [ ] **Step 13: Quality gate + commit**

```bash
php artisan test && npx tsc --noEmit && npm run lint && ./vendor/bin/pint --test && npm run build
git add database/migrations app/Models tests/Feature/ContractModelTest.php
git commit -m "feat(contract): schema + model hợp đồng, 3 giai đoạn ký, CCCD mã hoá (bopcamping-4jao)"
```

---

### Task 3: `ContractService` — dựng hợp đồng và render

**Files:**
- Create: `app/Services/ContractService.php`, `tests/Feature/ContractServiceTest.php`
- Modify: `database/seeders/` (nếu có seeder SiteSetting — thêm mẫu mặc định)

**Interfaces:**
- Consumes: `Contract::STAGES`, `Contract::nextStage()`, `ContractItem::HANDOVER_LABELS`, `App\Services\ContractPdf::render()`
- Produces:
  - `ContractService::createFor(Order $order, array $identity): Contract` — `$identity` = `['id_number' => string|null, 'id_issued_on' => string|null, 'id_issued_place' => string|null]`. Ném `InvalidArgumentException` nếu `$order->is_parent`. Idempotent: gọi lại trả về hợp đồng cũ, không tạo trùng.
  - `ContractService::render(Contract $contract, string $stage): string` — HTML đã thay biến.
  - `ContractService::defaultTemplate(string $stage): string` — mẫu mặc định khi admin chưa soạn.

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/ContractServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Order;
use App\Models\Product;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ContractServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(bool $isParent = false): Order
    {
        $order = Order::create([
            'code' => 'BOP-HD002',
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0912345678',
            'is_parent' => $isParent,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 361000,
            'deposit_total' => 1500000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);

        $product = Product::factory()->create([
            'name' => 'Lều Village 6.0',
            'replacement_value' => 4500000,
            'accessories' => '1 túi đựng, 8 dây căng lều, 16 cọc ghim đất',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price_per_day' => 190000,
            'days' => 2,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'subtotal' => 380000,
        ]);

        return $order->fresh();
    }

    /** @test */
    public function tao_hop_dong_chup_lai_ten_phu_kien_va_gia_den_bu(): void
    {
        $contract = app(ContractService::class)->createFor($this->makeOrder(), []);

        $item = $contract->items->first();
        $this->assertSame('Lều Village 6.0', $item->name);
        $this->assertSame('1 túi đựng, 8 dây căng lều, 16 cọc ghim đất', $item->accessories);
        $this->assertSame(4500000, $item->replacement_value);
        $this->assertSame(64, strlen($contract->token));
    }

    /** @test */
    public function goi_lai_khong_tao_hop_dong_trung(): void
    {
        $service = app(ContractService::class);
        $order = $this->makeOrder();

        $a = $service->createFor($order, []);
        $b = $service->createFor($order->fresh(), []);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Contract::count());
    }

    /** @test */
    public function don_cha_khong_duoc_tao_hop_dong(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ContractService::class)->createFor($this->makeOrder(isParent: true), []);
    }

    /** @test */
    public function render_thay_bien_va_giu_dau_tieng_viet(): void
    {
        $contract = app(ContractService::class)->createFor($this->makeOrder(), [
            'id_number' => '040202015437',
        ]);

        $html = app(ContractService::class)->render($contract, 'main');

        $this->assertStringContainsString('Nguyễn Văn A', $html);
        $this->assertStringContainsString('Lều Village 6.0', $html);
        $this->assertStringNotContainsString('{{', $html, 'Còn biến chưa thay trong hợp đồng.');
    }

    /** @test */
    public function gia_den_bu_bang_0_in_gach_ngang_chu_khong_in_0d(): void
    {
        $order = $this->makeOrder();
        Product::query()->update(['replacement_value' => 0]);

        $contract = app(ContractService::class)->createFor($order, []);
        $html = app(ContractService::class)->render($contract, 'main');

        $this->assertStringNotContainsString('0 đ', $html, '0 đền bù phải in "—", không được in "0 đ" (dễ bị đọc thành đền 0 đồng).');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận FAIL**

Run: `php artisan test --filter=ContractServiceTest`
Expected: FAIL — `Class "App\Services\ContractService" does not exist`.

- [ ] **Step 3: Viết `ContractService`**

`app/Services/ContractService.php`:

```php
<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * NGUỒN DUY NHẤT dựng, render và ký hợp đồng thuê điện tử (bopcamping-4jao).
 *
 * Không nơi nào khác được tự render hợp đồng — nếu có hai chỗ render thì sớm muộn cũng
 * có bản khách đọc khác bản khách ký, và đó đúng là thứ tính năng này tồn tại để chặn.
 */
class ContractService
{
    /**
     * Dựng hợp đồng cho đơn. Idempotent — gọi lại trả về hợp đồng cũ.
     *
     * @param  array{id_number?: ?string, id_issued_on?: ?string, id_issued_place?: ?string}  $identity
     */
    public function createFor(Order $order, array $identity): Contract
    {
        // Đơn CHA chỉ gom đợt, không có ngày/đồ riêng — hợp đồng bám đơn con.
        if ($order->is_parent) {
            throw new InvalidArgumentException('Đơn cha không có hợp đồng riêng — lập hợp đồng trên từng đơn con.');
        }

        if ($existing = $order->contract) {
            $existing->fill($this->identityAttributes($identity))->save();

            return $existing->load('items', 'signatures');
        }

        $contract = Contract::create([
            'order_id' => $order->id,
            'code' => $this->codeFor($order),
            'token' => Str::random(64),
            ...$this->identityAttributes($identity),
        ]);

        foreach ($order->items()->with('product', 'combo')->get()->values() as $i => $item) {
            ContractItem::create([
                'contract_id' => $contract->id,
                'product_id' => $item->product_id,
                'combo_name' => $item->combo?->name,
                // Sản phẩm bị xoá thì vẫn phải có tên trên hợp đồng.
                'name' => $item->product?->name ?? '(sản phẩm đã xoá)',
                'accessories' => $item->product?->accessories,
                'quantity' => $item->quantity,
                'replacement_value' => (int) ($item->product?->replacement_value ?? 0),
                'sort_order' => $i,
            ]);
        }

        return $contract->load('items', 'signatures');
    }

    public function render(Contract $contract, string $stage): string
    {
        if (! in_array($stage, Contract::STAGES, true)) {
            throw new InvalidArgumentException("Giai đoạn không hợp lệ: {$stage}");
        }

        $template = $this->templateFor($stage);

        return strtr($template, $this->variables($contract, $stage));
    }

    /** @return array<string, string> */
    private function variables(Contract $contract, string $stage): array
    {
        $order = $contract->order;

        return [
            '{{so_hop_dong}}' => e($contract->code),
            '{{ma_don}}' => e($order->code),
            '{{ten_khach}}' => e($order->customer_name),
            '{{sdt_khach}}' => e($order->customer_phone),
            '{{dia_chi_khach}}' => e($order->customer_address ?? ''),
            '{{cccd_khach}}' => e($contract->signer_id_number ?? '.....................'),
            '{{ngay_cap}}' => $contract->signer_id_issued_on?->format('d/m/Y') ?? '..../..../........',
            '{{noi_cap}}' => e($contract->signer_id_issued_place ?? '.....................'),
            '{{ngay_nhan}}' => $order->start_date?->format('d/m/Y') ?? '',
            '{{ngay_tra}}' => $order->end_date?->format('d/m/Y') ?? '',
            '{{so_ngay_thue}}' => (string) ($order->start_date && $order->end_date
                ? $order->start_date->diffInDays($order->end_date) + 1
                : 0),
            '{{tong_tien}}' => $this->money($order->total_price),
            '{{tien_coc}}' => $this->money($order->deposit_total),
            '{{bang_thiet_bi}}' => $this->equipmentTable($contract),
            '{{bang_ban_giao}}' => $this->conditionTable($contract, 'handover'),
            '{{bang_nhan_lai}}' => $this->conditionTable($contract, 'return'),
            '{{bang_quyet_toan}}' => $this->settlementTable($contract),
        ];
    }

    /** Bảng Điều 1 — CÓ cột giá trị đền bù, thứ hợp đồng giấy đang thiếu. */
    private function equipmentTable(Contract $contract): string
    {
        $rows = '';
        foreach ($contract->items as $i => $item) {
            $rows .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%d</td><td>%s</td></tr>',
                $i + 1,
                e($item->name),
                $item->quantity,
                // 0 = chưa khai giá. In "—" chứ KHÔNG in "0 đ": số 0 dễ bị đọc thành
                // "đền 0 đồng", tức là mất luôn căn cứ trừ cọc.
                $item->replacement_value > 0 ? $this->money($item->replacement_value) : '—',
            );
        }

        return '<table><thead><tr><th>STT</th><th>Tên thiết bị</th><th>SL</th>'
            .'<th>Giá trị đền bù (VNĐ)</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private function conditionTable(Contract $contract, string $stage): string
    {
        $labels = $stage === 'handover' ? ContractItem::HANDOVER_LABELS : ContractItem::RETURN_LABELS;
        $field = $stage === 'handover' ? 'handover_condition' : 'return_condition';
        $noteField = $stage === 'handover' ? 'handover_note' : 'return_note';

        $rows = '';
        foreach ($contract->items as $i => $item) {
            $rows .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>',
                $i + 1,
                e($item->name),
                e($item->accessories ?? ''),
                $item->quantity,
                e($labels[$item->{$field}] ?? '(chưa ghi nhận)'),
                e($item->{$noteField} ?? ''),
            );
        }

        return '<table><thead><tr><th>STT</th><th>Tên thiết bị</th><th>Phụ kiện</th>'
            .'<th>SL</th><th>Tình trạng</th><th>Ghi chú</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /** Bảng quyết toán của Phụ lục B — số lấy từ đơn, KHÔNG để admin gõ tay vào editor. */
    private function settlementTable(Contract $contract): string
    {
        $order = $contract->order;
        $deposit = (int) $order->deposit_total;
        $fee = (int) ($order->extra_fee ?? 0);
        $refund = max(0, $deposit - $fee);

        $rows = [
            'Tiền đặt cọc đã thu' => $this->money($deposit),
            'Phí phạt trễ / bồi thường' => $this->money($fee),
            'Số tiền hoàn lại cho Bên B' => $this->money($refund),
        ];

        $html = '';
        foreach ($rows as $label => $value) {
            $html .= sprintf('<tr><td>%s</td><td>%s</td></tr>', e($label), $value);
        }

        return '<table><thead><tr><th>Nội dung</th><th>Số tiền (VNĐ)</th></tr></thead><tbody>'
            .$html.'</tbody></table>';
    }

    private function templateFor(string $stage): string
    {
        $column = match ($stage) {
            'main' => 'contract_template_html',
            'handover' => 'handover_template_html',
            'return' => 'return_template_html',
        };

        return SiteSetting::current()->{$column} ?: $this->defaultTemplate($stage);
    }

    private function codeFor(Order $order): string
    {
        return $order->code.'/HĐTTB';
    }

    /** @param array{id_number?: ?string, id_issued_on?: ?string, id_issued_place?: ?string} $identity */
    private function identityAttributes(array $identity): array
    {
        return array_filter([
            'signer_id_number' => $identity['id_number'] ?? null,
            'signer_id_issued_on' => $identity['id_issued_on'] ?? null,
            'signer_id_issued_place' => $identity['id_issued_place'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function money(?int $amount): string
    {
        return number_format((int) $amount, 0, ',', '.').' đ';
    }

    public function defaultTemplate(string $stage): string
    {
        return match ($stage) {
            'main' => view('contracts.defaults.main')->render(),
            'handover' => view('contracts.defaults.handover')->render(),
            'return' => view('contracts.defaults.return')->render(),
        };
    }
}
```

- [ ] **Step 4: Viết 3 mẫu mặc định**

Tạo `resources/views/contracts/defaults/main.blade.php` — chép **nguyên văn Điều 1–10** từ hợp đồng 1408/HĐTTB, thay chỗ trống bằng biến. Ba chỗ **bắt buộc sửa so với bản giấy** (xem mục 8 design spec):

1. Ghi chú CCCD ở phần Bên B → `(Bên A ghi nhận thông tin và ảnh chụp CCCD để đối chiếu, không giữ bản gốc; dữ liệu được xoá sau khi hoàn tất hoàn cọc 90 ngày)`
2. Điều 1 dùng `{{bang_thiet_bi}}` (đã có cột giá trị đền bù)
3. Điều 3.2 → cọc thanh toán **trước khi nhận thiết bị**

Khung tối thiểu:

```blade
<h2 style="text-align:center">HỢP ĐỒNG THUÊ THIẾT BỊ CAMPING</h2>
<p style="text-align:center">Số: {{ '{{so_hop_dong}}' }}</p>
<p><strong>BÊN THUÊ (BÊN B)</strong></p>
<p>- Họ và tên: {{ '{{ten_khach}}' }}</p>
<p>- CCCD số: {{ '{{cccd_khach}}' }} — Ngày cấp: {{ '{{ngay_cap}}' }} — Nơi cấp: {{ '{{noi_cap}}' }}</p>
<p><em>(Bên A ghi nhận thông tin và ảnh chụp CCCD để đối chiếu, không giữ bản gốc; dữ liệu được xoá sau khi hoàn tất hoàn cọc 90 ngày)</em></p>
<p>- Địa chỉ: {{ '{{dia_chi_khach}}' }} — Điện thoại: {{ '{{sdt_khach}}' }}</p>
<h3>ĐIỀU 1. ĐỐI TƯỢNG THUÊ</h3>
{{ '{{bang_thiet_bi}}' }}
<h3>ĐIỀU 2. THỜI HẠN THUÊ</h3>
<p>Nhận: {{ '{{ngay_nhan}}' }} — Trả: {{ '{{ngay_tra}}' }} — Tổng {{ '{{so_ngay_thue}}' }} ngày.</p>
<h3>ĐIỀU 3. GIÁ THUÊ VÀ THANH TOÁN</h3>
<p>Tổng thanh toán: {{ '{{tong_tien}}' }}. Tiền đặt cọc: {{ '{{tien_coc}}' }}, thanh toán <strong>trước khi nhận thiết bị</strong>.</p>
```

`handover.blade.php` dùng `{{bang_ban_giao}}`; `return.blade.php` dùng `{{bang_nhan_lai}}` + `{{bang_quyet_toan}}`.

- [ ] **Step 5: Chạy test, xác nhận PASS**

Run: `php artisan test --filter=ContractServiceTest`
Expected: PASS (5 test).

- [ ] **Step 6: Quality gate + commit**

```bash
php artisan test && npx tsc --noEmit && npm run lint && ./vendor/bin/pint --test && npm run build
git add app/Services/ContractService.php resources/views/contracts tests/Feature/ContractServiceTest.php
git commit -m "feat(contract): ContractService dựng + render hợp đồng, bảng Điều 1 có giá đền bù (bopcamping-4jao)"
```

---

### Task 4: Ký giai đoạn `main` — trang khách + cửa 4 số cuối

**Files:**
- Create: `app/Http/Controllers/Shop/ContractController.php`, `resources/js/Pages/Contract.tsx`, `resources/js/Components/SignaturePadField.tsx`, `tests/Feature/ContractSigningTest.php`, `tests/js/SignaturePadField.test.tsx`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `ContractService::render()`, `Contract::nextStage()`, `Contract::phoneLast4()`
- Produces: `ContractService::sign(Contract $c, string $stage, string $signaturePng, string $expectedHash, Request $r): void` — ném `RuntimeException` nếu đã ký, `DomainException` nếu hash lệch.

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/ContractSigningTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Order;
use App\Models\Product;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractSigningTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 trong suốt — đủ để đại diện chữ ký trong test. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function makeContract(): Contract
    {
        Storage::fake('media');

        $order = Order::create([
            'code' => 'BOP-HD003',
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0912345678',
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 361000,
            'deposit_total' => 1500000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);
        $product = Product::factory()->create(['replacement_value' => 4500000]);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 190000,
            'days' => 2, 'start_date' => '2030-07-01', 'end_date' => '2030-07-03', 'subtotal' => 380000,
        ]);

        return app(ContractService::class)->createFor($order->fresh(), []);
    }

    /** @test */
    public function token_sai_tra_404(): void
    {
        $this->get('/hop-dong/'.str_repeat('z', 64))->assertNotFound();
    }

    /** @test */
    public function chua_qua_cua_4_so_cuoi_thi_khong_thay_noi_dung(): void
    {
        $c = $this->makeContract();

        $this->get("/hop-dong/{$c->token}")
            ->assertInertia(fn ($p) => $p->component('Contract')->where('unlocked', false));
    }

    /** @test */
    public function sai_4_so_cuoi_thi_bi_tu_choi(): void
    {
        $c = $this->makeContract();

        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '0000'])
            ->assertSessionHasErrors('last4');
    }

    /** @test */
    public function dung_4_so_cuoi_thi_mo_duoc_va_ghi_first_viewed_at(): void
    {
        $c = $this->makeContract();

        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678'])->assertRedirect();
        $this->assertNotNull($c->fresh()->first_viewed_at);
    }

    /** @test */
    public function so_cccd_khong_bao_gio_lot_ra_prop_cua_trang_khach(): void
    {
        $c = $this->makeContract();
        $c->update(['signer_id_number' => '040202015437', 'id_front_path' => 'id/front.jpg']);
        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678']);

        $response = $this->get("/hop-dong/{$c->token}");
        $json = json_encode($response->viewData('page')['props'], JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('040202015437', $json);
        $this->assertStringNotContainsString('id/front.jpg', $json);
    }

    /** @test */
    public function ky_thanh_cong_thi_luu_chu_ky_va_dau_vet(): void
    {
        $c = $this->makeContract();
        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678']);
        $hash = hash('sha256', app(ContractService::class)->render($c, 'main'));

        $this->post("/hop-dong/{$c->token}/ky/main", [
            'signature' => self::PNG,
            'content_hash' => $hash,
        ])->assertRedirect();

        $sig = $c->fresh()->signatureFor('main');
        $this->assertNotNull($sig);
        $this->assertSame($hash, $sig->content_hash);
        $this->assertNotNull($sig->signed_ip);
        Storage::disk('media')->assertExists($sig->signature_path);
    }

    /** @test */
    public function hash_lech_thi_tu_choi_va_khong_ghi_gi(): void
    {
        $c = $this->makeContract();
        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678']);

        $this->post("/hop-dong/{$c->token}/ky/main", [
            'signature' => self::PNG,
            'content_hash' => hash('sha256', 'bản khác'),
        ])->assertSessionHasErrors('content_hash');

        $this->assertNull($c->fresh()->signatureFor('main'));
    }

    /** @test */
    public function ky_lai_cung_giai_doan_bi_chan(): void
    {
        $c = $this->makeContract();
        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678']);
        $hash = hash('sha256', app(ContractService::class)->render($c, 'main'));
        $this->post("/hop-dong/{$c->token}/ky/main", ['signature' => self::PNG, 'content_hash' => $hash]);

        $this->post("/hop-dong/{$c->token}/ky/main", ['signature' => self::PNG, 'content_hash' => $hash])
            ->assertSessionHasErrors();

        $this->assertSame(1, $c->fresh()->signatures()->where('stage', 'main')->count());
    }

    /** @test */
    public function khong_duoc_ky_handover_khi_chua_ky_main(): void
    {
        $c = $this->makeContract();
        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678']);
        $hash = hash('sha256', app(ContractService::class)->render($c, 'handover'));

        $this->post("/hop-dong/{$c->token}/ky/handover", ['signature' => self::PNG, 'content_hash' => $hash])
            ->assertSessionHasErrors();

        $this->assertNull($c->fresh()->signatureFor('handover'));
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận FAIL**

Run: `php artisan test --filter=ContractSigningTest`
Expected: FAIL — route `/hop-dong/...` chưa tồn tại (404 ở mọi test trừ test đầu).

- [ ] **Step 3: Thêm `sign()` vào `ContractService`**

Thêm vào `app/Services/ContractService.php`:

```php
    /**
     * Ký một giai đoạn. Đóng băng nội dung + hash TẠI ĐÂY, không phải lúc tạo hợp đồng —
     * admin sửa mẫu được giữa chừng.
     *
     * $expectedHash là hash của bản khách ĐANG ĐỌC. Lệch nghĩa là mẫu vừa đổi giữa lúc
     * khách mở trang và lúc bấm ký → từ chối, bắt tải lại. Khách không bao giờ ký thứ
     * mình chưa đọc.
     *
     * @throws \RuntimeException  giai đoạn đã ký, hoặc chưa ký giai đoạn trước
     * @throws \DomainException   hash lệch
     */
    public function sign(Contract $contract, string $stage, string $signaturePng, string $expectedHash, \Illuminate\Http\Request $request): void
    {
        if ($contract->nextStage() !== $stage) {
            throw new \RuntimeException('Giai đoạn này đã ký hoặc chưa tới lượt ký.');
        }

        $html = $this->render($contract, $stage);
        $hash = hash('sha256', $html);

        if (! hash_equals($hash, $expectedHash)) {
            throw new \DomainException('Nội dung hợp đồng vừa thay đổi. Hãy tải lại trang và đọc lại trước khi ký.');
        }

        $path = "contracts/{$contract->id}/{$stage}.png";
        \Illuminate\Support\Facades\Storage::disk('media')->put($path, $this->decodePng($signaturePng));

        $contract->signatures()->create([
            'stage' => $stage,
            'content_html' => $html,
            'content_hash' => $hash,
            'signature_path' => $path,
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    /** data URL PNG -> binary. Từ chối mọi thứ không phải PNG. */
    private function decodePng(string $dataUrl): string
    {
        if (! preg_match('#^data:image/png;base64,#', $dataUrl)) {
            throw new \DomainException('Chữ ký không hợp lệ.');
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false || ! str_starts_with($binary, "\x89PNG")) {
            throw new \DomainException('Chữ ký không hợp lệ.');
        }

        return $binary;
    }
```

- [ ] **Step 4: Viết `ContractController`**

`app/Http/Controllers/Shop/ContractController.php`:

```php
<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\ContractService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Trang ký hợp đồng của khách — không cần đăng nhập, mở bằng link token (bopcamping-4jao).
 *
 * Cửa 4 số cuối SĐT: link gửi qua Zalo bị chuyển tiếp được, mà hợp đồng chứa tên, địa chỉ
 * và số CCCD. Token 64 ký tự chặn dò mù; 4 số cuối chặn người vô tình nhặt được link.
 */
class ContractController extends Controller
{
    public function __construct(private ContractService $contracts) {}

    public function show(Request $request, string $token): Response
    {
        $contract = $this->find($token);

        if (! $this->unlocked($request, $contract)) {
            return Inertia::render('Contract', [
                'unlocked' => false,
                'token' => $token,
                'customer_name' => $contract->order->customer_name,
            ]);
        }

        $stage = $contract->nextStage();
        // Ký xong cả ba thì hiện bản hợp đồng chính để đọc lại.
        $viewStage = $stage ?? 'main';
        $html = $this->contracts->render($contract, $viewStage);

        return Inertia::render('Contract', [
            'unlocked' => true,
            'token' => $token,
            'code' => $contract->code,
            'stage' => $stage,
            'stage_label' => Contract::STAGE_LABELS[$viewStage],
            'content_html' => $html,
            'content_hash' => hash('sha256', $html),
            'signed_stages' => $contract->signatures->pluck('stage')->values(),
            'has_pdf' => $contract->pdf_path !== null,
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $contract = $this->find($token);

        $data = $request->validate(['last4' => ['required', 'string', 'size:4']]);

        if (! hash_equals($contract->phoneLast4(), $data['last4'])) {
            return back()->withErrors(['last4' => 'Bốn số cuối chưa đúng. Nhập 4 số cuối của số điện thoại đặt đơn.']);
        }

        $request->session()->put($this->sessionKey($contract), true);

        if (! $contract->first_viewed_at) {
            $contract->forceFill(['first_viewed_at' => now()])->save();
        }

        return back();
    }

    public function sign(Request $request, string $token, string $stage): RedirectResponse
    {
        $contract = $this->find($token);

        abort_unless($this->unlocked($request, $contract), 403);

        $data = $request->validate([
            'signature' => ['required', 'string', 'max:2000000'],
            'content_hash' => ['required', 'string', 'size:64'],
        ]);

        try {
            $this->contracts->sign($contract, $stage, $data['signature'], $data['content_hash'], $request);
        } catch (DomainException $e) {
            return back()->withErrors(['content_hash' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã ký xong. Bản PDF sẽ được gửi vào email của bạn.');
    }

    private function find(string $token): Contract
    {
        return Contract::with('order', 'items', 'signatures')
            ->where('token', $token)
            ->firstOrFail();
    }

    private function unlocked(Request $request, Contract $contract): bool
    {
        return $request->session()->get($this->sessionKey($contract)) === true;
    }

    private function sessionKey(Contract $contract): string
    {
        return "contract_unlocked_{$contract->id}";
    }
}
```

- [ ] **Step 5: Thêm route**

Trong `routes/web.php`, cạnh route `/danh-gia/{token}`:

```php
// Hợp đồng thuê điện tử — ký qua link token, không cần đăng nhập (bopcamping-4jao).
// throttle trên 'mo' chặn dò 4 số cuối; token 64 ký tự đã chặn dò mù từ trước.
Route::get('/hop-dong/{token}', [ContractController::class, 'show'])->name('contract.show');
Route::post('/hop-dong/{token}/mo', [ContractController::class, 'unlock'])->name('contract.unlock')->middleware('throttle:10,1');
Route::post('/hop-dong/{token}/ky/{stage}', [ContractController::class, 'sign'])->name('contract.sign')->middleware('throttle:10,1');
```

Thêm `use App\Http\Controllers\Shop\ContractController;` vào đầu file.

- [ ] **Step 6: Viết `SignaturePadField.tsx`**

`resources/js/Components/SignaturePadField.tsx`:

```tsx
import SignaturePad from 'signature_pad';
import { useEffect, useRef, useState } from 'react';

type Props = {
    onChange: (dataUrl: string | null) => void;
};

/**
 * Ô vẽ chữ ký. Canvas phải scale theo devicePixelRatio, không thì nét ký bị vỡ
 * trên màn hình retina — lỗi hay gặp nhất của canvas ký trên điện thoại.
 */
export default function SignaturePadField({ onChange }: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const padRef = useRef<SignaturePad | null>(null);
    const [empty, setEmpty] = useState(true);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const resize = () => {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d')?.scale(ratio, ratio);
            padRef.current?.clear();
            setEmpty(true);
            onChange(null);
        };

        const pad = new SignaturePad(canvas, { backgroundColor: 'rgba(0,0,0,0)' });
        padRef.current = pad;
        pad.addEventListener('endStroke', () => {
            setEmpty(false);
            onChange(pad.toDataURL('image/png'));
        });

        resize();
        window.addEventListener('resize', resize);

        return () => {
            window.removeEventListener('resize', resize);
            pad.off();
        };
    }, [onChange]);

    const clear = () => {
        padRef.current?.clear();
        setEmpty(true);
        onChange(null);
    };

    return (
        <div>
            <canvas
                ref={canvasRef}
                aria-label="Ô ký tên"
                className="h-40 w-full touch-none rounded-md border border-stone-300 bg-white"
            />
            <div className="mt-2 flex items-center justify-between">
                <p className="text-sm text-stone-500">Ký bằng ngón tay hoặc chuột vào ô trên.</p>
                <button type="button" onClick={clear} disabled={empty} className="text-sm underline disabled:opacity-40">
                    Xoá chữ ký
                </button>
            </div>
        </div>
    );
}
```

- [ ] **Step 7: Viết `Contract.tsx`**

`resources/js/Pages/Contract.tsx` — hai trạng thái: chưa mở khoá (form 4 số cuối) và đã mở khoá (nội dung + ô ký). Dùng `useForm` của Inertia, `dangerouslySetInnerHTML` cho `content_html` (đã qua `EditorHtml::clean()` phía admin nên an toàn — ghi comment nêu rõ điều này). Nút Ký `disabled` khi chưa vẽ.

- [ ] **Step 8: Viết test component**

`tests/js/SignaturePadField.test.tsx` — kiểm: mới render thì `onChange` chưa được gọi với data URL; nút "Xoá chữ ký" disabled khi chưa vẽ. Mock `signature_pad` bằng `vi.mock`.

- [ ] **Step 9: Chạy test, xác nhận PASS**

Run: `php artisan test --filter=ContractSigningTest && npm test -- SignaturePadField`
Expected: PASS (9 feature test + 2 component test).

- [ ] **Step 10: Quality gate + commit**

```bash
php artisan test && npm test && npx tsc --noEmit && npm run lint && ./vendor/bin/pint --test && npm run build
git add app/Http/Controllers/Shop/ContractController.php app/Services/ContractService.php routes/web.php resources/js/Pages/Contract.tsx resources/js/Components/SignaturePadField.tsx tests/
git commit -m "feat(contract): trang ký của khách + cửa 4 số cuối SĐT, chặn hash lệch (bopcamping-4jao)"
```

---

### Task 5: PDF + biên bản chứng thực + mail

**Files:**
- Create: `resources/views/pdf/contract.blade.php`, `app/Mail/ContractSignedMail.php`, `resources/views/emails/contract_signed.blade.php`, `tests/Feature/ContractPdfTest.php`
- Modify: `app/Services/ContractService.php`

**Interfaces:**
- Consumes: `ContractPdf::render()`, `Contract::signatureFor()`
- Produces: `ContractService::pdf(Contract $c): string` (binary) · `ContractService::storePdf(Contract $c): string` (trả path trên disk `media`) · `ContractController::pdf()` → route `contract.pdf`

**Route tải PDF cho khách** (spec §6.1) — thêm vào `routes/web.php` cạnh 3 route hợp đồng đã có:

```php
Route::get('/hop-dong/{token}/pdf', [ContractController::class, 'pdf'])->name('contract.pdf');
```

Controller — **phải qua cửa 4 số cuối** rồi mới cho tải, vì PDF chứa tên, địa chỉ và số CCCD:

```php
    public function pdf(Request $request, string $token): StreamedResponse
    {
        $contract = $this->find($token);

        abort_unless($this->unlocked($request, $contract), 403);
        abort_if($contract->pdf_path === null, 404);

        return Storage::disk('media')->download(
            $contract->pdf_path,
            "hop-dong-{$contract->order->code}.pdf"
        );
    }
```

Test kèm theo: chưa qua cửa 4 số cuối mà gọi thẳng `/pdf` thì **403**; hợp đồng chưa ký lần nào thì **404**.

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/ContractPdfTest.php` — kiểm: ký `main` xong thì `pdf_path` được ghi và file tồn tại trên disk `media`; mail `ContractSignedMail` được queue tới email khách; PDF ký một giai đoạn vẫn render được (hai phần kia để trống, không vỡ); biên bản chứng thực chứa mã đơn và hash.

- [ ] **Step 2: Chạy test, xác nhận FAIL**

Run: `php artisan test --filter=ContractPdfTest`
Expected: FAIL — `Call to undefined method App\Services\ContractService::storePdf()`.

- [ ] **Step 3: Viết Blade PDF**

`resources/views/pdf/contract.blade.php` — dùng `<table>` + inline style, **KHÔNG** dùng component Tailwind (dompdf không hiểu flex/grid). Ba khối: hợp đồng chính, Phụ lục A, Phụ lục B — khối chưa ký in dòng *"(Chưa ký)"*. Trang cuối là **Biên bản chứng thực**: mã hợp đồng, mã đơn, hash từng giai đoạn, thời điểm gửi/mở/ký, IP, thiết bị, email đã xác thực OTP, và **chỉ dẫn tra sao kê** (mã đơn, số tiền, thời điểm + người ghi nhận đã thu).

`<html>` phải có `<style>* { font-family: 'DejaVu Sans', sans-serif; }</style>` — nếu không thì chữ có dấu ra ô vuông.

- [ ] **Step 4: Thêm `pdf()` + `storePdf()` vào `ContractService`, gọi từ `sign()`**

Cuối `sign()`, sau khi tạo `ContractSignature`:

```php
        $contract->load('signatures', 'items');
        $contract->forceFill(['pdf_path' => $this->storePdf($contract)])->save();

        if ($email = $contract->order->notifiableEmail()) {
            Mail::to($email)->send(new ContractSignedMail($contract, $stage));
        }
```

- [ ] **Step 5: Viết `ContractSignedMail`**

Đính kèm PDF từ disk `media`, subject theo giai đoạn vừa ký, `ShouldQueue`.

- [ ] **Step 6: Chạy test, xác nhận PASS**

Run: `php artisan test --filter=ContractPdf`
Expected: PASS (cả `ContractPdfFontTest` lẫn `ContractPdfTest`).

- [ ] **Step 7: Quality gate + commit**

```bash
php artisan test && npm test && npx tsc --noEmit && npm run lint && ./vendor/bin/pint --test && npm run build
git add app/Services/ContractService.php app/Mail resources/views/pdf resources/views/emails tests/Feature/ContractPdfTest.php
git commit -m "feat(contract): sinh PDF + biên bản chứng thực, mail bản đã ký cho khách (bopcamping-4jao)"
```

---

### Task 6: Admin — nhập CCCD, tạo hợp đồng, sao chép link

**Files:**
- Create: `app/Http/Controllers/Admin/AdminContractController.php`, `tests/Feature/AdminContractTest.php`
- Modify: `routes/web.php`, `app/Http/Controllers/Admin/AdminOrderController.php`, `resources/js/Pages/Admin/OrderShow.tsx`

**Interfaces:**
- Consumes: `ContractService::createFor()`
- Produces: route `admin.contracts.store` (tạo/cập nhật), `admin.contracts.identity` (upload ảnh CCCD), `admin.contracts.id-image` (xem ảnh, có kiểm quyền)

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/AdminContractTest.php` — kiểm: admin tạo được hợp đồng từ đơn con; đơn cha bị từ chối kèm thông báo; ảnh CCCD lưu vào thư mục riêng và **không** sinh biến thể public; route xem ảnh chặn người không phải admin (403); prop trang admin có `contract.signed_stages`.

- [ ] **Step 2: Chạy test, xác nhận FAIL**

Run: `php artisan test --filter=AdminContractTest`

- [ ] **Step 3: Viết controller + route + UI**

Trong nhóm `admin` của `routes/web.php`:

```php
    // Hợp đồng điện tử của đơn (bopcamping-4jao)
    Route::post('/orders/{order}/contract', [AdminContractController::class, 'store'])->name('contracts.store')->middleware('throttle:30,1');
    Route::post('/orders/{order}/contract/identity', [AdminContractController::class, 'storeIdentity'])->name('contracts.identity')->middleware('throttle:30,1');
    Route::get('/contracts/{contract}/anh-cccd/{side}', [AdminContractController::class, 'idImage'])->name('contracts.id-image')->whereIn('side', ['front', 'back']);
```

Trên `OrderShow.tsx`: form CCCD (số, ngày cấp, nơi cấp) + upload 2 ảnh + nút **Tạo hợp đồng** + nút **Sao chép link** + trạng thái 3 giai đoạn. Kèm dòng nhắc: *"Đã upload xong thì xoá ảnh CCCD khỏi Zalo."*

- [ ] **Step 4: Chạy test, xác nhận PASS**

- [ ] **Step 5: Quality gate + commit**

```bash
git commit -m "feat(contract): admin nhập CCCD + tạo hợp đồng + sao chép link gửi Zalo (bopcamping-4jao)"
```

---

### Task 7: Giai đoạn `handover` — checklist tình trạng + ảnh bàn giao

**Files:**
- Create: `tests/Feature/ContractHandoverTest.php`
- Modify: `app/Http/Controllers/Shop/ContractController.php`, `resources/js/Pages/Contract.tsx`, `app/Http/Controllers/ShipperScheduleController.php`, trang lịch giao của shipper

**Interfaces:**
- Consumes: `ContractItem::HANDOVER_CONDITIONS`, `ContractService::sign()`
- Produces: `POST /hop-dong/{token}/tinh-trang/handover` — lưu `handover_condition` + `handover_note` từng món trước khi ký

> ⚠️ **Cái bẫy của task này.** `{{bang_ban_giao}}` nằm TRONG nội dung được hash. Tick tình
> trạng một món là nội dung đổi → hash đổi → chữ ký gửi lên kèm hash cũ sẽ bị `sign()` từ
> chối. Đây là hành vi ĐÚNG (đúng thứ chống ký nhầm bản), nhưng nếu FE không xử lý thì
> shipper sẽ gặp lỗi "nội dung vừa thay đổi" ngay giữa lúc đứng bàn giao đồ với khách.
> Cách xử lý: lưu tình trạng là một request RIÊNG, server trả về `content_html` +
> `content_hash` MỚI, FE thay vào state rồi mới cho ký. Không gộp hai việc vào một request.

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/ContractHandoverTest.php` — 4 test:

```php
    /** @test */
    public function thieu_tinh_trang_mot_mon_thi_khong_cho_ky(): void
    // Ký handover khi còn ContractItem có handover_condition = null → assertSessionHasErrors,
    // và không tạo ContractSignature nào.

    /** @test */
    public function luu_tinh_trang_xong_thi_hash_doi(): void
    // Hash trước và sau khi POST tinh-trang phải KHÁC nhau — chứng minh bảng tình trạng
    // thực sự nằm trong nội dung được ký.

    /** @test */
    public function ky_bang_hash_cu_sau_khi_doi_tinh_trang_bi_tu_choi(): void
    // Lấy hash, đổi tình trạng, rồi ký bằng hash cũ → assertSessionHasErrors('content_hash').

    /** @test */
    public function anh_ban_giao_luu_dung_kind_pickup(): void
    // Upload ảnh → HandoverPhoto có kind = 'pickup', contract_id đúng, file tồn tại trên
    // disk media.
```

- [ ] **Step 2: Chạy test, xác nhận FAIL**

Run: `php artisan test --filter=ContractHandoverTest`
Expected: FAIL — route `tinh-trang` chưa tồn tại (405/404).

- [ ] **Step 3: Thêm route lưu tình trạng và ảnh**

```php
Route::post('/hop-dong/{token}/tinh-trang/{stage}', [ContractController::class, 'saveConditions'])
    ->name('contract.conditions')->middleware('throttle:60,1')->whereIn('stage', ['handover', 'return']);
Route::post('/hop-dong/{token}/anh/{kind}', [ContractController::class, 'storePhoto'])
    ->name('contract.photo')->middleware('throttle:60,1')->whereIn('kind', ['pickup', 'return']);
```

- [ ] **Step 4: Viết `saveConditions()` + chặn ký khi thiếu tình trạng**

Trong `ContractController::saveConditions()`: validate `items.*.condition` phải `in:` đúng
tập hằng của giai đoạn, `items.*.note` `nullable|string|max:500`; cập nhật từng
`ContractItem`; trả `back()` để Inertia nạp lại prop (kèm `content_hash` mới).

Trong `ContractService::sign()`, ngay sau kiểm `nextStage()`:

```php
        // Phụ lục A/B chỉ ký được khi ĐỦ tình trạng mọi món — biên bản thiếu ô là biên
        // bản vô dụng lúc đối chiếu trả đồ.
        $field = match ($stage) {
            'handover' => 'handover_condition',
            'return' => 'return_condition',
            default => null,
        };

        if ($field !== null && $contract->items->contains(fn ($i) => $i->{$field} === null)) {
            throw new \RuntimeException('Còn thiết bị chưa ghi nhận tình trạng — không ký được biên bản thiếu ô.');
        }
```

- [ ] **Step 5: Chạy test, xác nhận PASS**

Run: `php artisan test --filter=ContractHandoverTest`
Expected: PASS (4 test).

- [ ] **Step 6: Quality gate + commit**

```bash
git commit -m "feat(contract): Phụ lục A — checklist tình trạng + ảnh + ký lúc bàn giao (bopcamping-4jao)"
```

---

### Task 8: Giai đoạn `return` — checklist + bảng quyết toán

**Files:**
- Create: `tests/Feature/ContractReturnTest.php`
- Modify: `app/Http/Controllers/Shop/ContractController.php`, `resources/js/Pages/Contract.tsx`

- [ ] **Step 1: Viết test thất bại** — kiểm: bảng quyết toán lấy đúng `deposit_total` và `extra_fee` của đơn; số hoàn lại không bao giờ âm; thiếu tình trạng trả của một món thì không cho ký; ký `return` khi chưa ký `handover` bị chặn.

- [ ] **Step 2–4:** chạy FAIL → viết implementation → chạy PASS.

- [ ] **Step 5: Quality gate + commit**

```bash
git commit -m "feat(contract): Phụ lục B — checklist trả đồ + bảng quyết toán cọc + ký (bopcamping-4jao)"
```

---

### Task 9: Trang Mẫu hợp đồng + lệnh xoá dữ liệu định danh

**Files:**
- Create: `app/Http/Controllers/Admin/AdminContractTemplateController.php`, `resources/js/Pages/Admin/ContractTemplates.tsx`, `app/Console/Commands/PurgeContractIdentity.php`, `tests/Feature/PurgeContractIdentityTest.php`
- Modify: `routes/web.php`, `routes/console.php`

- [ ] **Step 1: Viết test thất bại cho lệnh xoá**

`tests/Feature/PurgeContractIdentityTest.php` — kiểm: đơn đã hoàn cọc **quá 90 ngày** thì xoá cả `signer_id_number` lẫn 2 file ảnh; đơn hoàn cọc **89 ngày** thì giữ nguyên; đơn **chưa** hoàn cọc thì giữ nguyên; lệnh idempotent (chạy hai lần không lỗi); **không** đụng `content_html` đã ký (hợp đồng đã ký là bất biến).

- [ ] **Step 2: Chạy test, xác nhận FAIL**

- [ ] **Step 3: Viết command**

```php
    protected $signature = 'contracts:purge-identity';
```

Lọc `Contract` có `order.deposit_refunded_at <= now()->subDays(90)` và còn `signer_id_number` hoặc ảnh → xoá file trên disk `media`, `forceFill` null cho 3 cột định danh, `saveQuietly()`.

- [ ] **Step 4: Đăng ký lịch** — `routes/console.php`:

```php
// Xoá số + ảnh CCCD 90 ngày sau khi hoàn cọc (Luật BVDLCN 2025) — bopcamping-4jao.
Schedule::command('contracts:purge-identity')->dailyAt('03:00');
```

- [ ] **Step 5: Trang Mẫu hợp đồng** — 3 editor TipTap, lưu qua `EditorHtml::clean()`, hiển thị danh sách biến chèn được. Route `admin.contract-templates` + `.update`.

- [ ] **Step 6: Chạy test, xác nhận PASS**

- [ ] **Step 7: Quality gate + commit + push**

```bash
php artisan test && npm test && npx tsc --noEmit && npm run lint && ./vendor/bin/pint --test && npm run build
git commit -m "feat(contract): trang Mẫu hợp đồng + lệnh xoá CCCD sau hoàn cọc 90 ngày (bopcamping-4jao)"
git push
```

---

## Sau khi xong cả 9 task

- [ ] Chạy `superpowers:requesting-code-review` trên toàn nhánh.
- [ ] Chạy `superpowers:verification-before-completion`.
- [ ] Kiểm thủ công trên trình duyệt: ký thử đủ 3 giai đoạn trên viewport mobile (jsdom **không** kiểm được layout thật — chồng lấn, z-index, canvas cảm ứng).
- [ ] Mở PDF sinh ra, **nhìn bằng mắt** xem chữ có dấu hiển thị đúng không (test chỉ khẳng định font được nhúng, không khẳng định layout đẹp).
- [ ] Nhắc chủ shop: sửa 5 chỗ trong văn bản hợp đồng (mục 8 design spec) + điền `replacement_value` cho từng sản phẩm.
