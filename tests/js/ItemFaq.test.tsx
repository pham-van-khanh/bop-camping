import FaqRowsEditor, { FAQ_MAX } from '@/Components/admin/FaqRowsEditor';
import ItemFaq from '@/Components/site/ItemFaq';
import { fireEvent, render, screen } from '@testing-library/react';
import { renderToString } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';

/** bopcamping-0345 — FAQ riêng của sản phẩm / combo. */

const FAQS = [
    { question: 'Lều này ngủ được mấy người?', answer: '2–3 người lớn.' },
    { question: 'Chở xe máy được không?', answer: 'Được.' },
];

describe('ItemFaq (trang khách)', () => {
    it('không có câu nào → không vẽ gì', () => {
        const { container } = render(<ItemFaq faqs={[]} />);
        expect(container).toBeEmptyDOMElement();
        expect(render(<ItemFaq faqs={null} />).container).toBeEmptyDOMElement();
    });

    it('hiện mọi câu hỏi dưới một tiêu đề mục', () => {
        render(<ItemFaq faqs={FAQS} />);
        expect(
            screen.getByRole('heading', {
                level: 2,
                name: /câu hỏi thường gặp/i,
            }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('heading', { level: 3 })).toHaveLength(2);
    });

    it('SSR: câu trả lời nằm sẵn trong HTML kể cả câu đang gập', () => {
        const html = renderToString(<ItemFaq faqs={FAQS} />);
        expect(html).toContain('2–3 người lớn.');
        expect(html).toContain('Được.');
    });
});

describe('FaqRowsEditor (admin)', () => {
    it('thêm, sửa, xoá dòng qua onChange', () => {
        const onChange = vi.fn();
        const { rerender } = render(
            <FaqRowsEditor rows={[]} onChange={onChange} errors={{}} />,
        );
        fireEvent.click(screen.getByRole('button', { name: '+ Thêm câu hỏi' }));
        expect(onChange).toHaveBeenLastCalledWith([
            { question: '', answer: '' },
        ]);

        rerender(<FaqRowsEditor rows={FAQS} onChange={onChange} errors={{}} />);
        fireEvent.change(screen.getByLabelText('Trả lời 2'), {
            target: { value: 'Được, túi dài 98cm.' },
        });
        expect(onChange).toHaveBeenLastCalledWith([
            FAQS[0],
            { ...FAQS[1], answer: 'Được, túi dài 98cm.' },
        ]);

        fireEvent.click(screen.getByRole('button', { name: 'Xoá câu hỏi 1' }));
        expect(onChange).toHaveBeenLastCalledWith([FAQS[1]]);
    });

    it('đủ số câu tối đa thì ẩn nút thêm; hiện lỗi server đầu tiên', () => {
        render(
            <FaqRowsEditor
                rows={Array.from({ length: FAQ_MAX }, () => FAQS[0])}
                onChange={() => {}}
                errors={{ 'faqs.0.answer': 'Câu trả lời không được bỏ trống.' }}
            />,
        );
        expect(
            screen.queryByRole('button', { name: '+ Thêm câu hỏi' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText('Câu trả lời không được bỏ trống.'),
        ).toBeInTheDocument();
    });
});
