import PaymentStatus from '@/Components/PaymentStatus';
import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-pew1 — khối "Tình trạng thanh toán" trên trang khách.
 *
 * Trước đây rental_paid/deposit_paid không hề ra tới khách: chuyển khoản xong không có
 * cách nào biết shop đã ghi nhận chưa. File này khoá lại:
 *   1. Hai khoản báo ĐỘC LẬP — trả tiền thuê rồi mà chưa trả cọc phải thấy đúng như vậy.
 *   2. Đơn không cọc thì KHÔNG đẻ ra dòng cọc luôn "chưa nhận" không bao giờ xong.
 *   3. Câu nhắc chờ chỉ hiện khi còn khoản chưa nhận.
 */

const rowFor = (label: string) =>
    screen.getByText(label, { exact: false }).closest('div') as HTMLElement;

describe('PaymentStatus', () => {
    it('báo hai khoản độc lập nhau', () => {
        render(
            <PaymentStatus
                rentalDue={1500000}
                depositTotal={361000}
                rentalPaid
                depositPaid={false}
            />,
        );

        expect(
            within(rowFor('Tiền thuê')).getByText(/Shop đã nhận/),
        ).toBeInTheDocument();
        expect(
            within(rowFor('Tiền cọc')).getByText(/Chưa nhận/),
        ).toBeInTheDocument();
    });

    it('hiện số tiền của từng khoản', () => {
        render(
            <PaymentStatus
                rentalDue={1500000}
                depositTotal={361000}
                rentalPaid={false}
                depositPaid={false}
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
                rentalPaid={false}
                depositPaid={false}
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
                rentalPaid
                depositPaid
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
                rentalPaid
                depositPaid={false}
            />,
        );

        expect(screen.getByText(/sau khi nhận được tiền/)).toBeInTheDocument();
    });
});
