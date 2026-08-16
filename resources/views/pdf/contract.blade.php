{{--
    Layout PDF hợp đồng (bopcamping-4jao).

    Viết bằng table + inline style, KHÔNG tái dùng component Tailwind: dompdf không hiểu
    flex/grid, dùng vào là vỡ bố cục mà chẳng có lỗi nào ném ra.

    font-family DejaVu Sans là BẮT BUỘC — thiếu là chữ tiếng Việt ra ô vuông trong im lặng.
    ContractPdfFontTest canh chỗ này.
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; line-height: 1.5; color: #1c1917; margin: 0; }
        h2 { font-size: 15px; margin: 0 0 4px; }
        h3 { font-size: 12px; margin: 12px 0 4px; }
        p { margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        th, td { border: 1px solid #a8a29e; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f5f5f4; font-size: 10px; }
        .page-break { page-break-before: always; }
        .sign-row { width: 100%; margin-top: 14px; }
        .sign-cell { width: 50%; text-align: center; border: 0; }
        .sign-img { height: 60px; }
        .muted { color: #78716c; font-size: 10px; }
        .cert th { width: 34%; background: #f5f5f4; }
    </style>
</head>
<body>

@foreach ($stages as $stage)
    @if (! $loop->first)
        <div class="page-break"></div>
    @endif

    {!! $stage['html'] !!}

    <table class="sign-row">
        <tr>
            <td class="sign-cell">
                <strong>ĐẠI DIỆN BÊN A</strong><br>
                <span class="muted">(Ký, ghi rõ họ tên)</span>
                <div style="height:60px"></div>
                <strong>Phạm Văn Khánh</strong>
            </td>
            <td class="sign-cell">
                <strong>BÊN B (KHÁCH THUÊ)</strong><br>
                <span class="muted">(Ký, ghi rõ họ tên)</span>
                @if ($stage['signature_data'])
                    <div><img class="sign-img" src="{{ $stage['signature_data'] }}" alt=""></div>
                    <strong>{{ $customer_name }}</strong><br>
                    <span class="muted">Ký điện tử lúc {{ $stage['signed_at'] }}</span>
                @else
                    {{-- Phải nói rõ CHƯA KÝ. Để trống trơn thì người đọc dễ tưởng đã ký đủ. --}}
                    <div style="height:60px"></div>
                    <strong>(Chưa ký)</strong>
                @endif
            </td>
        </tr>
    </table>
@endforeach

<div class="page-break"></div>
<h2>BIÊN BẢN CHỨNG THỰC</h2>
<p class="muted">Trang này do hệ thống sinh tự động, ghi lại dấu vết của quá trình ký điện tử
    theo Luật Giao dịch điện tử 2023.</p>

<table class="cert">
    <tr><th>Số hợp đồng</th><td>{{ $contract_code }}</td></tr>
    <tr><th>Mã đơn hàng</th><td>{{ $order_code }}</td></tr>
    <tr><th>Bên thuê</th><td>{{ $customer_name }} — {{ $customer_phone }}</td></tr>
    <tr><th>Email đã xác thực OTP</th><td>{{ $verified_email ?? 'chưa xác thực' }}</td></tr>
    <tr><th>Mở link lần đầu</th><td>{{ $first_viewed_at ?? 'chưa mở' }}</td></tr>
</table>

<h3>Dấu vết từng lần ký</h3>
<table>
    <thead>
        <tr><th>Nội dung</th><th>Thời điểm ký</th><th>IP</th><th>Mã kiểm tra nội dung (SHA-256)</th></tr>
    </thead>
    <tbody>
    @foreach ($stages as $stage)
        <tr>
            <td>{{ $stage['label'] }}</td>
            <td>{{ $stage['signed_at'] ?? '—' }}</td>
            <td>{{ $stage['signed_ip'] ?? '—' }}</td>
            <td style="font-size:8px; word-break:break-all;">{{ $stage['content_hash'] ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="muted">Mã kiểm tra là dấu vân tay của đúng nội dung mà Bên B đã đọc tại thời điểm ký.
    Nội dung bị sửa dù chỉ một ký tự thì mã này đổi hoàn toàn.</p>

<h3>Thiết bị dùng để ký</h3>
<table>
    @foreach ($stages as $stage)
        @if ($stage['signed_user_agent'])
            <tr>
                <th style="width:34%">{{ $stage['label'] }}</th>
                <td style="font-size:8px">{{ $stage['signed_user_agent'] }}</td>
            </tr>
        @endif
    @endforeach
</table>

<h3>Đối chiếu thanh toán</h3>
<p class="muted">Hệ thống KHÔNG lưu sao kê ngân hàng. Bằng chứng chuyển khoản nằm ở sao kê tài
    khoản của Bên A; các mục dưới đây là chỉ dẫn để tra đúng dòng giao dịch.</p>
<table class="cert">
    <tr><th>Nội dung chuyển khoản cần tra</th><td>{{ $transfer_content }}</td></tr>
    <tr><th>Tiền cọc theo hợp đồng</th><td>{{ $deposit_total }}</td></tr>
    <tr><th>Đã ghi nhận thu cọc</th><td>{{ $deposit_paid_at ?? 'chưa ghi nhận' }}</td></tr>
    <tr><th>Người ghi nhận</th><td>{{ $deposit_paid_by ?? '—' }}</td></tr>
</table>

</body>
</html>
