import ComboCard, { type ComboCardData } from '@/Components/site/ComboCard';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-hjde — ảnh cover combo trước đây serve file gốc, không có srcset nên
 * browser luôn tải bản lớn nhất bất kể kích thước hiển thị. Giữ test này để đảm bảo
 * ComboCard thực sự render srcSet/sizes khi backend gửi kèm, giống ProductCard.
 */

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...p }: { children: React.ReactNode }) => (
        <a {...p}>{children}</a>
    ),
}));
vi.mock('framer-motion', () => ({
    motion: {
        div: ({ children }: { children: React.ReactNode }) => (
            <div>{children}</div>
        ),
    },
}));

const BASE: ComboCardData = {
    id: 1,
    name: 'Combo Cặp Đôi',
    slug: 'combo-cap-doi',
    combo_price: 10000,
    sum_individual: 85000,
    savings_amount: 75000,
    savings_percent: 88,
    suitable_for: 2,
};

describe('ComboCard — srcset ảnh cover', () => {
    it('có image_srcset thì gắn vào thẻ img cùng sizes và lazy load', () => {
        render(
            <ComboCard
                c={{
                    ...BASE,
                    image: '/storage/combos/cover-1600.webp',
                    image_srcset:
                        '/storage/combos/cover-400.webp 400w, /storage/combos/cover-1600.webp 1600w',
                }}
            />,
        );

        const img = screen.getByRole('img', { name: 'Combo Cặp Đôi' });
        expect(img).toHaveAttribute(
            'srcset',
            '/storage/combos/cover-400.webp 400w, /storage/combos/cover-1600.webp 1600w',
        );
        expect(img).toHaveAttribute('sizes');
        expect(img).toHaveAttribute('loading', 'lazy');
    });

    it('ảnh chưa backfill (không có image_srcset) vẫn render bình thường, không vỡ thẻ', () => {
        render(
            <ComboCard c={{ ...BASE, image: '/storage/combos/cover.jpg' }} />,
        );

        const img = screen.getByRole('img', { name: 'Combo Cặp Đôi' });
        expect(img).not.toHaveAttribute('srcset');
        expect(img).toHaveAttribute('src', '/storage/combos/cover.jpg');
    });

    it('không có ảnh nào thì không render thẻ img', () => {
        render(<ComboCard c={BASE} />);

        expect(screen.queryByRole('img')).not.toBeInTheDocument();
    });
});
