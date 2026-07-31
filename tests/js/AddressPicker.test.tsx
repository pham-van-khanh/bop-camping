import AddressPicker, {
    type AddressValue,
} from '@/Components/site/AddressPicker';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-vj4x — AddressPicker.
 *
 * Chốt 3 ca của phép suy ra (map xã cũ -> xã mới là NHIỀU-NHIỀU, đo thật: Phường Điện Biên
 * -> 4 phường mới) và FALLBACK về ô text — cái sau quan trọng nhất vì nó là thứ giữ cho
 * khách vẫn đặt được hàng khi API địa chỉ chết.
 */

const mocks = vi.hoisted(() => ({
    getProvinces: vi.fn(),
    getWards: vi.fn(),
    getLegacyProvinces: vi.fn(),
    getLegacyDistricts: vi.fn(),
    getLegacyWards: vi.fn(),
    inferNewWards: vi.fn(),
    getLegaciesOfWard: vi.fn(),
    getLegacyDistrictName: vi.fn(),
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
    province_code: 1,
};
const HOAN_KIEM = {
    name: 'Phường Hoàn Kiếm',
    code: 5,
    codename: 'phuong_hoan_kiem',
    division_type: 'phường',
    province_code: 1,
};
const O_CHO_DUA = {
    name: 'Phường Ô Chợ Dừa',
    code: 6,
    codename: 'phuong_o_cho_dua',
    division_type: 'phường',
    province_code: 1,
};
const VAN_MIEU = {
    name: 'Phường Văn Miếu',
    code: 7,
    codename: 'phuong_van_mieu',
    division_type: 'phường',
    province_code: 1,
};

const LEGACY_DISTRICT = {
    name: 'Quận Ba Đình',
    code: 1,
    division_type: 'quận',
    codename: 'quan_ba_dinh',
    province_code: 1,
};
const LEGACY_WARD = {
    name: 'Phường Điện Biên',
    code: 19,
    codename: 'phuong_dien_bien',
    division_type: 'phường',
    district_code: 1,
};

const EMPTY: AddressValue = {
    address: '',
    street: '',
    province_code: null,
    ward_code: null,
    legacy_ward_code: null,
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

/** Query option TRONG một select cụ thể — tỉnh mới và tỉnh cũ có tên trùng nhau. */
const optionIn = (selectLabel: string, name: string) =>
    within(screen.getByLabelText(selectLabel)).getByRole('option', { name });

const waitOption = (selectLabel: string, name: string) =>
    waitFor(() => expect(optionIn(selectLabel, name)).toBeInTheDocument());

/** Giá trị onChange gần nhất. */
const last = (onChange: ReturnType<typeof vi.fn>): AddressValue =>
    onChange.mock.calls[onChange.mock.calls.length - 1][0];

describe('AddressPicker', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.getProvinces.mockResolvedValue([HANOI, NGHEAN]);
        mocks.getWards.mockResolvedValue([BA_DINH, HOAN_KIEM]);
        mocks.getLegacyProvinces.mockResolvedValue([HANOI]);
        mocks.getLegacyDistricts.mockResolvedValue([LEGACY_DISTRICT]);
        mocks.getLegacyWards.mockResolvedValue([LEGACY_WARD]);
        mocks.getLegaciesOfWard.mockResolvedValue([LEGACY_WARD]);
        mocks.getLegacyDistrictName.mockResolvedValue('Quận Ba Đình');
    });

    it('nạp tỉnh khi mở, chọn tỉnh thì nạp xã của tỉnh đó', async () => {
        const user = userEvent.setup();
        renderPicker();

        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '1',
        );

        await waitFor(() => expect(mocks.getWards).toHaveBeenCalledWith(1));
        await waitOption('Xã / Phường', 'Phường Ba Đình');
    });

    it('ghép address đúng định dạng khi chọn đủ', async () => {
        const user = userEvent.setup();
        const { onChange } = renderPicker();

        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');
        await user.type(
            screen.getByLabelText('Số nhà, tên đường'),
            'Số 5 Trần Phú',
        );
        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '1',
        );
        await waitOption('Xã / Phường', 'Phường Ba Đình');
        await user.selectOptions(screen.getByLabelText('Xã / Phường'), '4');

        // Chọn xã mới giờ TỰ tra địa chỉ cũ (getLegaciesOfWard) nên chuỗi kèm phần ngoặc.
        await waitFor(() =>
            expect(last(onChange).address).toBe(
                'Số 5 Trần Phú, Phường Ba Đình, Thành phố Hà Nội (trước sát nhập: Phường Điện Biên, Quận Ba Đình)',
            ),
        );
        const v = last(onChange);
        expect(v.province_code).toBe(1);
        expect(v.ward_code).toBe(4);
        expect(v.legacy_ward_code).toBe(19);
    });

    it('xã chưa chọn tỉnh thì bị vô hiệu', async () => {
        renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        expect(screen.getByLabelText('Xã / Phường')).toBeDisabled();
    });

    it('bấm "địa chỉ cũ" mở 3 select cũ', async () => {
        const user = userEvent.setup();
        renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        await user.click(
            screen.getByRole('button', { name: /Tôi chỉ biết địa chỉ cũ/ }),
        );

        expect(screen.getByLabelText('Tỉnh cũ')).toBeInTheDocument();
        expect(screen.getByLabelText('Quận / Huyện cũ')).toBeInTheDocument();
        expect(screen.getByLabelText('Xã / Phường cũ')).toBeInTheDocument();
        await waitFor(() =>
            expect(mocks.getLegacyProvinces).toHaveBeenCalled(),
        );
    });

    /** Chọn hết 3 select cũ rồi trả về giá trị onChange gần nhất. */
    async function walkLegacy(user: ReturnType<typeof userEvent.setup>) {
        await user.click(
            screen.getByRole('button', { name: /Tôi chỉ biết địa chỉ cũ/ }),
        );
        await waitOption('Tỉnh cũ', 'Thành phố Hà Nội');
        await user.selectOptions(screen.getByLabelText('Tỉnh cũ'), '1');
        await waitOption('Quận / Huyện cũ', 'Quận Ba Đình');
        await user.selectOptions(screen.getByLabelText('Quận / Huyện cũ'), '1');
        await waitOption('Xã / Phường cũ', 'Phường Điện Biên');
        await user.selectOptions(screen.getByLabelText('Xã / Phường cũ'), '19');
    }

    /** CA 1 — map 1-1: tự điền luôn. */
    it('suy ra đúng 1 xã thì tự điền + hiện "Đã suy ra"', async () => {
        const user = userEvent.setup();
        mocks.inferNewWards.mockResolvedValue({
            wards: [BA_DINH],
            exact: true,
        });
        const { onChange } = renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        await walkLegacy(user);

        await waitFor(() =>
            expect(screen.getByText(/Đã suy ra/)).toBeInTheDocument(),
        );
        const v = last(onChange);
        expect(v.ward_code).toBe(4);
        expect(v.province_code).toBe(1);
        expect(v.legacy_ward_code).toBe(19);
        expect(v.address).toContain(
            '(trước sát nhập: Phường Điện Biên, Quận Ba Đình)',
        );
    });

    /** CA N — xã cũ bị CHIA (ca thật của Phường Điện Biên): hiện đúng N ứng viên, KHÔNG tự chọn. */
    it('suy ra nhiều xã thì thu hẹp danh sách và không tự chọn', async () => {
        const user = userEvent.setup();
        mocks.inferNewWards.mockResolvedValue({
            wards: [BA_DINH, HOAN_KIEM, O_CHO_DUA, VAN_MIEU],
            exact: false,
        });
        const { onChange } = renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        await walkLegacy(user);

        await waitFor(() =>
            expect(
                screen.getByText(/thuộc 4 xã\/phường mới/),
            ).toBeInTheDocument(),
        );
        // Danh sách xã mới thu hẹp còn đúng 4 ứng viên.
        expect(
            screen.getByRole('option', { name: 'Phường Ô Chợ Dừa' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Phường Văn Miếu' }),
        ).toBeInTheDocument();
        // KHÔNG tự chọn xã nào — khách phải tự quyết.
        expect(last(onChange).ward_code).toBeNull();
        expect(last(onChange).legacy_ward_code).toBe(19);
    });

    /** CA 0 — không tra được: nói rõ, giữ cho khách chọn tay. */
    it('không suy ra được thì báo rõ và không chặn', async () => {
        const user = userEvent.setup();
        mocks.inferNewWards.mockResolvedValue({ wards: [], exact: false });
        const { onChange } = renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        await walkLegacy(user);

        await waitFor(() =>
            expect(
                screen.getByText(/Không tra được tự động/),
            ).toBeInTheDocument(),
        );
        expect(last(onChange).legacy_ward_code).toBe(19);
    });

    /** inferNewWards throw cũng không được chặn khách. */
    it('suy ra lỗi mạng thì vẫn cho chọn tay', async () => {
        const user = userEvent.setup();
        mocks.inferNewWards.mockRejectedValue(new Error('mạng lỗi'));
        renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        await walkLegacy(user);

        await waitFor(() =>
            expect(
                screen.getByText(/Không tra được tự động/),
            ).toBeInTheDocument(),
        );
        // Vẫn còn select xã mới để chọn tay.
        expect(screen.getByLabelText('Xã / Phường')).toBeInTheDocument();
    });

    it('sau khi thu hẹp, bấm "Xem tất cả xã" thì nạp lại full list', async () => {
        const user = userEvent.setup();
        mocks.inferNewWards.mockResolvedValue({
            wards: [BA_DINH, HOAN_KIEM],
            exact: false,
        });
        renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');
        await walkLegacy(user);
        await waitFor(() =>
            expect(
                screen.getByText(/thuộc 2 xã\/phường mới/),
            ).toBeInTheDocument(),
        );

        mocks.getWards.mockClear();
        await user.click(
            screen.getByRole('button', { name: /Xem tất cả xã của tỉnh/ }),
        );

        await waitFor(() => expect(mocks.getWards).toHaveBeenCalledWith(1));
    });

    /**
     * FALLBACK — quan trọng nhất. API chết thì về đúng ô text tự do như trước khi có
     * tính năng này, và onChange vẫn trả address khách gõ để đơn đặt được.
     */
    it('không tải được tỉnh thì về ô text tự do, vẫn gửi được address', async () => {
        const user = userEvent.setup();
        mocks.getProvinces.mockRejectedValue(new Error('API chết'));
        const { onChange } = renderPicker();

        await waitFor(() =>
            expect(
                screen.getByText(/Không tải được danh sách địa chỉ/),
            ).toBeInTheDocument(),
        );
        expect(
            screen.queryByLabelText('Tỉnh / Thành phố'),
        ).not.toBeInTheDocument();

        await user.type(
            screen.getByLabelText('Địa chỉ giao nhận'),
            'Số 9 ngõ 2',
        );

        const v = last(onChange);
        expect(v.address).toBe('Số 9 ngõ 2');
        expect(v.province_code).toBeNull();
        expect(v.ward_code).toBeNull();
    });

    // ---------- Ô "địa chỉ cũ" tự tra từ địa chỉ MỚI ----------

    /** Chọn xong xã mới -> ô địa chỉ cũ hiện ra và ĐÃ ĐIỀN SẴN (ca 1 xã cũ). */
    it('chọn xã mới thì ô địa chỉ cũ hiện ra, điền sẵn khi chỉ có 1 xã cũ', async () => {
        const user = userEvent.setup();
        const { onChange } = renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        // Chưa chọn xã thì chưa có ô địa chỉ cũ.
        expect(
            screen.queryByLabelText('Địa chỉ cũ (trước sát nhập)'),
        ).not.toBeInTheDocument();

        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '1',
        );
        await waitOption('Xã / Phường', 'Phường Ba Đình');
        await user.selectOptions(screen.getByLabelText('Xã / Phường'), '4');

        await waitFor(() =>
            expect(
                screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'),
            ).toHaveValue('Phường Điện Biên, Quận Ba Đình'),
        );
        await waitFor(() =>
            expect(mocks.getLegaciesOfWard).toHaveBeenCalledWith(4),
        );
        await waitFor(() =>
            expect(last(onChange).address).toContain(
                '(trước sát nhập: Phường Điện Biên, Quận Ba Đình)',
            ),
        );
        expect(last(onChange).legacy_ward_code).toBe(19);
    });

    /**
     * Một xã MỚI thường gộp từ NHIỀU xã cũ (đo thật: Phường Ba Đình <- 10 xã cũ).
     * KHÔNG được điền bừa cái đầu — phải cho khách chọn.
     */
    it('nhiều xã cũ gộp lại thì không điền bừa, cho khách chọn', async () => {
        const user = userEvent.setup();
        const TRUC_BACH = {
            name: 'Phường Trúc Bạch',
            code: 4,
            codename: 'truc_bach',
            division_type: 'phường',
            district_code: 1,
        };
        const QUAN_THANH = {
            name: 'Phường Quán Thánh',
            code: 13,
            codename: 'quan_thanh',
            division_type: 'phường',
            district_code: 1,
        };
        mocks.getLegaciesOfWard.mockResolvedValue([TRUC_BACH, QUAN_THANH]);
        const { onChange } = renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '1',
        );
        await waitOption('Xã / Phường', 'Phường Ba Đình');
        await user.selectOptions(screen.getByLabelText('Xã / Phường'), '4');

        // Ô để TRỐNG, hiện select cho khách chọn.
        await waitFor(() =>
            expect(
                screen.getByLabelText('Chọn địa chỉ cũ tương ứng'),
            ).toBeInTheDocument(),
        );
        expect(
            screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'),
        ).toHaveValue('');
        expect(last(onChange).address).not.toContain('trước sát nhập');

        // Chọn 1 trong số đó -> điền vào ô.
        mocks.getLegacyDistrictName.mockResolvedValue('Quận Ba Đình');
        await user.selectOptions(
            screen.getByLabelText('Chọn địa chỉ cũ tương ứng'),
            '13',
        );

        await waitFor(() =>
            expect(
                screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'),
            ).toHaveValue('Phường Quán Thánh, Quận Ba Đình'),
        );
        expect(last(onChange).legacy_ward_code).toBe(13);
    });

    it('khách sửa ô địa chỉ cũ thì chuỗi address đổi theo', async () => {
        const user = userEvent.setup();
        const { onChange } = renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');
        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '1',
        );
        await waitOption('Xã / Phường', 'Phường Ba Đình');
        await user.selectOptions(screen.getByLabelText('Xã / Phường'), '4');
        await waitFor(() =>
            expect(
                screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'),
            ).toHaveValue('Phường Điện Biên, Quận Ba Đình'),
        );

        await user.clear(screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'));
        await user.type(
            screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'),
            'Tôi tự ghi',
        );

        await waitFor(() =>
            expect(last(onChange).address).toContain(
                '(trước sát nhập: Tôi tự ghi)',
            ),
        );
    });

    /** Xoá sạch ô địa chỉ cũ -> chuỗi không còn phần ngoặc. */
    it('xoá sạch ô địa chỉ cũ thì bỏ luôn phần trong ngoặc', async () => {
        const user = userEvent.setup();
        const { onChange } = renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');
        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '1',
        );
        await waitOption('Xã / Phường', 'Phường Ba Đình');
        await user.selectOptions(screen.getByLabelText('Xã / Phường'), '4');
        await waitFor(() =>
            expect(
                screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'),
            ).toHaveValue('Phường Điện Biên, Quận Ba Đình'),
        );

        await user.clear(screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'));

        await waitFor(() =>
            expect(last(onChange).address).not.toContain('trước sát nhập'),
        );
    });

    it('tra địa chỉ cũ lỗi thì ô vẫn hiện và trống, không chặn', async () => {
        const user = userEvent.setup();
        mocks.getLegaciesOfWard.mockRejectedValue(new Error('API lỗi'));
        const { onChange } = renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        await user.selectOptions(
            screen.getByLabelText('Tỉnh / Thành phố'),
            '1',
        );
        await waitOption('Xã / Phường', 'Phường Ba Đình');
        await user.selectOptions(screen.getByLabelText('Xã / Phường'), '4');

        await waitFor(() =>
            expect(
                screen.getByLabelText('Địa chỉ cũ (trước sát nhập)'),
            ).toHaveValue(''),
        );
        // Vẫn ghi được address đầy đủ phần mới.
        expect(last(onChange).ward_code).toBe(4);
    });

    /** Yêu cầu bố cục: ô số nhà nằm DƯỚI hai select. */
    it('ô số nhà nằm dưới select tỉnh và xã', async () => {
        renderPicker();
        await waitOption('Tỉnh / Thành phố', 'Thành phố Hà Nội');

        const provinceSel = screen.getByLabelText('Tỉnh / Thành phố');
        const street = screen.getByLabelText('Số nhà, tên đường');

        // compareDocumentPosition: FOLLOWING = street đứng sau select trong DOM.
        expect(
            provinceSel.compareDocumentPosition(street) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('hiện lỗi validate từ server', async () => {
        render(
            <AddressPicker
                value={EMPTY}
                onChange={vi.fn()}
                error="Vui lòng nhập địa chỉ."
            />,
        );

        await waitFor(() =>
            expect(
                screen.getByText('Vui lòng nhập địa chỉ.'),
            ).toBeInTheDocument(),
        );
    });
});
