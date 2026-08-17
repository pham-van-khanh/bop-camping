{{--
    Layout PDF hợp đồng (bopcamping-4jao) — trình bày theo lối văn bản hành chính Việt Nam.

    Viết bằng table + CSS mà dompdf hiểu, KHÔNG tái dùng component Tailwind: dompdf không có
    flex/grid, dùng vào là vỡ bố cục mà chẳng có lỗi nào ném ra.

    Font: DejaVu Serif cho thân bài (chữ có chân, giống Times New Roman của văn bản hành chính)
    và DejaVu Sans cho bảng/biên bản chứng thực. CẢ HAI đều đủ dấu tiếng Việt — đổi sang font
    khác là chữ ra ô vuông trong im lặng, ContractPdfFontTest canh chỗ này.
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 20mm 18mm 22mm 25mm; /* lề trái rộng hơn theo lối văn bản đóng gáy */
        }

        body {
            font-family: 'DejaVu Serif', serif;
            font-size: 12px;
            line-height: 1.6;
            color: #000;
            margin: 0;
            text-align: justify;
        }

        /* ---- Chân trang: số trang trên mọi trang ---- */
        .page-footer {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5px;
            color: #555;
            border-top: 0.5px solid #bbb;
            padding-top: 3px;
        }
        .page-footer table { width: 100%; border: 0; margin: 0; }
        .page-footer td { border: 0; padding: 0; }
        .page-footer .right { text-align: right; }
        .page-num:after { content: counter(page); }

        /* ---- Quốc hiệu ---- */
        .national { text-align: center; margin-bottom: 14px; }
        .national .country {
            font-weight: bold; font-size: 12.5px; text-transform: uppercase; letter-spacing: .2px;
        }
        .national .motto { font-weight: bold; font-size: 12.5px; }
        .national .rule {
            width: 160px; border-bottom: 1px solid #000; margin: 3px auto 0; height: 0;
        }

        /* ---- Tiêu đề ---- */
        h1, h2 {
            font-size: 15px; font-weight: bold; text-align: center;
            text-transform: uppercase; margin: 16px 0 2px; line-height: 1.35;
        }
        .doc-number { text-align: center; font-style: italic; font-size: 11.5px; margin: 0 0 12px; }

        h3 {
            font-size: 12.5px; font-weight: bold; margin: 14px 0 5px;
            text-transform: uppercase; text-align: left; page-break-after: avoid;
        }

        p { margin: 0 0 7px; }
        strong { font-weight: bold; }

        /* ---- Bảng ---- */
        table {
            width: 100%; border-collapse: collapse; margin: 8px 0 12px;
            font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px;
            page-break-inside: avoid;
        }
        th, td {
            border: 0.75px solid #333; padding: 5px 7px;
            text-align: left; vertical-align: top; line-height: 1.45;
        }
        th { background: #e8e8e8; font-weight: bold; text-align: center; }

        /* ---- Khối chữ ký ---- */
        .signatures { border: 0; margin-top: 18px; page-break-inside: avoid; }
        .signatures td {
            border: 0; width: 50%; text-align: center; vertical-align: top;
            font-family: 'DejaVu Serif', serif; font-size: 11.5px;
        }
        .sig-role { font-weight: bold; text-transform: uppercase; }
        .sig-hint { font-style: italic; font-size: 10.5px; color: #333; }
        .sig-space { height: 62px; }
        .sig-img { height: 58px; margin-top: 2px; }
        .sig-name { font-weight: bold; }
        .sig-meta { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #444; }
        .sig-unsigned { font-style: italic; color: #666; }

        /* ---- Biên bản chứng thực ---- */
        .cert-intro {
            font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #444;
            text-align: left; margin-bottom: 10px;
        }
        .kv th { width: 36%; text-align: left; background: #f2f2f2; }
        .hash { font-size: 7.5px; word-break: break-all; line-height: 1.35; }
        .note {
            font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #444;
            text-align: left; margin: 4px 0 12px;
        }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

<div class="page-footer">
    <table>
        <tr>
            <td>Hợp đồng số {{ $contract_code }}</td>
            <td class="right">Trang <span class="page-num"></span></td>
        </tr>
    </table>
</div>

@foreach ($stages as $stage)
    @if (! $loop->first)
        <div class="page-break"></div>
    @endif

    {{-- Quốc hiệu nằm trong MẪU hợp đồng (contracts/defaults/main), không lặp lại ở đây —
         trang web cũng phải thấy quốc hiệu, nên nó thuộc về nội dung chứ không thuộc layout. --}}
    {!! $stage['html'] !!}

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-role">Đại diện Bên A</div>
                <div class="sig-hint">(Ký, ghi rõ họ tên)</div>
                <div class="sig-space"></div>
                <div class="sig-name">Phạm Văn Khánh</div>
            </td>
            <td>
                <div class="sig-role">Bên B (Khách thuê)</div>
                <div class="sig-hint">(Ký, ghi rõ họ tên)</div>
                @if ($stage['signature_data'])
                    <div><img class="sig-img" src="{{ $stage['signature_data'] }}" alt=""></div>
                    <div class="sig-name">{{ $customer_name }}</div>
                    <div class="sig-meta">Ký điện tử lúc {{ $stage['signed_at'] }}</div>
                @else
                    {{-- Phải nói rõ CHƯA KÝ. Để trống trơn thì người đọc dễ tưởng đã ký đủ. --}}
                    <div class="sig-space"></div>
                    <div class="sig-unsigned">(Chưa ký)</div>
                @endif
            </td>
        </tr>
    </table>
@endforeach

<div class="page-break"></div>
<h2>Biên bản chứng thực chữ ký điện tử</h2>
<p class="doc-number">Đính kèm Hợp đồng số {{ $contract_code }}</p>

<p class="cert-intro">Trang này do hệ thống sinh tự động, ghi lại dấu vết của quá trình ký điện tử
    theo Luật Giao dịch điện tử số 20/2023/QH15. Đây là bộ phận không tách rời của Hợp đồng.</p>

<table class="kv">
    <tr><th>Số hợp đồng</th><td>{{ $contract_code }}</td></tr>
    <tr><th>Mã đơn hàng</th><td>{{ $order_code }}</td></tr>
    <tr><th>Bên thuê</th><td>{{ $customer_name }} — {{ $customer_phone }}</td></tr>
    <tr><th>Email đã xác thực OTP</th><td>{{ $verified_email ?? 'chưa xác thực' }}</td></tr>
    <tr><th>Mở link hợp đồng lần đầu</th><td>{{ $first_viewed_at ?? 'chưa mở' }}</td></tr>
</table>

<h3>1. Dấu vết từng lần ký</h3>
<table>
    <thead>
        <tr>
            <th style="width:27%">Nội dung ký</th>
            <th style="width:16%">Thời điểm</th>
            <th style="width:14%">Địa chỉ IP</th>
            <th>Mã kiểm tra nội dung (SHA-256)</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($stages as $stage)
        <tr>
            <td>{{ $stage['label'] }}</td>
            <td>{{ $stage['signed_at'] ?? '—' }}</td>
            <td>{{ $stage['signed_ip'] ?? '—' }}</td>
            <td class="hash">{{ $stage['content_hash'] ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="note">Mã kiểm tra là dấu vân tay của đúng nội dung mà Bên B đã đọc tại thời điểm ký.
    Nội dung bị sửa dù chỉ một ký tự thì mã này đổi hoàn toàn, nên hai Bên luôn đối chiếu được
    bản mình giữ với bản của bên kia.</p>

<h3>2. Thiết bị dùng để ký</h3>
<table>
    @foreach ($stages as $stage)
        @if ($stage['signed_user_agent'])
            <tr>
                <th style="width:27%; text-align:left">{{ $stage['label'] }}</th>
                <td class="hash">{{ $stage['signed_user_agent'] }}</td>
            </tr>
        @endif
    @endforeach
</table>

<h3>3. Đối chiếu thanh toán</h3>
<p class="note">Hệ thống không lưu sao kê ngân hàng. Bằng chứng chuyển khoản nằm ở sao kê tài
    khoản của Bên A; các mục dưới đây là chỉ dẫn để tra đúng dòng giao dịch.</p>
<table class="kv">
    <tr><th>Nội dung chuyển khoản cần tra</th><td>{{ $transfer_content }}</td></tr>
    <tr><th>Tiền cọc theo hợp đồng</th><td>{{ $deposit_total }}</td></tr>
    <tr><th>Đã ghi nhận thu cọc</th><td>{{ $deposit_paid_at ?? 'chưa ghi nhận' }}</td></tr>
    <tr><th>Người ghi nhận</th><td>{{ $deposit_paid_by ?? '—' }}</td></tr>
</table>

</body>
</html>
