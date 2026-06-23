import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { emit, EVENTS } from '@/lib/bus';
import type { PageProps } from '@/types';

/**
 * Popup hiện 1 lần khi khách vào site qua link giới thiệu (?ref=) và CHƯA đăng nhập.
 * "Bạn được A giới thiệu" + CTA mở modal đăng ký (đã prefill mã).
 */
export default function ReferralPopup() {
    const { referral, auth } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!referral?.code || auth.user) return;
        const key = `bop:ref-popup:${referral.code}`;
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, '1');
        setOpen(true);
    }, [referral?.code, auth.user]);

    const close = () => setOpen(false);
    const register = () => {
        close();
        emit(EVENTS.openLogin, null);
    };

    return (
        <AnimatePresence>
            {open && referral && (
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.2 }}
                    onClick={close}
                    className="fixed inset-0 z-[95] flex items-center justify-center p-5"
                    style={{ background: 'rgba(24,35,15,.5)', backdropFilter: 'blur(3px)' }}
                >
                    <motion.div
                        initial={{ opacity: 0, scale: 0.96, y: 12 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.96, y: 12 }}
                        transition={{ duration: 0.22, ease: [0.2, 0.7, 0.2, 1] }}
                        onClick={(e) => e.stopPropagation()}
                        className="w-full max-w-[400px] rounded-[20px] bg-card p-7 text-center"
                        style={{ boxShadow: '0 30px 70px -20px rgba(24,35,15,.6)' }}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Lời mời giới thiệu"
                    >
                        <div className="mx-auto mb-3 grid h-[60px] w-[60px] place-items-center rounded-full text-[30px]" style={{ background: '#dcebc4' }}>
                            🎁
                        </div>
                        <h2 className="mb-1.5 text-[20px] font-extrabold text-pine">
                            Bạn được {referral.referrer_name ? <span className="text-grass">{referral.referrer_name}</span> : 'một người bạn'} giới thiệu!
                        </h2>
                        <p className="mb-1 text-[14px] text-moss">
                            Đăng ký tài khoản và đặt đơn thuê đầu tiên với mã dưới đây để nhận ưu đãi giảm giá.
                        </p>
                        <div className="mx-auto mb-[18px] mt-3 inline-block rounded-[11px] border border-dashed border-grass bg-[#f1f4ea] px-5 py-2.5 font-mono text-[20px] font-bold tracking-[0.16em] text-grass">
                            {referral.code}
                        </div>
                        <div className="flex flex-col gap-2">
                            <button onClick={register} className="h-12 rounded-control bg-grass text-[15px] font-bold text-white transition hover:bg-pine">
                                Đăng ký ngay
                            </button>
                            <button onClick={close} className="h-10 text-[14px] font-semibold text-moss">
                                Để sau
                            </button>
                        </div>
                    </motion.div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
