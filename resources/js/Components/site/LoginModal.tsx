import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { on, emit, EVENTS } from '@/lib/bus';

/** Modal đăng nhập nhanh SĐT + tên — POST /dang-nhap (GuestAuthController). */
export default function LoginModal() {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        phone: '',
    });

    useEffect(() => on(EVENTS.openLogin, () => setOpen(true)), []);

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    const handleClose = () => {
        setOpen(false);
        reset();
        clearErrors();
    };

    const submit = () => {
        post(route('guest.login'), {
            onSuccess: () => {
                handleClose();
                emit(EVENTS.userChange, null);
            },
        });
    };

    const valid = data.name.trim().length >= 2 && data.phone.trim().length >= 8;

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
                        <p className="mb-[18px] text-[14px] text-moss">Chỉ cần số điện thoại và tên. Không mật khẩu.</p>
                        <div className="mb-[18px] flex flex-col gap-[11px]">
                            <div>
                                <input
                                    autoFocus
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && valid && !processing && submit()}
                                    placeholder="Tên của bạn"
                                    className={`h-12 w-full rounded-[11px] border bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass ${errors.name ? 'border-red-400' : 'border-cardBorder'}`}
                                />
                                {errors.name && (
                                    <p className="mt-1 text-[13px] text-red-500">{errors.name}</p>
                                )}
                            </div>
                            <div>
                                <input
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && valid && !processing && submit()}
                                    placeholder="Số điện thoại"
                                    inputMode="tel"
                                    className={`h-12 w-full rounded-[11px] border bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass ${errors.phone ? 'border-red-400' : 'border-cardBorder'}`}
                                />
                                {errors.phone && (
                                    <p className="mt-1 text-[13px] text-red-500">{errors.phone}</p>
                                )}
                            </div>
                        </div>
                        <button
                            onClick={submit}
                            disabled={!valid || processing}
                            className="h-[50px] w-full rounded-control text-[16px] font-bold text-white transition disabled:cursor-not-allowed"
                            style={{ background: valid && !processing ? '#557A2B' : '#c4cfae' }}
                        >
                            {processing ? 'Đang xử lý…' : 'Tiếp tục'}
                        </button>
                    </motion.div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
