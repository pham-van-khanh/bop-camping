<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Bọc dompdf — CHỖ DUY NHẤT dự án gọi thư viện PDF (bopcamping-4jao).
 *
 * Tách khỏi ContractService vì hai lý do:
 *   1. Test font đứng độc lập được (ContractPdfFontTest), không phải dựng cả một hợp đồng
 *      chỉ để kiểm chữ có dấu.
 *   2. Nếu về sau shop muốn lên tầng "tương đương chữ ký tay" thì ký số bản PDF bằng chứng
 *      thư của CA Việt Nam chỉ phải sửa đúng file này (adr_contract_esignature mục 3.5).
 */
class ContractPdf
{
    public function render(string $html): string
    {
        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }
}
