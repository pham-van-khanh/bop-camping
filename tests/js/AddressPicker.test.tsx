import AddressPicker, {
    type AddressValue,
} from '@/Components/site/AddressPicker';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-vj4x — AddressPicker (chỉ địa chỉ SAU sát nhập + ô khối tuỳ chọn).
 *
 * Quan trọng nhất là FALLBACK về ô text: nó là thứ giữ cho khách vẫn đặt được hàng khi
 * API địa chỉ của bên thứ ba chết. Kế đến là chuỗi ghép, vì customer_address mới là cái
 * shipper đọc — mã tỉnh/xã chỉ để thống kê.
 */

const mocks = vi.hoisted(() => ({
    getProvinces: vi.fn(),
    getWards: vi.fn(),
}));

vi.mock('@/lib/divisions', () => mocks);

const HANOI = {
    name: 'Thành phố Hà Nội',
    code: 1,
    codename: 'ha_noi',
    division_type: 'thành phố trung ương',
};
const NGHEAN = {
    name: 'Tỉnh Nghệ An',
    code: 40,
    codename: 'nghe_an',
    division_type: 'tỉnh',
};

const BA_DINH = {
    name: 'Phường Ba Đình',
    code: 4,
    codename: 'phuong_ba_dinh',
    division_type: 'phường',
};
const HOAN_KIEM = {
    name: 'Phường Hoàn Kiếm',
    code: 5,
    codename: 'phuong_hoan_kiem',
    division_type: 'phường',
};
const HUNG_BINH = {
    name: 'Phường Hưng Bình',
    code: 16600,
    codename: 'phuong_hung_binh',
    division_type: 'phường',
};

const EMPTY: AddressValue = {
    address: '',
    street: '',
    province_code: null,
    ward_code: null,
};

/**
 * AddressPicker là controlled component: `street` đọc từ prop `value`. Test PHẢI mô phỏng
 * cha có state thật (Cart.tsx làm `setData(d => ({...d, ...v}))`), không thì `value.street`
 * đứng yên và mọi phép ghép chuỗi sẽ sai — lỗi của test, không phải của component.
 */
function Harness({ onChange }: { onChange: (v: AddressValue) => void }) {
    const [value, setValue] = useState<AddressValue>(EMPTY);

    return (
        <AddressPicker
            value={value}
            onChange={(v) => {
                setValue(v);
                onChange(v);
            }}
        />
    );
}

function renderPicker() {
    const onChange = vi.fn();
    const utils = render(<Harness onChange={onChange} />);

    return { ...utils, onChange };
}

const optionIn = (selectLabel: string, name: string) =>
    within(screen.getByLabelText(selectLabel)).getByRole('option', { name });

const waitOption = (selectLabel: string, name: string) =>
    waitFor(() => expect(optionIn(selectLabel, name)).toBeInTheDocument());

/** Giá trị onChange gần nhất. */
const last = (onChange: ReturnType<typeof vi.fn>): AddressValue =>
    onChange.mock.calls[onChange.mock.calls.length - 1][0];

/** Chọn Hà Nội -> Ba Đình, trả về onChange để khẳng định tiếp. */
async function chonHaNoiBaDinh(user: ReturnType<typeof userEvent.setup>) {
    const { onChange } = renderPicker();

    await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');
    await user.selectOptions(screen.getByLabelText('Tỉnh / Thành phố'), '1');
    await waitOption('Xã / Phường', 'Phường Ba Đình');
    await user.selectOptions(screen.getByLabelText('Xã / Phường'), '4');

    return onChange;
}

