import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import DraftMediaGallery from '@/Components/admin/DraftMediaGallery';
import MediaGallery from '@/Components/admin/MediaGallery';
import MediaPickerModal from '@/Components/admin/MediaPickerModal';

/**
 * bopcamping-7czf — gallery ảnh nằm BÊN TRONG <form> của ProductForm, nên mọi nút
 * trong nó phải là type="button".
 *
 * Lỗi gốc: các nút "Chọn ảnh có sẵn", "Upload ảnh/video", "Huỷ", "Thêm N ảnh", "×"
 * và nút xoá ảnh đều thiếu type → mặc định HTML là submit → bấm chọn ảnh là submit
 * luôn cả form sản phẩm (ở màn thêm mới thì bung lỗi validation, ở màn sửa thì âm
 * thầm gửi request cập nhật). Đúng triệu chứng "không thực hiện được chức năng chọn ảnh".
 */

vi.mock('@inertiajs/react', () => ({
    router: { post: vi.fn(), delete: vi.fn(), reload: vi.fn() },
    usePage: () => ({ props: { mediaLibrary: [] } }),
}));

// framer-motion Reorder không cần thiết cho phép kiểm này.
vi.mock('framer-motion', () => ({
    Reorder: {
        Group: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
        Item: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    },
}));

// route() là global do Ziggy cung cấp ở runtime.
(globalThis as unknown as { route: (n: string, p?: unknown) => string }).route = (
    n: string,
) => `/${n}`;

const LIBRARY = [
    {
        type: 'product' as const,
        id: 1,
        name: 'Lều A',
        images: [{ id: 10, path: '/storage/a.jpg', type: 'image' as const }],
    },
];

/** Mọi <button> render ra phải có type="button" — không được để rơi về submit. */
const expectNoSubmitButtons = () => {
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
    buttons.forEach((b) => expect(b).toHaveAttribute('type', 'button'));
};

describe('nút trong gallery ảnh không được submit form', () => {
    it('MediaGallery (màn sửa): mọi nút đều type="button"', () => {
        render(
            <MediaGallery
                kind="product"
                itemId={1}
                images={[
                    { id: 5, path: '/storage/x.jpg', sort_order: 1, type: 'image' },
                ]}
                label="Ảnh phụ"
            />,
        );
        expectNoSubmitButtons();
    });

    it('DraftMediaGallery (màn thêm mới): mọi nút đều type="button"', () => {
        render(
            <DraftMediaGallery
                label="Ảnh phụ"
                value={{ files: [], sources: [{ type: 'product', id: 10 }] }}
                onChange={vi.fn()}
            />,
        );
        expectNoSubmitButtons();
    });

    it('MediaPickerModal: ×, Huỷ và Thêm N ảnh đều type="button"', () => {
        render(
            <MediaPickerModal
                open
                loading={false}
                library={LIBRARY}
                submitting={false}
                onClose={vi.fn()}
                onConfirm={vi.fn()}
            />,
        );
        expectNoSubmitButtons();
    });

    it('bấm "Chọn ảnh có sẵn" trong form KHÔNG làm form submit', async () => {
        const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
        render(
            <form onSubmit={onSubmit}>
                <DraftMediaGallery
                    label="Ảnh phụ"
                    value={{ files: [], sources: [] }}
                    onChange={vi.fn()}
                />
            </form>,
        );

        await userEvent.click(
            screen.getByRole('button', { name: /Chọn ảnh có sẵn/ }),
        );

        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('bấm "Upload ảnh/video" trong form KHÔNG làm form submit', async () => {
        const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
        render(
            <form onSubmit={onSubmit}>
                <DraftMediaGallery
                    label="Ảnh phụ"
                    value={{ files: [], sources: [] }}
                    onChange={vi.fn()}
                />
            </form>,
        );

        await userEvent.click(
            screen.getByRole('button', { name: /Upload ảnh\/video/ }),
        );

        expect(onSubmit).not.toHaveBeenCalled();
    });
});
