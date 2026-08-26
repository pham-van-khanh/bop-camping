import LoginModal from '@/Components/site/LoginModal';
import { EVENTS, emit } from '@/lib/bus';
import { act, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Hộp thư nhận mã phải NẰM YÊN trên màn nhập mã, kể cả sau khi khách gõ sai mã.
 *
 * Lỗi gốc: chỗ hiển thị đọc thẳng `flash.otp_email`, mà flash chỉ sống một request.
 * Gõ sai mã là sang request mới, flash rỗng, câu thông báo cụt thành "Đã gửi mã 6 số
 * tới ." — đúng lúc khách đang cần biết mở hộp thư nào.
 *
 * Vì sao không chữa bằng cách cho server phát lại email từ session: server cố tình gửi
 * bản CHE khi khách mới chỉ gõ SĐT, vì người đang gõ chưa chắc là chủ số (bopcamping-bqsv).
 * Phát lại bản thật ở nhánh lỗi là mở lại lỗ hổng ca 3.6 qua đường khác. Test cuối file
 * giữ đúng ranh giới đó.
 */

const state = vi.hoisted(() => ({
    flash: {} as Record<string, unknown>,
    form: {
        name: '',
        phone: '',
        email: '',
        ref: '',
        code: '',
    } as Record<string, string>,
    errors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            referral: null,
            auth: { user: null },
            flash: state.flash,
            emailBonus: null,
            site: {},
        },
    }),
    useForm: () => ({
        data: state.form,
        setData: (k: string, v: string) => {
            state.form[k] = v;
        },
        post: () => {},
        processing: false,
        errors: state.errors,
        reset: () => {},
        clearErrors: () => {
            state.errors = {};
        },
    }),
}));

vi.mock('framer-motion', () => ({
    AnimatePresence: ({ children }: { children: React.ReactNode }) => (
        <>{children}</>
    ),
    motion: {
        div: ({ children }: { children: React.ReactNode }) => (
            <div>{children}</div>
        ),
    },
}));

const MASK = 'te************@example.com';

/** Mở modal rồi đưa nó sang bước nhập mã đúng như server làm: một flash `otp_sent`. */
function openAtOtpStep(otpEmail: string) {
    const view = render(<LoginModal />);
    act(() => emit(EVENTS.openLogin));

    state.flash = { otp_sent: true, otp_email: otpEmail };
    view.rerender(<LoginModal />);

    return view;
}

/** Server trả lỗi: redirect back KHÔNG kèm flash nào — đây là chỗ lỗi cũ lộ ra. */
function replyWithCodeError(view: { rerender: (ui: React.ReactNode) => void }) {
    state.flash = {};
    state.errors = { code: 'Mã không đúng hoặc đã hết hạn.' };
    view.rerender(<LoginModal />);
}

beforeEach(() => {
    state.flash = {};
    state.errors = {};
    state.form = { name: '', phone: '', email: '', ref: '', code: '' };
});

describe('màn nhập mã — hộp thư nhận mã', () => {
    it('hiện bản che ngay khi server vừa gửi mã', () => {
        openAtOtpStep(MASK);

        expect(screen.getByText(MASK)).toBeInTheDocument();
    });

    it('giữ nguyên bản che sau khi khách gõ sai mã', () => {
        const view = openAtOtpStep(MASK);
        replyWithCodeError(view);

        // Vẫn ở bước nhập mã, và vẫn nói rõ mã đã bay đi đâu.
        expect(
            screen.getByText('Mã không đúng hoặc đã hết hạn.'),
        ).toBeInTheDocument();
        expect(screen.getByText(MASK)).toBeInTheDocument();
    });

    it('không để lại câu cụt "gửi mã tới ." sau khi gõ sai', () => {
        const view = openAtOtpStep(MASK);
        replyWithCodeError(view);

        // Đây chính là thứ khách nhìn thấy lúc lỗi: chuỗi "tới ." liền nhau.
        expect(document.body.textContent).not.toContain('tới .');
    });

    it('khách tự gõ email thì hiện nguyên địa chỉ, sai mã vẫn còn', () => {
        // Nhánh này server trả email đầy đủ vì chính khách vừa gõ nó.
        const full = 'khach@example.com';
        state.form.email = full;
        const view = openAtOtpStep(full);
        replyWithCodeError(view);

        expect(screen.getByText(full)).toBeInTheDocument();
    });

    it('lần gửi mã THỨ HAI vẫn mở được màn nhập mã', () => {
        // "← Sửa thông tin" là thuần client, không có request nào xen giữa để dọn cờ, nên
        // hai phản hồi liên tiếp cùng mang otp_sent: true. Effect nào chỉ nghe giá trị
        // boolean sẽ không chạy lại → mã bay đi thật mà khách vẫn đứng ở bước nhập SĐT.
        const view = openAtOtpStep(MASK);

        act(() => {
            screen.getByRole('button', { name: /Sửa thông tin/ }).click();
        });
        expect(screen.queryByText(MASK)).not.toBeInTheDocument();

        state.flash = { otp_sent: true, otp_email: MASK };
        view.rerender(<LoginModal />);

        expect(screen.getByText(MASK)).toBeInTheDocument();
    });

    it('bấm "Sửa thông tin" rồi quay lại thì không xài lại hộp thư cũ', () => {
        const view = openAtOtpStep(MASK);

        act(() => {
            screen.getByRole('button', { name: /Sửa thông tin/ }).click();
        });
        // Về bước nhập SĐT: không còn câu thông báo nào mang email cũ.
        expect(screen.queryByText(MASK)).not.toBeInTheDocument();

        // Số khác, hộp thư khác — phải là hộp thư mới chứ không phải cái cũ còn sót.
        const other = 'ab****@gmail.com';
        state.flash = { otp_sent: true, otp_email: other };
        view.rerender(<LoginModal />);

        expect(screen.getByText(other)).toBeInTheDocument();
        expect(screen.queryByText(MASK)).not.toBeInTheDocument();
    });
});
