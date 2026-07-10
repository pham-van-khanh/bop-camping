import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { on, emit, EVENTS } from '@/lib/bus';
import { money } from '@/lib/format';
import type { PageProps } from '@/types';

/**
 * Modal đăng nhập khách: SĐT là bắt buộc; tên + email tuỳ ý (email khuyến khích qua ưu đãi
 * đơn đầu — xem emailBonus prop). Chỉ xác thực OTP qua email khi có nhập email mới/chưa verify.
 * - Bước 1: nhập SĐT (+tên/email) → POST /dang-nhap. Không nhập email hoặc email đã verify → vào
 *   thẳng; email mới/chưa verify → server gửi OTP và trả flash.otp_sent → chuyển bước 2.
 * - Bước 2: nhập OTP 6 số → POST /dang-nhap/xac-thuc.
 */
export default function LoginModal() {
    const { referral, auth, flash, emailBonus } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const [step, setStep] = useState<'form' | 'otp'>('form');
    const [resendIn, setResendIn] = useState(0);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        phone: '',
        email: '',
        ref: '',
        code: '',
    });

    useEffect(() => on(EVENTS.openLogin, () => setOpen(true)), []);

    // Prefill mã giới thiệu từ link (?ref=) khi có.
    useEffect(() => {
        if (referral?.code) setData('ref', referral.code);
    }, [referral?.code]);

    // Khách vào qua link giới thiệu & chưa đăng nhập → tự mở modal (1 lần/phiên).
    useEffect(() => {
        if (!referral?.code || auth.user) return;
        const key = `bop:ref-autologin:${referral.code}`;
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, '1');
        setOpen(true);
    }, [referral?.code, auth.user]);

    // Server đã gửi OTP → chuyển sang bước nhập mã + bật đếm ngược gửi lại.
    useEffect(() => {
        if (flash?.otp_sent) {
            setStep('otp');
            startCooldown();
        }
    }, [flash?.otp_sent]);

    // Đăng nhập xong (prop auth.user xuất hiện) → đóng modal.
    useEffect(() => {
        if (auth.user && open) {
            emit(EVENTS.userChange, null);
            handleClose();
        }
    }, [auth.user]);

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && handleClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    useEffect(() => () => { if (timer.current) clearInterval(timer.current); }, []);

    const startCooldown = () => {
        setResendIn(30);
        if (timer.current) clearInterval(timer.current);
        timer.current = setInterval(() => {
            setResendIn((s) => {
                if (s <= 1 && timer.current) clearInterval(timer.current);
                return s > 0 ? s - 1 : 0;
            });
        }, 1000);
    };

    const handleClose = () => {
        setOpen(false);
        setStep('form');
        setResendIn(0);
        reset();
        clearErrors();
        if (referral?.code) setData('ref', referral.code);
    };

    // Bước 1: gửi yêu cầu (gửi OTP hoặc đăng nhập thẳng nếu đã verify).
    const requestOtp = () => post(route('guest.login'), { preserveScroll: true });

    // Bước 2: xác thực OTP.
    const verifyOtp = () => post(route('guest.login.verify'), { preserveScroll: true });

    // Tên & email không bắt buộc — SĐT là khoá định danh duy nhất. Nếu có nhập email thì phải
    // đúng định dạng (còn để trống thì bỏ qua, khách vào thẳng không cần OTP).
    const phoneValid = /^0[0-9]{8,10}$/.test(data.phone.trim());
    const emailTyped = data.email.trim() !== '';
    const emailOk = !emailTyped || /\S+@\S+\.\S+/.test(data.email);
    const formValid = phoneValid && emailOk;
    const codeValid = /^[0-9]{6}$/.test(data.code);
    const bonusText = emailBonus?.enabled
        ? emailBonus.type === 'percent' ? `giảm ${emailBonus.value}%` : `giảm ${money(emailBonus.value)}`
        : null;

    return (
        <AnimatePresence>
            {open && (
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.2 }}
                    onClick={handleClose}
                    className="fixed inset-0 z-[90] flex items-center justify-center p-5"
                    style={{ background: 'rgba(24,35,15,.5)', backdropFilter: 'blur(3px)' }}
                >
                    <motion.div
                        initial={{ opacity: 0, scale: 0.96, y: 12 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.96, y: 12 }}
                        transition={{ duration: 0.22, ease: [0.2, 0.7, 0.2, 1] }}
                        onClick={(e) => e.stopPropagation()}
                        className="w-full max-w-[380px] rounded-[20px] bg-card p-7"
                        style={{ boxShadow: '0 30px 70px -20px rgba(24,35,15,.6)' }}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Đăng nhập nhanh"
                    >
                        <div className="mb-1.5 flex items-center gap-2.5">
                            <span className="grid h-10 w-10 place-items-center rounded-[11px] bg-grass">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 4 4 19h16L12 4Z" fill="#cfe0a8" /><path d="M12 10l-4 9h8l-4-9Z" fill="#557A2B" /></svg>
                            </span>
                            <div className="text-[18px] font-extrabold text-pine">Đăng nhập nhanh</div>
                        </div>

                        {step === 'form' ? (
                            <>
                                <p className="mb-[14px] text-[14px] text-moss">Chỉ cần số điện thoại là đăng nhập được. Nếu nhập email, lần đầu sẽ có mã xác thực gửi qua email.</p>
                                {referral?.referrer_name && (
                                    <div className="mb-[14px] flex items-center gap-2 rounded-[11px] px-3.5 py-2.5 text-[13px]" style={{ background: '#eef2e3', color: '#3a5a1f' }}>
                                        <span>🎁</span>
                                        <span>Bạn được <strong>{referral.referrer_name}</strong> giới thiệu — đặt đơn đầu để nhận ưu đãi.</span>
                                    </div>
                                )}
                                <div className="mb-[18px] flex flex-col gap-[11px]">
                                    <Field
                                        value={data.phone}
                                        onChange={(v) => setData('phone', v)}
                                        onEnter={() => formValid && !processing && requestOtp()}
                                        placeholder="Số điện thoại"
                                        inputMode="tel"
                                        error={errors.phone}
                                        autoFocus
                                    />
                                    <div>
                                        <Field
                                            value={data.email}
                                            onChange={(v) => setData('email', v)}
                                            onEnter={() => formValid && !processing && requestOtp()}
                                            placeholder="Email (không bắt buộc)"
                                            inputMode="email"
                                            error={errors.email}
                                        />
                                        {bonusText && (
                                            <p className="mt-1.5 text-[12.5px] text-grass">
                                                🎁 Để trống nếu chưa có email. Thêm email để được <strong>{bonusText}</strong> cho đơn hàng đầu tiên!
                                            </p>
                                        )}
                                    </div>
                                    <Field
                                        value={data.name}
                                        onChange={(v) => setData('name', v)}
                                        onEnter={() => formValid && !processing && requestOtp()}
                                        placeholder="Tên hiển thị (tuỳ ý)"
                                        error={errors.name}
                                    />
                                    <input
                                        value={data.ref}
                                        onChange={(e) => setData('ref', e.target.value.toUpperCase())}
                                        placeholder="Mã giới thiệu (nếu có)"
                                        className="h-12 w-full rounded-[11px] border border-cardBorder bg-white px-3.5 font-mono text-[15px] uppercase tracking-[0.04em] text-ink outline-none focus:border-grass"
                                    />
                                </div>
                                <button
                                    onClick={requestOtp}
                                    disabled={!formValid || processing}
                                    className="h-[50px] w-full rounded-control text-[16px] font-bold text-white transition disabled:cursor-not-allowed"
                                    style={{ background: formValid && !processing ? '#557A2B' : '#c4cfae' }}
                                >
                                    {processing ? 'Đang xử lý…' : 'Tiếp tục'}
                                </button>
                            </>
                        ) : (
                            <>
                                <p className="mb-[14px] text-[14px] text-moss">
                                    Đã gửi mã 6 số tới <strong className="text-ink">{flash?.otp_email || data.email}</strong>. Nhập mã để hoàn tất.
                                </p>
                                <div className="mb-[18px]">
                                    <input
                                        autoFocus
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                        onKeyDown={(e) => e.key === 'Enter' && codeValid && !processing && verifyOtp()}
                                        placeholder="••••••"
                                        inputMode="numeric"
                                        className={`h-14 w-full rounded-[11px] border bg-white text-center font-mono text-[28px] tracking-[12px] text-ink outline-none focus:border-grass ${errors.code ? 'border-red-400' : 'border-cardBorder'}`}
                                    />
                                    {errors.code && <p className="mt-1 text-[13px] text-red-500">{errors.code}</p>}
                                </div>
                                <button
                                    onClick={verifyOtp}
                                    disabled={!codeValid || processing}
                                    className="h-[50px] w-full rounded-control text-[16px] font-bold text-white transition disabled:cursor-not-allowed"
                                    style={{ background: codeValid && !processing ? '#557A2B' : '#c4cfae' }}
                                >
                                    {processing ? 'Đang xác thực…' : 'Xác nhận'}
                                </button>
                                <div className="mt-3 flex items-center justify-between text-[13px]">
                                    <button onClick={() => { setStep('form'); clearErrors(); }} className="text-moss hover:text-ink">← Sửa thông tin</button>
                                    <button
                                        onClick={requestOtp}
                                        disabled={resendIn > 0 || processing}
                                        className="font-semibold text-grass disabled:text-moss/60"
                                    >
                                        {resendIn > 0 ? `Gửi lại sau ${resendIn}s` : 'Gửi lại mã'}
                                    </button>
                                </div>
                            </>
                        )}
                    </motion.div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}

function Field({ value, onChange, onEnter, placeholder, error, inputMode, autoFocus }: {
    value: string;
    onChange: (v: string) => void;
    onEnter: () => void;
    placeholder: string;
    error?: string;
    inputMode?: 'tel' | 'email' | 'text';
    autoFocus?: boolean;
}) {
    return (
        <div>
            <input
                autoFocus={autoFocus}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && onEnter()}
                placeholder={placeholder}
                inputMode={inputMode}
                className={`h-12 w-full rounded-[11px] border bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass ${error ? 'border-red-400' : 'border-cardBorder'}`}
            />
            {error && <p className="mt-1 text-[13px] text-red-500">{error}</p>}
        </div>
    );
}
