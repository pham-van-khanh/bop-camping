import ContractPanel, { type ContractBlock } from '@/Pages/Admin/ContractPanel';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-4jao — khối hợp đồng trên màn chi tiết đơn của admin.
 *
 * Kiểm ba điều dễ sai và tốn tiền nếu sai: đơn cha không được hiện form (hợp đồng bám đơn
 * con), nhãn "chưa ký" phải hiện đúng giai đoạn còn thiếu, và nút sao chép phải chép ĐÚNG
 * link ký — chép nhầm là chủ shop gửi cho khách một đường dẫn chết.
 */

const mocks = vi.hoisted(() => ({
    post: vi.fn(),
    setData: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    useForm: () => ({
        data: { id_number: '', id_issued_on: '', id_issued_place: '' },
        setData: mocks.setData,
        post: mocks.post,
        errors: {},
        processing: false,
    }),
}));

// route() là helper toàn cục do Ziggy cắm vào window ở app thật.
globalThis.route = ((name: string, param: unknown) =>
    `/${name}/${param}`) as unknown as typeof globalThis.route;

const CONTRACT: ContractBlock = {
    code: 'BOP-1/HĐTTB',
    sign_url: 'http://localhost/hop-dong/abc123',
    signed_stages: ['main'],
    stage_labels: {
        main: 'Hợp đồng thuê thiết bị',
        handover: 'Phụ lục A — Biên bản bàn giao',
        return: 'Phụ lục B — Biên bản nhận lại thiết bị',
    },
    id_number: '040202015437',
    id_issued_on: '2021-03-15',
    id_issued_place: 'Cục CSQLHC về TTXH',
    has_pdf: true,
};

describe('ContractPanel', () => {
    beforeEach(() => vi.clearAllMocks());

    it('đơn cha không hiện khối hợp đồng', () => {
        const { container } = render(
            <ContractPanel orderId={1} contract={null} isParent />,
        );

        // Đơn cha chỉ gom đợt, không có ngày/đồ riêng — hợp đồng lập trên từng đơn con.
        expect(container).toBeEmptyDOMElement();
    });

    it('đơn chưa lập hợp đồng thì nút là "Lập hợp đồng", chưa có link', () => {
        render(<ContractPanel orderId={1} contract={null} isParent={false} />);

        expect(
            screen.getByRole('button', { name: 'Lập hợp đồng' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Sao chép link/ }),
        ).not.toBeInTheDocument();
    });

    it('hiện đúng giai đoạn đã ký và giai đoạn còn thiếu', () => {
        render(
            <ContractPanel orderId={1} contract={CONTRACT} isParent={false} />,
        );

        expect(
            screen.getByText(/✓ Hợp đồng thuê thiết bị/),
        ).toBeInTheDocument();
        expect(screen.getByText(/Chưa ký — Phụ lục A/)).toBeInTheDocument();
        expect(screen.getByText(/Chưa ký — Phụ lục B/)).toBeInTheDocument();
    });

    it('nút sao chép chép đúng link ký', async () => {
        // userEvent.setup() tự cài clipboard giả của nó, và navigator.clipboard sau đó chỉ
        // còn getter — gán đè sẽ ném lỗi. Theo dõi bằng spy trên chính stub đó.
        const user = userEvent.setup();
        const writeText = vi
            .spyOn(navigator.clipboard, 'writeText')
            .mockResolvedValue(undefined);

        render(
            <ContractPanel orderId={1} contract={CONTRACT} isParent={false} />,
        );
        await user.click(screen.getByRole('button', { name: /Sao chép link/ }));

        // Chép nhầm là chủ shop gửi cho khách một đường dẫn chết.
        expect(writeText).toHaveBeenCalledWith(
            'http://localhost/hop-dong/abc123',
        );
    });
});
