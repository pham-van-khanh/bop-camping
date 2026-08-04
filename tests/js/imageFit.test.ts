import { pickObjectFit } from '@/lib/imageFit';
import { describe, expect, it } from 'vitest';

// Khung ảnh chính ProductDetail trên desktop (lg:h-[680px], cột ~668px).
const BOX = { width: 668, height: 680 };
const RETINA = 2;

describe('pickObjectFit', () => {
    it('giữ cover khi ảnh gốc đủ lớn (không phải phóng to)', () => {
        // 1500x1500 → cover scale 0.453, ở DPR2 = 0.91x → vẫn nét.
        expect(pickObjectFit({ width: 1500, height: 1500 }, BOX, RETINA)).toBe(
            'cover',
        );
    });

    it('đổi sang contain cho ảnh dọc nhỏ (cover phóng 1.69x, contain chỉ 1.19x)', () => {
        expect(pickObjectFit({ width: 790, height: 1146 }, BOX, RETINA)).toBe(
            'contain',
        );
    });

    it('giữ cover cho ảnh 578x678 gần vuông — contain chỉ lợi 13%, không đáng đổi', () => {
        // Ca này chỉ upload lại ảnh gốc to hơn mới hết mờ (2.01x vs 2.31x đều mờ).
        expect(pickObjectFit({ width: 578, height: 678 }, BOX, RETINA)).toBe(
            'cover',
        );
    });

    it('giữ cover khi ảnh cùng tỉ lệ với khung — contain không nét hơn', () => {
        // Cùng tỉ lệ 668/680 nhưng chỉ bằng nửa kích thước: cover và contain
        // scale bằng nhau, đổi sang contain chỉ hụt khung mà không nét thêm.
        expect(pickObjectFit({ width: 334, height: 340 }, BOX, RETINA)).toBe(
            'cover',
        );
    });

    it('trên màn thường (DPR 1) ảnh 790px đã đủ nét → giữ cover', () => {
        expect(pickObjectFit({ width: 790, height: 1146 }, BOX, 1)).toBe(
            'cover',
        );
    });

    it('trả cover khi chưa đo được kích thước', () => {
        expect(pickObjectFit({ width: 0, height: 0 }, BOX, RETINA)).toBe(
            'cover',
        );
        expect(
            pickObjectFit(
                { width: 790, height: 1146 },
                { width: 0, height: 0 },
                RETINA,
            ),
        ).toBe('cover');
    });
});
