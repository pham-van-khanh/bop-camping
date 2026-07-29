@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $fmtDay = fn ($d) => \Illuminate\Support\Str::ucfirst($d->locale('vi')->isoFormat('dddd, DD/MM/YYYY'));
    $vnd = fn ($n) => number_format((int) $n, 0, ',', '.').'đ';
    $total = count($pickups) + count($returns);
@endphp
{{-- Mail NỘI BỘ cho shipper (bopcamping-5r5m): có SĐT/địa chỉ khách + tiền cần thu +
     ghi chú shipper. KHÔNG dùng lại cho khách. --}}
<x-mail.brand variant="green" eyebrow="Lịch của bạn" :heading="'Ngày '.$date->format('d/m')"
    :preheader="'Hôm '.$date->format('d/m').': '.count($pickups).' đơn cần giao, '.count($returns).' đơn cần thu.'">
    <p style="font-size:14.5px;line-height:1.65;margin:0 0 4px;color:#5a5445;">
        Chào {{ $shipper->name }}, lịch {{ $fmtDay($date) }} của bạn:
        <strong>{{ count($pickups) }}</strong> đơn cần giao ·
        <strong>{{ count($returns) }}</strong> đơn cần thu.
    </p>

    @if ($total === 0)
        <div style="margin:16px 0;background:#f6efd8;border-radius:14px;padding:16px 18px;font-size:14px;color:#5a5445;">
            Hôm nay bạn không có lượt nào. Nghỉ ngơi nhé 🏕
        </div>
    @endif

    @foreach ([['Cần giao', $pickups, 'giao'], ['Cần thu', $returns, 'thu']] as [$title, $rows, $verb])
        @if (count($rows) > 0)
            <div style="margin:18px 0 6px;font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">
                {{ $title }} ({{ count($rows) }})
            </div>
            @foreach ($rows as $row)
                <div style="margin:0 0 10px;border:1px solid #e9e2cf;border-radius:12px;padding:12px 14px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                        <tr>
                            <td style="font-family:{{ $mono }};font-size:17px;font-weight:800;color:#b3493a;">
                                {{ $row['time'] ?? 'chưa chốt giờ' }}
                            </td>
                            <td style="text-align:right;font-family:{{ $mono }};font-size:12.5px;color:#8a8170;">
                                {{ $row['code'] }}
                            </td>
                        </tr>
                    </table>
                    <div style="font-size:14.5px;font-weight:700;color:#2e2a20;margin-top:4px;">{{ $row['customer_name'] }}</div>
                    <div style="font-family:{{ $mono }};font-size:14px;color:#557a2b;margin-top:2px;">{{ $row['customer_phone'] }}</div>
                    @if ($row['customer_address'])
                        <div style="font-size:13.5px;color:#5a5445;margin-top:2px;">{{ $row['customer_address'] }}</div>
                    @endif

                    @if (count($row['items']) > 0)
                        <div style="margin-top:8px;border-top:1px solid #f0ebdd;padding-top:8px;font-size:13px;color:#2e2a20;">
                            @foreach ($row['items'] as $item)
                                {{ $item['name'] }} × {{ $item['quantity'] }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </div>
                    @endif

                    <div style="margin-top:8px;border-top:1px solid #f0ebdd;padding-top:8px;font-size:13.5px;">
                        @if ($verb === 'giao')
                            <strong style="font-family:{{ $mono }};color:#2e2a20;">Thu khi giao: {{ $vnd($row['amount_due']) }}</strong>
                        @else
                            <strong style="font-family:{{ $mono }};color:#2e2a20;">Hoàn cọc: {{ $vnd($row['deposit_total']) }}</strong>
                        @endif
                    </div>

                    @if ($row['schedule_note'])
                        <div style="margin-top:8px;background:#f8faf4;border-radius:9px;padding:8px 10px;font-size:12.5px;color:#5C6E47;">
                            <strong>Ghi chú:</strong> {{ $row['schedule_note'] }}
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    @endforeach

    <div style="margin:22px 0 12px;">
        <x-mail.button :href="route('shipper.schedule', ['date' => $date->toDateString()])">Mở lịch trên điện thoại</x-mail.button>
    </div>
    <p style="text-align:center;margin:0;font-size:12.5px;color:#8a8170;">
        Cần đổi gì trong lịch thì nhắn chủ shop nhé.
    </p>
</x-mail.brand>
