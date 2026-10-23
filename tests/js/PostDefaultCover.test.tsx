import PostCard from '@/Components/site/PostCard';
import PostDefaultCover from '@/Components/site/PostDefaultCover';
import type { PostCardData } from '@/types/post';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * Bài Cẩm nang chưa có ảnh bìa → hiện ảnh bìa mặc định kèm tiêu đề bài in đè lên.
 * Bài đã có ảnh thì giữ nguyên ảnh của bài, không đè chữ.
 */

vi.mock('@inertiajs/react', () => ({
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

const post = (over: Partial<PostCardData> = {}): PostCardData => ({
    id: 1,
    title: 'Checklist đồ cắm trại cho người mới',
    slug: 'checklist',
    excerpt: null,
    cover: null,
    published_at: '2026-10-01T00:00:00+07:00',
    published_label: '01/10/2026',
    reading_minutes: 3,
    author: 'BỐP CAMPING',
    ...over,
});

describe('thẻ bài Cẩm nang', () => {
    it('chưa có ảnh bìa: dùng ảnh mặc định và in tiêu đề lên ảnh', () => {
        const { container } = render(<PostCard post={post()} />);

        const img = container.querySelector('img')!;
        expect(img).toHaveAttribute(
            'src',
            '/images/cam-nang/cover-default-1600.webp',
        );
        expect(img.getAttribute('srcset')).toContain('cover-default-800.webp');
        expect(img).toHaveAttribute('loading', 'lazy');

        const overlay = screen.getByTestId('post-default-cover-title');
        expect(overlay).toHaveTextContent(
            'Checklist đồ cắm trại cho người mới',
        );
        // Tiêu đề đã có ở heading của thẻ — chữ trên ảnh chỉ để nhìn, không đọc lại.
        expect(overlay).toHaveAttribute('aria-hidden', 'true');
        expect(img).toHaveAttribute('alt', '');
        expect(
            screen.getAllByText('Checklist đồ cắm trại cho người mới'),
        ).toHaveLength(2);
        expect(
            screen.getByRole('heading', {
                name: 'Checklist đồ cắm trại cho người mới',
            }),
        ).toBeInTheDocument();
    });

    it('đã có ảnh bìa: giữ ảnh của bài, không đè tiêu đề', () => {
        const { container } = render(
            <PostCard
                post={post({
                    cover: { url: '/storage/posts/a.webp', srcset: null },
                })}
            />,
        );

        expect(container.querySelector('img')).toHaveAttribute(
            'src',
            '/storage/posts/a.webp',
        );
        expect(
            screen.queryByTestId('post-default-cover-title'),
        ).not.toBeInTheDocument();
    });
});

describe('bìa mặc định ở đầu trang chi tiết', () => {
    it('tải ưu tiên (ảnh LCP), không lazy', () => {
        const { container } = render(
            <PostDefaultCover title="Cắm trại mùa mưa" variant="hero" />,
        );

        const img = container.querySelector('img')!;
        expect(img).toHaveAttribute('fetchpriority', 'high');
        expect(img).not.toHaveAttribute('loading');
        expect(
            screen.getByTestId('post-default-cover-title'),
        ).toHaveTextContent('Cắm trại mùa mưa');
    });
});
