import Footer from '@/Components/site/Footer';
import { EVENTS, on } from '@/lib/bus';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Link "Cài đặt cookie" ở footer (bopcamping-vqwr) — đường duy nhất để khách đổi ý
 * sau khi đã bấm Đồng ý/Từ chối ở banner. Không có nó thì đồng ý là một chiều.
 */

const state = vi.hoisted(() => ({
    analytics: null as { ga_id: string | null; gtm_id: string | null } | null,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { site: null, analytics: state.analytics } }),
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string;
        children: React.ReactNode;
    }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

vi.mock('@/Components/Logo', () => ({
    default: () => <img alt="Bốp Camping" />,
}));

beforeEach(() => {
    state.analytics = null;
});

describe('footer: cài đặt cookie', () => {
    it('ẩn khi shop không dùng công cụ phân tích nào', () => {
        render(<Footer />);
        expect(
            screen.queryByRole('button', { name: 'Cài đặt cookie' }),
        ).not.toBeInTheDocument();
    });

    it('bấm thì mở lại banner đồng ý cookie', async () => {
        state.analytics = { ga_id: 'G-TEST1234', gtm_id: null };
        const opened = vi.fn();
        const off = on(EVENTS.openCookieSettings, opened);
        const user = userEvent.setup();
        render(<Footer />);

        await user.click(
            screen.getByRole('button', { name: 'Cài đặt cookie' }),
        );

        expect(opened).toHaveBeenCalledTimes(1);
        off();
    });
});
