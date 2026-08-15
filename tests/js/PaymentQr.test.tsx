import PaymentQr, { type PaymentQrData } from '@/Components/PaymentQr';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-55rh — khối QR chuyển khoản.
 *
 * Hai thứ được khoá lại ở đây:
 *   1. Nội dung CK phải hiện dạng CHỮ, không chỉ nằm trong ảnh. Đối soát tiền ở dự án
 *      này làm TAY (không có webhook), nên đây là chuỗi duy nhất admin dò trong sao kê —
 *      chôn nó vào ảnh là bắt người ta tự đoán.
 *   2. Nút tải ảnh CHỈ hiện khi backend đưa download_url, tức chỉ ở màn admin. Khách
 *      không cần tải ảnh, đó là công cụ để admin gửi qua Zalo.
 */

const qr: PaymentQrData = {
    url: 'https://qr.sepay.vn/img?acc=QRPSEP1ZZZZ55400303&bank=Vietcombank&amount=800000&des=BOP1485E3',
    amount: 800000,
    content: 'BOP1485E3',
};

describe('PaymentQr', () => {
    it('không vẽ gì khi đơn không có QR', () => {
        const { container } = render(<PaymentQr qr={null} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('hiện ảnh QR, số tiền và nội dung chuyển khoản', () => {
        render(<PaymentQr qr={qr} />);

        expect(screen.getByRole('img')).toHaveAttribute('src', qr.url);
        expect(screen.getByText('800.000đ')).toBeInTheDocument();
        expect(screen.getByText('BOP1485E3')).toBeInTheDocument();
    });

    it('không có nút tải khi thiếu download_url', () => {
        render(<PaymentQr qr={qr} />);

        expect(
            screen.queryByRole('link', { name: /tải ảnh/i }),
        ).not.toBeInTheDocument();
    });

    it('hiện nút tải trỏ đúng link khi backend đưa download_url', () => {
        render(
            <PaymentQr
                qr={{ ...qr, download_url: `${qr.url}&download=true` }}
            />,
        );

        expect(screen.getByRole('link', { name: /tải ảnh/i })).toHaveAttribute(
            'href',
            `${qr.url}&download=true`,
        );
    });

    it('đổi được tiêu đề để phân biệt từng đợt của đơn gộp', () => {
        render(<PaymentQr qr={qr} title="Chuyển khoản đợt 2" />);

        expect(screen.getByText('Chuyển khoản đợt 2')).toBeInTheDocument();
    });
});
