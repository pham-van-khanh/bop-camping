<?php

namespace Tests\Feature;

use App\Services\ContractPdf;
use Tests\TestCase;

/**
 * bopcamping-4jao — chặn lỗi font dompdf, vốn là LỖI IM LẶNG: thiếu font có dấu thì chữ
 * tiếng Việt ra ô vuông mà không ném exception nào, nên chỉ phát hiện khi khách đã cầm file.
 *
 * Test khẳng định PDF có NHÚNG DejaVu Sans (font đủ dấu tiếng Việt). Cố ý KHÔNG bóc text
 * ra so chuỗi: lớp text của PDF vẫn giữ đúng ký tự Unicode kể cả khi glyph không vẽ được,
 * nên so text sẽ PASS ngay cả lúc file hỏng về mặt nhìn — tức là một test vô dụng.
 *
 * Ràng buộc này kế thừa từ artifacts/adr_pdf_generation.md.
 */
class ContractPdfFontTest extends TestCase
{
    /** @test */
    public function pdf_nhung_font_co_dau_tieng_viet(): void
    {
        $html = '<html><body><p>Lều Village 6.0 — bồi thường 100% giá trị thiết bị</p></body></html>';

        $pdf = app(ContractPdf::class)->render($html);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString(
            'DejaVuSans',
            $pdf,
            'PDF không nhúng DejaVu Sans — chữ có dấu sẽ ra ô vuông. Kiểm config/dompdf.php: default_font.'
        );
    }
}
