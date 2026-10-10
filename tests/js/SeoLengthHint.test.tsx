import SeoLengthHint from '@/Components/admin/SeoLengthHint';
import {
    charCount,
    comboTitle,
    plainText,
    productTitle,
    staticPageTitle,
} from '@/lib/seo';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-b4c3 — bộ đếm ký tự SEO trong form admin. Title xem trước phải ghép y như
 * server (ProductController / ComboController / SeoService::withBrand), nếu không con
 * số admin thấy sẽ lệch với thứ Google thật sự hiện.
 */

describe('ghép title giống server', () => {
    it('sản phẩm: "<tên> — Thuê tại BỐP CAMPING"', () => {
        expect(productTitle('Lều 2 người ')).toBe(
            'Lều 2 người — Thuê tại BỐP CAMPING',
        );
    });

    it('combo: "<tên> — Thuê trọn bộ tại BỐP CAMPING"', () => {
        expect(comboTitle('Combo Gia Đình')).toBe(
            'Combo Gia Đình — Thuê trọn bộ tại BỐP CAMPING',
        );
    });

    it('trang tĩnh: chỉ nối brand khi tiêu đề chưa có (kể cả viết không dấu)', () => {
        expect(staticPageTitle('Chính sách bảo mật')).toBe(
            'Chính sách bảo mật | BỐP CAMPING',
        );
        expect(staticPageTitle('Về Bop Camping')).toBe('Về Bop Camping');
        expect(staticPageTitle('Về Bốp Camping')).toBe('Về Bốp Camping');
        expect(staticPageTitle('  ')).toBe('BỐP CAMPING');
    });

    it('đếm theo ký tự Unicode, gỡ HTML trước khi đếm mô tả', () => {
        expect(charCount('Lều')).toBe(3);
        expect(plainText('<p>Lều</p><p>sạch&nbsp;sẽ</p>')).toBe('Lều sạch sẽ');
    });
});

describe('<SeoLengthHint kind="title">', () => {
    it('không cảnh báo khi title ≤ 60 ký tự', () => {
        const title = productTitle('Lều 2 người');
        render(<SeoLengthHint kind="title" value={title} />);

        expect(
            screen.getByText(`${charCount(title)}/60`, { exact: false }),
        ).toBeInTheDocument();
        expect(screen.queryByText(/dài quá/)).not.toBeInTheDocument();
    });

    it('cảnh báo và nói rõ cần bớt bao nhiêu ký tự khi > 60', () => {
        // Tên thật từng bị audit: title 86 ký tự.
        const title = productTitle(
            'Ghế thư giãn gấp gọn Naturehike Nightfall T05 dành cho cắm trại',
        );
        const over = charCount(title) - 60;
        render(<SeoLengthHint kind="title" value={title} />);

        expect(
            screen.getByText(new RegExp(`rút gọn khoảng ${over} ký tự`)),
        ).toBeInTheDocument();
    });
});

describe('<SeoLengthHint kind="description">', () => {
    it('nhắc khi mô tả quá ngắn', () => {
        render(<SeoLengthHint kind="description" value="Lều tốt." />);
        expect(screen.getByText(/Hơi ngắn/)).toBeInTheDocument();
    });

    it('khen khi 120–155 ký tự', () => {
        render(<SeoLengthHint kind="description" value={'a'.repeat(140)} />);
        expect(screen.getByText(/vừa đẹp/)).toBeInTheDocument();
    });

    it('mô tả dài chỉ nhắc đặt ý chính ở câu đầu', () => {
        render(<SeoLengthHint kind="description" value={'a'.repeat(400)} />);
        expect(screen.getByText(/ý chính ở câu đầu/)).toBeInTheDocument();
    });

    it('để trống thì báo sẽ dùng mô tả tự sinh', () => {
        render(<SeoLengthHint kind="description" value="" />);
        expect(screen.getByText(/tự sinh/)).toBeInTheDocument();
    });
});
