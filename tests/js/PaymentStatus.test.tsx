import PaymentStatus from '@/Components/PaymentStatus';
import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-pew1 / bopcamping-r3fy — khối "Tình trạng thanh toán" trên trang khách.
 *
 * Khoá lại bốn thứ:
 *   1. Hai khoản báo ĐỘC LẬP — trả tiền thuê rồi mà chưa trả cọc phải thấy đúng như vậy.
 *   2. In theo SỐ TIỀN THẬT ĐÃ NHẬN, không phải số phải trả hiện tại. Giá đơn còn đổi sau
 *      lúc thu (admin nhập phụ phí), ghép cờ "đã nhận" với con số mới là khẳng định shop
 *      nhận một khoản chưa từng nhận — lỗi đã đo được trước khi lên production.
 *   3. Đơn không cọc thì KHÔNG đẻ ra dòng cọc luôn "chưa nhận" không bao giờ xong.
 *   4. Câu nhắc chờ chỉ hiện khi còn thiếu.
 */

const rowFor = (label: string) =>
    screen.getByText(label, { exact: false }).closest('div') as HTMLElement;

describe('PaymentStatus', () => {
    it('báo hai khoản độc lập nhau', () => {
        render(
            <PaymentStatus
                rentalDue={1500000}
                depositTotal={361000}
                rentalReceived={1500000}
                depositReceived={0}
            />,
        );

        expect(
            within(rowFor('Tiền thuê')).getByText(/Shop đã nhận/),
        ).toBeInTheDocument();
        expect(
            within(rowFor('Tiền cọc')).getByText(/Chưa nhận/),
        ).toBeInTheDocument();
    });

    /**
     * Ca lỗi thật: admin bấm thu 500.000 rồi mới nhập phí ship 50.000, tiền thuê thành
     * 550.000. Bản cũ in "550.000đ ✓ Shop đã nhận" — nói shop nhận 550k trong khi mới
     * nhận 500k. Giờ phải nói rõ đã nhận bao nhiêu và còn thiếu bao nhiêu.
     */
    it('thu thiếu thì nói rõ đã nhận bao nhiêu và còn bao nhiêu', () => {
        render(
            <PaymentStatus
                rentalDue={550000}
                depositTotal={300000}
                rentalReceived={500000}
                depositReceived={0}
            />,
        );

        const row = rowFor('Tiền thuê');
        expect(within(row).getByText(/Đã nhận 500\.000đ/)).toBeInTheDocument();
        expect(within(row).getByText(/còn 50\.000đ/)).toBeInTheDocument();
        expect(
            within(row).queryByText(/✓ Shop đã nhận/),
        ).not.toBeInTheDocument();
    });

    it('hiện số tiền phải trả của từng khoản', () => {
        render(
            <PaymentStatus
                rentalDue={1500000}
                depositTotal={361000}
                rentalReceived={0}
                depositReceived={0}
            />,
        );

        expect(screen.getByText('1.500.000đ')).toBeInTheDocument();
        expect(screen.getByText('361.000đ')).toBeInTheDocument();
    });

    it('đơn không cọc thì không có dòng cọc', () => {
        render(
            <PaymentStatus
                rentalDue={800000}
                depositTotal={0}
                rentalReceived={0}
                depositReceived={0}
            />,
        );

        expect(screen.getByText(/Tiền thuê/)).toBeInTheDocument();
        expect(screen.queryByText(/Tiền cọc/)).not.toBeInTheDocument();
    });

    it('thu đủ cả hai thì bỏ câu nhắc chờ', () => {
        render(
            <PaymentStatus
                rentalDue={1500000}
                depositTotal={361000}
                rentalReceived={1500000}
                depositReceived={361000}
            />,
        );

        expect(
            screen.queryByText(/sau khi nhận được tiền/),
        ).not.toBeInTheDocument();
        expect(screen.getAllByText(/Shop đã nhận/)).toHaveLength(2);
    });

    it('còn khoản chưa nhận thì nhắc khách chờ shop cập nhật', () => {
        render(
            <PaymentStatus
                rentalDue={1500000}
                depositTotal={361000}
                rentalReceived={1500000}
                depositReceived={0}
            />,
        );

        expect(screen.getByText(/sau khi nhận được tiền/)).toBeInTheDocument();
    });
});
