import AddressPicker, {
    type AddressValue,
} from '@/Components/site/AddressPicker';
import { render, screen, waitFor } from '@testing-library/react';
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

/**
 * Không còn <select> native (danh sách của nó OS vẽ, không style được) nên test phải
 * MỞ dropdown rồi mới thấy option — không dùng selectOptions được nữa.
 */
/**
 * Mở dropdown và chờ tới khi thấy mục cần.
 *
 * Phải BẤM LẠI trong waitFor: Headless UI hẹn việc qua rAF/microtask, trong jsdom cú
 * bấm đầu thỉnh thoảng rơi vào lúc máy trạng thái chưa sẵn sàng và panel không mở.
 * Đây là nhiễu của môi trường test, KHÔNG phải "phải bấm 2 lần" — hành vi 1 cú bấm đã
 * đo trên trình duyệt thật. Guard aria-expanded để không tự đóng lại panel vừa mở.
 */
async function moDanhSach(
    user: ReturnType<typeof userEvent.setup>,
    label: string,
    ten: RegExp | string,
) {
    await waitFor(() => expect(screen.getByLabelText(label)).toBeEnabled());
    await waitFor(async () => {
        if (
            screen.getByLabelText(label).getAttribute('aria-expanded') !==
            'true'
        ) {
            await user.click(screen.getByLabelText(`Mở danh sách ${label}`));
        }
        expect(screen.getByRole('option', { name: ten })).toBeInTheDocument();
    });
}

/**
 * Gõ vào một ô: chờ focus xong mới gõ, rồi chờ giá trị vào đủ.
 *
 * Không có bước chờ này thì cú bấm ngay sau khi đóng panel dropdown thỉnh thoảng rơi
 * vào lúc jsdom chưa chuyển focus và cả chuỗi gõ mất trắng. Trên trình duyệt thật một
 * cú bấm là gõ được ngay (đã đo), nên đây là chống nhiễu môi trường test.
 */
async function goVao(
    user: ReturnType<typeof userEvent.setup>,
    label: string,
    text: string,
) {
    const o = screen.getByLabelText(label);
    // Bấm lại nếu focus chưa tới: Headless UI trả focus về ô của nó sau khi đóng panel,
    // và trong jsdom việc đó có thể xảy ra SAU cú bấm đầu, nuốt mất focus.
    await waitFor(async () => {
        if (document.activeElement !== o) await user.click(o);
        expect(o).toHaveFocus();
    });
    await user.type(o, text);
    await waitFor(() => expect(o).toHaveValue(text));
}

async function moVaChon(
    user: ReturnType<typeof userEvent.setup>,
    label: string,
    ten: RegExp | string,
) {
    await moDanhSach(user, label, ten);
    await user.click(screen.getByRole('option', { name: ten }));
    // Chờ panel đóng hẳn rồi mới thao tác tiếp, không thì cú gõ sau rơi vào lúc
    // panel đang đóng và bị nuốt mất.
    await waitFor(() => {
        const o = screen.queryByLabelText(label);
        // Có ca API xã lỗi -> component rơi về ô text, ô này biến mất. Không phải lỗi.
        if (o) expect(o).toHaveAttribute('aria-expanded', 'false');
    });
}

/** Giá trị onChange gần nhất. */
const last = (onChange: ReturnType<typeof vi.fn>): AddressValue =>
    onChange.mock.calls[onChange.mock.calls.length - 1][0];