describe('AddressPicker', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.getProvinces.mockResolvedValue([HANOI, NGHEAN]);
        mocks.getWards.mockImplementation(async (code: number) =>
            code === 1 ? [BA_DINH, HOAN_KIEM] : [HUNG_BINH],
        );
    });

    it('đổ danh sách tỉnh sau sát nhập vào select', async () => {
        renderPicker();

        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');
        expect(
            optionIn('Tỉnh / Thành phố', 'Tỉnh Nghệ An'),
        ).toBeInTheDocument();
    });

    it('chưa chọn tỉnh thì select xã bị khoá', async () => {
        renderPicker();

        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');
        expect(screen.getByLabelText('Xã / Phường')).toBeDisabled();
    });

    it('chọn tỉnh xong mới nạp xã của đúng tỉnh đó', async () => {
        const user = userEvent.setup();
        renderPicker();

        await waitOption('Tỉnh / Thành phố', 'Tỉnh Nghệ An');
        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '40',
        );

        await waitOption('Xã / Phường', 'Phường Hưng Bình');
        expect(mocks.getWards).toHaveBeenCalledWith(40);
        expect(screen.getByLabelText('Xã / Phường')).toBeEnabled();
    });

    it('ghép chuỗi đủ 4 phần theo thứ tự số nhà, khối, xã, tỉnh', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        await user.type(
            screen.getByLabelText('Khối / Xóm / Thôn (nếu có)'),
            'Khối 3',
        );
        await user.type(
            screen.getByLabelText('Số nhà, tên đường'),
            'Số 5 Trần Phú',
        );

        expect(last(onChange).address).toBe(
            'Số 5 Trần Phú, Khối 3, Phường Ba Đình, Thành phố Hà Nội',
        );
    });

    it('bỏ trống khối thì chuỗi không có dấu phẩy thừa', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        await user.type(
            screen.getByLabelText('Số nhà, tên đường'),
            'Số 5 Trần Phú',
        );

        expect(last(onChange).address).toBe(
            'Số 5 Trần Phú, Phường Ba Đình, Thành phố Hà Nội',
        );
    });

    it('gửi lên mã tỉnh + mã xã, và street tách riêng khỏi chuỗi', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        await user.type(
            screen.getByLabelText('Số nhà, tên đường'),
            'Số 5 Trần Phú',
        );

        const v = last(onChange);
        expect(v.province_code).toBe(1);
        expect(v.ward_code).toBe(4);
        expect(v.street).toBe('Số 5 Trần Phú');
    });

    /**
     * Đổi tỉnh mà giữ nguyên xã cũ thì địa chỉ sai tỉnh — ca dễ vỡ nhất của form 2 cấp.
     */
    it('đổi tỉnh thì xoá xã đã chọn và bỏ ward_code', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        expect(last(onChange).ward_code).toBe(4);

        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '40',
        );

        const v = last(onChange);
        expect(v.ward_code).toBeNull();
        expect(v.province_code).toBe(40);
        expect(v.address).not.toContain('Ba Đình');
        expect(screen.getByLabelText('Xã / Phường')).toHaveValue('');
    });

    it('ô khối là tuỳ chọn, không chặn và không tạo mã riêng', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        const khoi = screen.getByLabelText('Khối / Xóm / Thôn (nếu có)');
        expect(khoi).not.toBeRequired();

        await user.type(khoi, 'Xóm 4');
        const v = last(onChange);
        expect(v.address).toBe('Xóm 4, Phường Ba Đình, Thành phố Hà Nội');
        expect(Object.keys(v).sort()).toEqual([
            'address',
            'province_code',
            'street',
            'ward_code',
        ]);
    });

    /** Bố cục chủ shop chốt: chọn vùng trước, gõ chi tiết sau. */
    it('hai select nằm TRÊN ô số nhà trong DOM', async () => {
        const { container } = renderPicker();

        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        const fields = Array.from(container.querySelectorAll('select, input'));
        const nhan = fields.map((el) => el.getAttribute('aria-label'));

        expect(nhan).toEqual([
            'Tỉnh / Thành phố',
            'Xã / Phường',
            'Khối / Xóm / Thôn (nếu có)',
            'Số nhà, tên đường',
        ]);
    });

    it('không còn phần địa chỉ cũ (trước sát nhập)', async () => {
        renderPicker();

        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        expect(screen.queryByText(/trước sát nhập/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/địa chỉ cũ/i)).not.toBeInTheDocument();
    });

    // ---- Fallback: thứ giữ cho khách vẫn đặt được hàng khi API chết ----

    it('API tỉnh lỗi -> về ô text tự do, vẫn nhập và gửi được địa chỉ', async () => {
        mocks.getProvinces.mockRejectedValue(new Error('mạng chết'));
        const user = userEvent.setup();
        const { onChange } = renderPicker();

        const o = await screen.findByLabelText('Địa chỉ giao nhận');
        expect(
            screen.queryByLabelText('Tỉnh / Thành phố'),
        ).not.toBeInTheDocument();
        expect(screen.getByText(/nhập tay giúp nhé/i)).toBeInTheDocument();

        await user.type(o, 'Số 9 đường Nào Đó, TP Vinh');

        const v = last(onChange);
        expect(v.address).toBe('Số 9 đường Nào Đó, TP Vinh');
        expect(v.province_code).toBeNull();
        expect(v.ward_code).toBeNull();
    });

    it('API xã lỗi giữa chừng -> cũng rơi về ô text, không kẹt select rỗng', async () => {
        mocks.getWards.mockRejectedValue(new Error('mạng chết'));
        const user = userEvent.setup();
        renderPicker();

        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');
        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '1',
        );

        expect(
            await screen.findByLabelText('Địa chỉ giao nhận'),
        ).toBeInTheDocument();
    });

    it('hiện lỗi validate từ server và tô viền đỏ ô số nhà', async () => {
        render(
            <AddressPicker
                value={EMPTY}
                onChange={vi.fn()}
                error="Nhập địa chỉ giúp tụi mình"
            />,
        );

        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        expect(
            screen.getByText('Nhập địa chỉ giúp tụi mình'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Số nhà, tên đường').className).toContain(
            'border-red-400',
        );
    });
});
