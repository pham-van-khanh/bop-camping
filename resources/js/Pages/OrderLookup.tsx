import { Head, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import OrderLookupPanel, { type LookupProps } from '@/Components/site/OrderLookupPanel';
import type { PageProps } from '@/types';

// Trang tra cứu đứng riêng — giữ cho khách vãng lai (link "Theo dõi đơn này" sau
// checkout, bookmark cũ). Khách đăng nhập dùng section tra cứu trong /tai-khoan.
type Props = PageProps<LookupProps>;

export default function OrderLookup() {
    const { order, not_found, query } = usePage<Props>().props;

    return (
        <>
            <Head title="Tra cứu đơn thuê" />
            <main className="mx-auto max-w-[640px] px-5 pb-12 pt-[38px]">
                <h1
                    className="mb-2 font-extrabold tracking-tight text-ink"
                    style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                >
                    Tra cứu đơn thuê
                </h1>
                <p className="mb-6 text-moss">Nhập mã đơn và số điện thoại đã đặt để xem trạng thái.</p>

                <OrderLookupPanel
                    key={`${query.code}|${query.phone}`}
                    lookup={{ order, not_found, query }}
                    routeName="lookup"
                />
            </main>
        </>
    );
}

OrderLookup.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
