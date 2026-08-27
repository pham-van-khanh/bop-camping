import FeedbackWidget from '@/Components/site/FeedbackWidget';
import Footer from '@/Components/site/Footer';
import Header from '@/Components/site/Header';
import LoginModal from '@/Components/site/LoginModal';
import Toast from '@/Components/site/Toast';
import ZaloFloatButton from '@/Components/site/ZaloFloatButton';
import { EVENTS, on } from '@/lib/bus';
import { cartCount, getCart } from '@/lib/cart';
import type { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';

/**
 * Thanh nhắc khi admin đang xem hộ tài khoản khách (bopcamping-bqsv).
 *
 * Phải nổi bật và có ở MỌI trang khách: không có nó thì admin rất dễ quên mình đang ở trong
 * tài khoản người khác rồi thao tác nhầm (đặt đơn, tiêu voucher của khách).
 */
function ImpersonationBar({ name }: { name: string | null }) {
    return (
        <div className="flex flex-wrap items-center justify-center gap-3 bg-[#fdf6e3] px-4 py-2 text-center text-[13px] text-[#8a6d1f]">
            <span>
                Bạn đang xem với tư cách <b>{name ?? 'khách'}</b>
            </span>
            <button
                onClick={() => router.post(route('impersonate.stop'))}
                className="rounded-[8px] border border-[#e0d0a0] bg-white px-3 py-1 font-semibold transition hover:border-[#c9a227]"
            >
                Thoát
            </button>
        </div>
    );
}

/**
 * Khung chung mọi trang khách: Header + nội dung + Footer + Toast + Modal đăng nhập.
 * Dùng làm persistent layout của Inertia để giỏ/toast/đăng nhập sống xuyên suốt điều hướng.
 */
export default function SiteLayout({ children }: { children: ReactNode }) {
    const { auth, impersonating } = usePage<PageProps>().props;
    const [count, setCount] = useState(0);

    useEffect(() => {
        setCount(cartCount(getCart()));
        const offCart = on(EVENTS.cartChange, () =>
            setCount(cartCount(getCart())),
        );
        return () => {
            offCart();
        };
    }, []);

    return (
        <>
            {impersonating && <ImpersonationBar name={impersonating.name} />}
            <Header cartCount={count} userName={auth.user?.name} />
            {children}
            <Footer />
            <Toast />
            <LoginModal />
            <ZaloFloatButton />
            <FeedbackWidget />
        </>
    );
}
