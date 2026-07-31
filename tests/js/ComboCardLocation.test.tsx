import ComboCard, { type ComboCardData } from '@/Components/site/ComboCard';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * Badge cơ sở trên thẻ combo (bopcamping-daet) — combo có kho RIÊNG nên phải nói rõ
 * bán ở đâu, giống thẻ sản phẩm. Dùng chung LocationBadges để hai thẻ không lệch nhau.
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
    name: 'Combo 1',
    slug: 'combo-1',
    combo_price: 10000,
    sum_individual: 85000,
    savings_amount: 75000,
    savings_percent: 88,
    suitable_for: 2,
};

describe('ComboCard — badge cơ sở', () => {
    it('một cơ sở thì hiện thẳng tên cơ sở', () => {
        render(
            <ComboCard
                c={{ ...BASE, locations: [{ slug: 'vinh', name: 'Vinh' }] }}
            />,
        );

        expect(screen.getByText('Vinh')).toBeInTheDocument();
        expect(screen.queryByText('Toàn hệ thống')).not.toBeInTheDocument();
    });

    it('nhiều cơ sở nhưng chưa đủ hết thì liệt kê từng cơ sở', () => {
        render(
            <ComboCard
                c={{
                    ...BASE,
                    locations: [
                        { slug: 'vinh', name: 'Vinh' },
                        { slug: 'ha-noi', name: 'Hà Nội' },
                    ],
                    all_locations: false,
                }}
            />,
        );

        expect(screen.getByText('Vinh')).toBeInTheDocument();
        expect(screen.getByText('Hà Nội')).toBeInTheDocument();
        expect(screen.queryByText('Toàn hệ thống')).not.toBeInTheDocument();
    });

    it('bán ở đủ mọi cơ sở thì gộp thành "Toàn hệ thống"', () => {
        render(
            <ComboCard
                c={{
                    ...BASE,
                    locations: [
                        { slug: 'vinh', name: 'Vinh' },
                        { slug: 'ha-noi', name: 'Hà Nội' },
                    ],
                    all_locations: true,
                }}
            />,
        );

        expect(screen.getByText('Toàn hệ thống')).toBeInTheDocument();
        expect(screen.queryByText('Vinh')).not.toBeInTheDocument();
    });

    /** 1 cơ sở mà nói "toàn hệ thống" là vô nghĩa — phải hiện tên nơi đó. */
    it('all_locations nhưng chỉ 1 cơ sở thì vẫn hiện tên cơ sở', () => {
        render(
            <ComboCard
                c={{
                    ...BASE,
                    locations: [{ slug: 'vinh', name: 'Vinh' }],
                    all_locations: true,
                }}
            />,
        );

        expect(screen.getByText('Vinh')).toBeInTheDocument();
        expect(screen.queryByText('Toàn hệ thống')).not.toBeInTheDocument();
    });

    it('không có cơ sở nào thì không render badge', () => {
        render(<ComboCard c={{ ...BASE, locations: [] }} />);

        expect(screen.queryByText('Toàn hệ thống')).not.toBeInTheDocument();
        // Thẻ vẫn render bình thường, chỉ không có badge.
        expect(screen.getByText('Combo 1')).toBeInTheDocument();
    });

    it('thiếu prop locations (dữ liệu cũ) cũng không vỡ thẻ', () => {
        render(<ComboCard c={BASE} />);

        expect(screen.getByText('Combo 1')).toBeInTheDocument();
        expect(screen.queryByText('Toàn hệ thống')).not.toBeInTheDocument();
    });

    /** Badge cơ sở phải sống chung được với badge "Hết trong khoảng này". */
    it('hiện cùng lúc với badge hết hàng', () => {
        render(
            <ComboCard
                c={{
                    ...BASE,
                    locations: [{ slug: 'vinh', name: 'Vinh' }],
                    available: 0,
                }}
            />,
        );

        expect(screen.getByText('Vinh')).toBeInTheDocument();
        expect(screen.getByText('Hết trong khoảng này')).toBeInTheDocument();
    });
});