/** Chọn Hà Nội -> Ba Đình, trả về onChange để khẳng định tiếp. */
async function chonHaNoiBaDinh(user: ReturnType<typeof userEvent.setup>) {
    const { onChange } = renderPicker();

    await moVaChon(user, 'Tỉnh / Thành phố', 'Thành phố Hà Nội');
    await moVaChon(user, 'Xã / Phường', 'Phường Ba Đình');

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

    it('đổ danh sách tỉnh sau sát nhập vào dropdown', async () => {
        const user = userEvent.setup();
        renderPicker();

        await moDanhSach(user, 'Tỉnh / Thành phố', 'Tỉnh Nghệ An');
        expect(
            screen.getByRole('option', { name: 'Tỉnh Nghệ An' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Thành phố Hà Nội' }),
        ).toBeInTheDocument();
    });

    it('chưa chọn tỉnh thì ô xã bị khoá', async () => {
        renderPicker();

        await screen.findByLabelText('Tỉnh / Thành phố');
        expect(screen.getByLabelText('Xã / Phường')).toBeDisabled();
    });

    it('chọn tỉnh xong mới nạp xã của đúng tỉnh đó', async () => {
        const user = userEvent.setup();
        renderPicker();

        await moVaChon(user, 'Tỉnh / Thành phố', 'Tỉnh Nghệ An');

        await moDanhSach(user, 'Xã / Phường', 'Phường Hưng Bình');
        expect(
            screen.getByRole('option', { name: 'Phường Hưng Bình' }),
        ).toBeInTheDocument();
        expect(mocks.getWards).toHaveBeenCalledWith(40);
        expect(screen.getByLabelText('Xã / Phường')).toBeEnabled();
    });

    /**
     * Bỏ <select> là mất type-ahead của OS, nên ô lọc phải bỏ dấu được — khách gõ
     * 'nghe an' phải ra 'Tỉnh Nghệ An', không thì 130 xã chỉ còn cách cuộn tay.
     */
    it('gõ không dấu vẫn lọc ra đúng tỉnh', async () => {
        const user = userEvent.setup();
        renderPicker();

        await moDanhSach(user, 'Tỉnh / Thành phố', 'Tỉnh Nghệ An');
        await goVao(user, 'Tỉnh / Thành phố', 'nghe an');

        expect(
            await screen.findByRole('option', { name: 'Tỉnh Nghệ An' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('option', { name: 'Thành phố Hà Nội' }),
        ).not.toBeInTheDocument();
    });

    it('gõ không khớp gì thì báo không tìm thấy, không im lặng', async () => {
        const user = userEvent.setup();
        renderPicker();

        await moDanhSach(user, 'Tỉnh / Thành phố', 'Thành phố Hà Nội');
        await goVao(user, 'Tỉnh / Thành phố', 'zzz');

        expect(await screen.findByText(/Không tìm thấy/)).toBeInTheDocument();
        expect(screen.queryAllByRole('option')).toHaveLength(0);
    });

    /**
     * Không khoá thì khách bấm vào ô xã ngay sau khi chọn tỉnh sẽ mở ra một danh sách
     * RỖNG rồi nó tự đóng — trông y như hỏng, phải bấm lại lần nữa.
     */
    it('đang gọi API xã thì ô xã bị khoá và báo đang tải', async () => {
        let traVe: (w: (typeof BA_DINH)[]) => void = () => {};
        mocks.getWards.mockImplementation(
            () =>
                new Promise((res) => {
                    traVe = res;
                }),
        );
        const user = userEvent.setup();
        renderPicker();

        await moVaChon(user, 'Tỉnh / Thành phố', 'Thành phố Hà Nội');

        const xa = screen.getByLabelText('Xã / Phường');
        expect(xa).toBeDisabled();
        expect(xa).toHaveAttribute('placeholder', 'Đang tải xã/phường…');

        traVe([BA_DINH]);
        await waitFor(() => expect(xa).toBeEnabled());
        expect(xa).toHaveAttribute('placeholder', 'Xã / Phường');
    });

    it('ghép chuỗi đủ 4 phần theo thứ tự số nhà, khối, xã, tỉnh', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        await goVao(user, 'Khối / Xóm / Thôn (nếu có)', 'Khối 3');
        await goVao(user, 'Số nhà, tên đường', 'Số 5 Trần Phú');

        expect(last(onChange).address).toBe(
            'Số 5 Trần Phú, Khối 3, Phường Ba Đình, Thành phố Hà Nội',
        );
    });

    it('bỏ trống khối thì chuỗi không có dấu phẩy thừa', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        await goVao(user, 'Số nhà, tên đường', 'Số 5 Trần Phú');

        expect(last(onChange).address).toBe(
            'Số 5 Trần Phú, Phường Ba Đình, Thành phố Hà Nội',
        );
    });

    it('gửi lên mã tỉnh + mã xã, và street tách riêng khỏi chuỗi', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        await goVao(user, 'Số nhà, tên đường', 'Số 5 Trần Phú');

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

        await moVaChon(user, 'Tỉnh / Thành phố', 'Tỉnh Nghệ An');

        const v = last(onChange);
        expect(v.ward_code).toBeNull();
        expect(v.province_code).toBe(40);
        expect(v.address).not.toContain('Ba Đình');
        expect(screen.getByLabelText('Xã / Phường')).toHaveValue('');
    });

    it('ô khối là tuỳ chọn, không chặn và không tạo mã riêng', async () => {
        const user = userEvent.setup();
        const onChange = await chonHaNoiBaDinh(user);

        expect(
            screen.getByLabelText('Khối / Xóm / Thôn (nếu có)'),
        ).not.toBeRequired();

        await goVao(user, 'Khối / Xóm / Thôn (nếu có)', 'Xóm 4');
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
    it('hai ô chọn vùng nằm TRÊN ô số nhà trong DOM', async () => {
        const { container } = renderPicker();

        await screen.findByLabelText('Tỉnh / Thành phố');

        const fields = Array.from(
            container.querySelectorAll('input[aria-label]'),
        );
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

        await screen.findByLabelText('Tỉnh / Thành phố');

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

        await moVaChon(user, 'Tỉnh / Thành phố', 'Thành phố Hà Nội');

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

        await screen.findByLabelText('Tỉnh / Thành phố');

        expect(
            screen.getByText('Nhập địa chỉ giúp tụi mình'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Số nhà, tên đường').className).toContain(
            'border-red-400',
        );
    });
});
