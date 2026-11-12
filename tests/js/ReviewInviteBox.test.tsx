import { ReviewInviteBox } from '@/Pages/Admin/orderShared';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/** bopcamping-tme3 — khối "Mời đánh giá" ở chi tiết đơn: chép link / lời nhắn cho Zalo. */

vi.mock('@inertiajs/react', () => ({
    router: {},
    usePage: () => ({ props: {} }),
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
}));

const URL_ = 'https://bopcamping.com/danh-gia/abc123';
const writeText = vi.fn(() => Promise.resolve());

describe('ReviewInviteBox', () => {
    beforeEach(() => {
        writeText.mockClear();
        Object.assign(navigator, { clipboard: { writeText } });
    });

    it('đơn không email: nói rõ chưa được mời, chép lời nhắn có tên khách + link', async () => {
        render(
            <ReviewInviteBox
                invite={{ url: URL_, submitted: false, emailed_at: null }}
                customerName="Anh Nam"
            />,
        );

        expect(screen.getByText(/chưa được mời/)).toBeInTheDocument();
        expect(screen.getByLabelText('Link mời đánh giá')).toHaveValue(URL_);

        fireEvent.click(
            screen.getByRole('button', { name: 'Sao chép lời nhắn' }),
        );
        expect(writeText).toHaveBeenCalledWith(
            expect.stringMatching(/^Chào Anh Nam,.*danh-gia\/abc123$/),
        );
        expect(
            await screen.findByRole('button', { name: /Đã chép lời nhắn/ }),
        ).toBeInTheDocument();
    });

    it('chỉ chép link', () => {
        render(
            <ReviewInviteBox
                invite={{ url: URL_, submitted: false, emailed_at: null }}
                customerName="Anh Nam"
            />,
        );
        fireEvent.click(
            screen.getByRole('button', { name: 'Chỉ sao chép link' }),
        );
        expect(writeText).toHaveBeenCalledWith(URL_);
    });

    it('đã gửi mail: ghi ngày gửi', () => {
        render(
            <ReviewInviteBox
                invite={{
                    url: URL_,
                    submitted: false,
                    emailed_at: '01/11/2026',
                }}
                customerName="Chị Hoa"
            />,
        );
        expect(
            screen.getByText(/Đã gửi mail mời ngày 01\/11\/2026/),
        ).toBeInTheDocument();
    });

    it('khách đã đánh giá: không còn nút chép', () => {
        render(
            <ReviewInviteBox
                invite={{ url: URL_, submitted: true, emailed_at: null }}
                customerName="Chị Hoa"
            />,
        );
        expect(screen.getByText(/đã gửi đánh giá/)).toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
