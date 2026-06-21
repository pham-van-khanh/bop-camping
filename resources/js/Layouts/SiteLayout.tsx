import { ReactNode, useEffect, useState } from 'react';
import Header from '@/Components/site/Header';
import Footer from '@/Components/site/Footer';
import Toast from '@/Components/site/Toast';
import LoginModal from '@/Components/site/LoginModal';
import { on, EVENTS } from '@/lib/bus';
import { cartCount, getCart } from '@/lib/cart';
import { getUser, type LocalUser } from '@/lib/auth';

/**
 * Khung chung mọi trang khách: Header + nội dung + Footer + Toast + Modal đăng nhập.
 * Dùng làm persistent layout của Inertia để giỏ/toast/đăng nhập sống xuyên suốt điều hướng.
 */
export default function SiteLayout({ children }: { children: ReactNode }) {
    const [count, setCount] = useState(0);
    const [user, setUserState] = useState<LocalUser | null>(null);

    useEffect(() => {
        setCount(cartCount(getCart()));
        setUserState(getUser());
        const offCart = on(EVENTS.cartChange, () => setCount(cartCount(getCart())));
        const offUser = on(EVENTS.userChange, (u) => setUserState((u as LocalUser) ?? null));
        return () => {
            offCart();
            offUser();
        };
    }, []);

    return (
        <>
            <Header cartCount={count} userName={user?.name} />
            {children}
            <Footer />
            <Toast />
            <LoginModal />
        </>
    );
}
