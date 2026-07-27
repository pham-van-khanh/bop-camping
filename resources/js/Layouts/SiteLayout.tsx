import { ReactNode, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Header from '@/Components/site/Header';
import Footer from '@/Components/site/Footer';
import Toast from '@/Components/site/Toast';
import LoginModal from '@/Components/site/LoginModal';
import FeedbackWidget from '@/Components/site/FeedbackWidget';
import ZaloFloatButton from '@/Components/site/ZaloFloatButton';
import { on, EVENTS } from '@/lib/bus';
import { cartCount, getCart } from '@/lib/cart';
import type { PageProps } from '@/types';

/**
 * Khung chung mọi trang khách: Header + nội dung + Footer + Toast + Modal đăng nhập.
 * Dùng làm persistent layout của Inertia để giỏ/toast/đăng nhập sống xuyên suốt điều hướng.
 */
export default function SiteLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<PageProps>().props;
    const [count, setCount] = useState(0);

    useEffect(() => {
        setCount(cartCount(getCart()));
        const offCart = on(EVENTS.cartChange, () => setCount(cartCount(getCart())));
        return () => { offCart(); };
    }, []);

    return (
        <>
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
